<?php
/**
 * WP-CLI commands.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Http\RateLimit;
use Assinafy\WP\Webhook\Route;
use WP_CLI;

/**
 * `wp assinafy <command>`.
 *
 * Each subcommand is registered against its own method rather than registering this object
 * wholesale, because WP-CLI turns every public method of a registered object into a
 * subcommand — `register()` included.
 */
final class Cli {

	/**
	 * @param ClientFactory $clients     API client factory.
	 * @param SendService   $sender      The domain send action.
	 * @param StatusSync    $status_sync Local-to-remote reconciliation.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly SendService $sender,
		private readonly StatusSync $status_sync
	) {
	}

	/**
	 * Register every subcommand.
	 */
	public function register(): void {
		WP_CLI::add_command( 'assinafy status', array( $this, 'status' ) );
		WP_CLI::add_command( 'assinafy send', array( $this, 'send' ) );
		WP_CLI::add_command( 'assinafy sync', array( $this, 'sync' ) );
		WP_CLI::add_command( 'assinafy webhook', array( new WebhookCli( $this->clients ), 'webhook' ) );
	}

	/**
	 * Report how this site is connected to Assinafy.
	 *
	 * Reads the account, the account-wide webhook subscription and the rate-limit budget left
	 * over from the last API call this site made. Contacting the account costs one request;
	 * reading the subscription costs a second.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render the report in this format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Check a site's connection.
	 *     $ wp assinafy status
	 *     +----------------------+---------------------------------------------+
	 *     | field                | value                                       |
	 *     +----------------------+---------------------------------------------+
	 *     | Environment          | production                                  |
	 *     | API base URL         | https://api.assinafy.com.br/v1              |
	 *     | Credentials          | configured                                  |
	 *     +----------------------+---------------------------------------------+
	 *
	 *     # Assert connectivity from a deploy script.
	 *     $ wp assinafy status --format=json | jq -e '.[] | select(.field=="Account")'
	 *
	 * @param array<int, string>    $args       Positional arguments. Unused.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$this->status_rows(),
			array( 'field', 'value' )
		);
	}

	/**
	 * The report, one row per fact.
	 *
	 * Credentials that cannot produce a client end the report there: every row below reads
	 * the API, and there is nothing to read it with.
	 *
	 * @return array<int, array<string, string>> The rows, in display order.
	 */
	private function status_rows(): array {
		$rows = array(
			$this->row( 'Environment', (string) Settings::get( Settings::OPTION_ENVIRONMENT ) ),
			$this->row( 'API base URL', $this->clients->base_url() ),
		);

		$client = $this->clients->client();

		if ( null === $client ) {
			$error = $this->clients->error();

			$rows[] = $this->row(
				'Credentials',
				null !== $error ? $error->get_error_message() : 'not configured'
			);

			return $rows;
		}

		$rows[] = $this->row( 'Credentials', 'configured' );
		$rows[] = $this->account_row( $client );
		$rows[] = $this->row( 'This site endpoint', Route::url() );

		return array_merge( $rows, $this->subscription_rows( $client ), array( $this->budget_row() ) );
	}

	/**
	 * The account row. One request.
	 *
	 * @param AssinafyClient $client Connected client.
	 *
	 * @return array<string, string> The row.
	 */
	private function account_row( AssinafyClient $client ): array {
		try {
			$account = $client->accounts()->get();
		} catch ( \Throwable $e ) {
			return $this->row( 'Account', 'unreachable: ' . $e->getMessage() );
		}

		return $this->row(
			'Account',
			sprintf(
				'%s (%s)',
				is_string( $account['name'] ?? null ) ? $account['name'] : 'unnamed',
				is_string( $account['id'] ?? null ) ? $account['id'] : 'unknown id'
			)
		);
	}

	/**
	 * The webhook rows. One request.
	 *
	 * @param AssinafyClient $client Connected client.
	 *
	 * @return array<int, array<string, string>> The rows, in display order.
	 */
	private function subscription_rows( AssinafyClient $client ): array {
		try {
			$subscription = $client->webhooks()->get();
		} catch ( \Throwable $e ) {
			return array( $this->row( 'Webhook subscription', 'unreadable: ' . $e->getMessage() ) );
		}

		if ( null === $subscription ) {
			return array( $this->row( 'Webhook subscription', 'none registered on this account' ) );
		}

		$events = $subscription['events'] ?? array();

		return array(
			$this->row( 'Webhook subscription', (string) ( $subscription['url'] ?? '' ) ),
			$this->row( 'Webhook delivering', ( $subscription['is_active'] ?? false ) ? 'yes' : 'no' ),
			$this->row( 'Webhook events', is_array( $events ) ? implode( ', ', array_map( 'strval', $events ) ) : '' ),
		);
	}

	/**
	 * The rate-limit row, read from the last API call this site made rather than from a
	 * request of its own.
	 *
	 * @return array<string, string> The row.
	 */
	private function budget_row(): array {
		$budget = RateLimit::snapshot();

		return $this->row(
			'Rate budget',
			null === $budget
				? 'unknown — no request recorded in the last two minutes'
				: sprintf(
					'%d requests left, window resets in %ds (read %ds ago)',
					$budget['remaining'],
					$budget['reset'],
					max( 0, time() - $budget['recorded'] )
				)
		);
	}

	/**
	 * Send a PDF for signature.
	 *
	 * Uploads the document, resolves every signer against the account (reusing an existing
	 * signer record when the email matches) and dispatches the request. The local
	 * `assinafy_document` post it creates is what the admin screens and the reconcile cron
	 * work from afterwards.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Path to the PDF to send. Mutually exclusive with --attachment.
	 *
	 * [--attachment=<id>]
	 * : Media-library attachment id of the PDF. Mutually exclusive with --file.
	 *
	 * --signers=<list>
	 * : Comma-separated signers. Each is either `Full Name <address@example.com>` or a bare
	 * email address, in which case the address doubles as the name.
	 *
	 * [--message=<text>]
	 * : Message shown in the invitation. Defaults to the configured message.
	 *
	 * [--expires=<datetime>]
	 * : Expiry as ISO 8601 with a Z or +/-HH:MM offset, for example 2026-12-31T23:59:59Z.
	 * Defaults to the configured expiry window.
	 *
	 * [--key=<idempotency-key>]
	 * : Stable identifier for this send. Repeating a command with the same key inside the
	 * lock window sends once. Defaults to a key derived from the document and the signers.
	 *
	 * [--porcelain]
	 * : Print just the new post id.
	 *
	 * ## EXAMPLES
	 *
	 *     # Send a file from disk to one signer.
	 *     $ wp assinafy send --file=/srv/contracts/nda.pdf --signers="Jane Doe <jane@example.com>"
	 *     Success: Sent. Local record: post 4187.
	 *
	 *     # Send a media-library PDF to two signers who sign in parallel.
	 *     $ wp assinafy send --attachment=412 --signers="jane@example.com,sam@example.com"
	 *
	 *     # Capture the post id for a shell script.
	 *     $ POST=$(wp assinafy send --attachment=412 --signers=jane@example.com --porcelain)
	 *
	 * @param array<int, string>    $args       Positional arguments. Unused.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function send( array $args, array $assoc_args ): void {
		$send_args = $this->send_args( $assoc_args );

		if ( array() === $send_args ) {
			return;
		}

		try {
			$post_id = $this->sender->send( $send_args );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );

			return;
		}

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( $post_id->get_error_message() );

			return;
		}

		if ( isset( $assoc_args['porcelain'] ) ) {
			WP_CLI::line( (string) $post_id );

			return;
		}

		WP_CLI::success( sprintf( 'Sent. Local record: post %d.', $post_id ) );
	}

	/**
	 * Turn the command's options into the `SendService` argument array.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 *
	 * @return array<string, mixed> The arguments to send, or an empty array once the reason
	 *                              it cannot be sent has been reported.
	 */
	private function send_args( array $assoc_args ): array {
		$file       = trim( (string) ( $assoc_args['file'] ?? '' ) );
		$attachment = (int) ( $assoc_args['attachment'] ?? 0 );

		if ( ! $this->source_is_usable( $file, $attachment ) ) {
			return array();
		}

		$signers = $this->parse_signers( (string) ( $assoc_args['signers'] ?? '' ) );

		if ( array() === $signers ) {
			WP_CLI::error( 'Pass --signers with at least one "Name <address@example.com>" or bare email address.' );

			return array();
		}

		$send_args = array(
			'signers'         => $signers,
			'idempotency_key' => (string) ( $assoc_args['key'] ?? 'cli-' . md5( $file . $attachment . wp_json_encode( $signers ) ) ),
		);

		// Exactly one of the two sources is set; the other was refused above.
		$send_args += '' !== $file
			? array( 'file_path' => $file )
			: array( 'attachment_id' => $attachment );

		if ( isset( $assoc_args['message'] ) ) {
			$send_args['message'] = (string) $assoc_args['message'];
		}

		if ( isset( $assoc_args['expires'] ) ) {
			$send_args['expires_at'] = (string) $assoc_args['expires'];
		}

		return $send_args;
	}

	/**
	 * Whether the chosen PDF source can be sent, reporting the reason when it cannot.
	 *
	 * @param string $file       Path from `--file`, or an empty string.
	 * @param int    $attachment Attachment id from `--attachment`, or 0.
	 */
	private function source_is_usable( string $file, int $attachment ): bool {
		if ( ( '' === $file ) === ( 0 === $attachment ) ) {
			WP_CLI::error( 'Pass exactly one of --file or --attachment.' );

			return false;
		}

		if ( '' !== $file && ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $file ) );

			return false;
		}

		if ( 0 !== $attachment && 'application/pdf' !== get_post_mime_type( $attachment ) ) {
			WP_CLI::error( sprintf( 'Attachment %d is not a PDF.', $attachment ) );

			return false;
		}

		return true;
	}

	/**
	 * Refresh local records from Assinafy.
	 *
	 * With a document id, re-reads that one document. Without one, runs the same reconcile
	 * pass the hourly cron runs: the oldest open records first, stopping early if the
	 * rate-limit budget is thin. This is what keeps a site correct when it has no webhook
	 * registered, or when a delivery was missed.
	 *
	 * ## OPTIONS
	 *
	 * [<document-id>]
	 * : Assinafy document id to refresh. Omit to run the reconcile pass over open records.
	 *
	 * ## EXAMPLES
	 *
	 *     # Refresh everything still open.
	 *     $ wp assinafy sync
	 *     Success: Reconcile pass finished.
	 *
	 *     # Refresh one document after a support question.
	 *     $ wp assinafy sync 104618b275d321f5de22240ebfda
	 *     Success: Refreshed 104618b275d321f5de22240ebfda.
	 *
	 *     # Replace the hourly WP-Cron pass with a system timer, every 15 minutes.
	 *     $ crontab -e   # then: 0,15,30,45 * * * * cd /srv/site && wp assinafy sync --quiet
	 *
	 * @param array<int, string> $args Positional arguments: an optional document id.
	 */
	public function sync( array $args ): void {
		$document_id = trim( (string) ( $args[0] ?? '' ) );

		try {
			if ( '' === $document_id ) {
				$this->status_sync->reconcile();

				WP_CLI::success( 'Reconcile pass finished.' );

				return;
			}

			$refreshed = $this->status_sync->sync_one( $document_id );

			if ( is_wp_error( $refreshed ) ) {
				WP_CLI::error( $refreshed->get_error_message() );

				return;
			}

			WP_CLI::success( sprintf( 'Refreshed %s.', $document_id ) );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Parse the `--signers` list.
	 *
	 * @param string $raw Comma-separated `Name <address>` or bare address entries.
	 *
	 * @return array<int, array<string, string>> Signer descriptions for `SendService`.
	 */
	private function parse_signers( string $raw ): array {
		$signers = array();

		foreach ( explode( ',', $raw ) as $entry ) {
			$entry = trim( $entry );

			if ( '' === $entry ) {
				continue;
			}

			$name = '';

			if ( 1 === preg_match( '/^(.*?)<([^>]+)>$/', $entry, $matches ) ) {
				$name  = trim( $matches[1] );
				$entry = trim( $matches[2] );
			}

			// Validation reads what was typed, as `Signers::normalize()` explains; the
			// sanitised value is only what gets stored.
			if ( ! is_email( $entry ) ) {
				WP_CLI::error( sprintf( '%s is not a valid email address.', $entry ) );

				return array();
			}

			$email = sanitize_email( $entry );

			$signers[] = array(
				'full_name' => '' !== $name ? $name : $email,
				'email'     => $email,
			);
		}

		return $signers;
	}

	/**
	 * One report row.
	 *
	 * @param string $field Label.
	 * @param string $value Value.
	 *
	 * @return array<string, string> The row.
	 */
	private function row( string $field, string $value ): array {
		return array(
			'field' => $field,
			'value' => $value,
		);
	}
}
