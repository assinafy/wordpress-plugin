<?php
/**
 * The site's webhook endpoint and the account endpoint that points at it.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Webhook;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Support\WebhookEventParser;
use Assinafy\WP\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Registers `POST /wp-json/assinafy/v1/webhook/<token>` and owns everything about that URL:
 * the token that makes it unguessable, the rotation of that token, and the account-side
 * endpoint that tells Assinafy to deliver to it.
 *
 * Deliveries authenticate in two layers. The URL token is the baseline: `permission_callback`
 * compares it with `hash_equals()` and is never `__return_true`. When a `whsec_` signing
 * secret is configured — stored by registration on API-key sites, pasted from the Assinafy
 * dashboard on OAuth sites — the delivery's Standard Webhooks signature is verified on top,
 * and an unsigned, badly signed or stale delivery fails with a 401. With no secret configured
 * the token alone authenticates, exactly as before, so the body is treated as a hint rather
 * than as truth either way (see {@see Handler}).
 *
 * An account has one endpoint, or up to three on paid plans, and every active endpoint
 * subscribed to an event receives it. {@see self::subscribe()} therefore manages this site's
 * own endpoint — found by URL match among the account's endpoints, created when absent —
 * and never touches an endpoint belonging to another integration.
 */
final class Route {

	/**
	 * REST namespace the endpoint lives in.
	 */
	public const REST_NAMESPACE = 'assinafy/v1';

	/**
	 * Token length in characters. The route regex pins the same number.
	 */
	public const TOKEN_LENGTH = 32;

	/**
	 * Random bytes behind one token. Hex doubles, so this is half {@see self::TOKEN_LENGTH}.
	 */
	private const TOKEN_BYTES = 16;

	/**
	 * Every event `GET /webhooks/event-types` offers, in the order it returns them.
	 *
	 * Exactly fifteen. `template_created`, `template_processed` and
	 * `template_processing_failed` exist as SDK constants and in the published event catalog
	 * but are not subscribable, so they are absent here and are filtered out of any event
	 * list handed to {@see self::subscribe()}.
	 *
	 * @var array<int, string>
	 */
	public const EVENTS = array(
		'document_uploaded',
		'document_metadata_ready',
		'document_prepared',
		'assignment_created',
		'signature_requested',
		'document_ready',
		'signer_created',
		'signer_email_verified',
		'signer_whatsapp_verified',
		'signer_data_confirmed',
		'signer_signed_document',
		'signer_viewed_document',
		'signer_rejected_document',
		'user_rejected_document',
		'document_processing_failed',
	);

	/**
	 * The events the plugin asks for when nothing more specific is requested.
	 *
	 * Every one of these changes what the document list shows, so each is worth a delivery.
	 * The rest are reachable through the hourly reconcile run.
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_EVENTS = array(
		'document_metadata_ready',
		'document_ready',
		'signer_viewed_document',
		'signer_signed_document',
		'signer_rejected_document',
		'user_rejected_document',
		'document_processing_failed',
	);

	/**
	 * @param Handler $handler The delivery handler that backs the route callback.
	 */
	public function __construct( private readonly Handler $handler ) {
	}

	/**
	 * Attach the route to the REST API.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Declare the endpoint.
	 *
	 * The token is matched by the URL pattern and checked again in `permission_callback`:
	 * the pattern only narrows the shape, `hash_equals()` is what authenticates. The route
	 * is registered even while deliveries are switched off: the endpoint lives on the
	 * Assinafy account rather than here, so an authenticated delivery still has to be
	 * answered, and {@see Handler::handle()} acknowledges it without doing any work.
	 *
	 * There is deliberately no `args` schema. Declaring one makes
	 * `WP_REST_Request::sanitize_params()` walk every parameter the request carries, and its
	 * JSON parameters are whatever `json_decode()` made of the body — for a body that is a
	 * bare JSON scalar, a string or an integer, which it then tries to `foreach`. That is an
	 * uncaught `TypeError`, so an endpoint declaring `args` answers a one-byte body with a
	 * 500. Nothing is lost by omitting it: the route pattern already fixes the token's
	 * length and alphabet, and {@see self::check_token()} reads the value the pattern
	 * captured rather than anything a schema would sanitise. The delivery body is parsed by
	 * {@see Handler::handle()} from the raw request, which is the only reader that should
	 * ever touch it.
	 */
	public function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/webhook/(?P<token>[A-Za-z0-9]{' . self::TOKEN_LENGTH . '})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this->handler, 'handle' ),
				'permission_callback' => array( $this, 'check_token' ),
				'show_in_index'       => false,
			)
		);
	}

	/**
	 * Authenticate the delivery.
	 *
	 * Returning a `WP_Error` rather than `false` keeps the status precise. The token gate
	 * compares in constant time and refuses outright when no token has been generated yet.
	 *
	 * The token is read from `get_url_params()`, never `get_param()`. `get_param()` resolves
	 * in the order the REST server declares — JSON body, then POST body, then query string,
	 * and only then the URL match — so a delivery carrying its own `token` key would decide
	 * its own authentication. The value this gate checks is the one the route pattern
	 * captured from the path and nothing else.
	 *
	 * When a signing secret is stored, the delivery must additionally carry a valid Standard
	 * Webhooks signature over the raw body with a fresh timestamp; an unsigned, badly signed
	 * or stale delivery fails closed. A stored secret that cannot be parsed fails the delivery
	 * rather than widening the gate.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Incoming request.
	 * @return true|WP_Error True when the delivery authenticates, an error otherwise.
	 */
	public function check_token( WP_REST_Request $request ): bool|WP_Error {
		$stored = self::token();
		$given  = $request->get_url_params()['token'] ?? null;

		if ( '' === $stored || ! is_string( $given ) || ! hash_equals( $stored, $given ) ) {
			return new WP_Error(
				'assinafy_webhook_forbidden',
				__( 'Invalid webhook endpoint.', 'assinafy' ),
				array( 'status' => 403 )
			);
		}

		if ( ! self::signature_valid( $request ) ) {
			return new WP_Error(
				'assinafy_webhook_bad_signature',
				__( 'Invalid webhook signature.', 'assinafy' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Verify the delivery's Standard Webhooks signature when a secret is configured.
	 *
	 * With no secret stored the token alone authenticates. A stored secret that cannot be
	 * parsed makes the parser raise, which fails the delivery closed rather than widening
	 * the gate.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Incoming request.
	 */
	private static function signature_valid( WP_REST_Request $request ): bool {
		$secret = self::secret();

		if ( '' === $secret ) {
			return true;
		}

		try {
			return ( new WebhookEventParser() )->verifySignature( $request->get_body(), $request->get_headers(), $secret );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * The stored endpoint token, or an empty string before one is generated.
	 */
	public static function token(): string {
		return (string) Settings::get( Settings::OPTION_WEBHOOK_TOKEN );
	}

	/**
	 * The decrypted `whsec_` signing secret, or an empty string when none is stored.
	 */
	public static function secret(): string {
		return SigningSecret::get();
	}

	/**
	 * This site's delivery endpoint, or an empty string while no token exists.
	 *
	 * Example: `https://example.com/wp-json/assinafy/v1/webhook/<token>`.
	 */
	public static function url(): string {
		$token = self::token();

		if ( '' === $token ) {
			return '';
		}

		return rest_url( self::REST_NAMESPACE . '/webhook/' . $token );
	}

	/**
	 * Issue a new token and return it.
	 *
	 * The old endpoint stops authenticating the moment this returns, so the caller must
	 * re-register with {@see self::subscribe()} or the account keeps delivering to a URL that
	 * now answers 403.
	 *
	 * The token is the whole of the endpoint's baseline authentication, so it comes straight
	 * from `random_bytes()`: 128 bits rendered as the hex the route pattern accepts.
	 * `wp_generate_password()` would route the value through the `random_password` filter,
	 * where any plugin on the site could shorten or fix it.
	 *
	 * @return string The new token.
	 */
	public static function rotate_token(): string {
		$token = bin2hex( random_bytes( self::TOKEN_BYTES ) );

		update_option( Settings::OPTION_WEBHOOK_TOKEN, $token, false );

		return $token;
	}

	/**
	 * Whether an endpoint belongs to this site.
	 *
	 * Matches on the endpoint prefix rather than the full URL so that an endpoint still
	 * carrying a rotated-away token is recognised as this site's own and is updated in place.
	 * An endpoint without a URL is never this site's.
	 *
	 * @param array<string, mixed>|null $endpoint Endpoint as returned by the API.
	 */
	public static function is_ours( ?array $endpoint ): bool {
		$url = is_string( $endpoint['url'] ?? null ) ? untrailingslashit( $endpoint['url'] ) : '';

		if ( '' === $url ) {
			return false;
		}

		$base = untrailingslashit( rest_url( self::REST_NAMESPACE . '/webhook' ) );

		return $url === $base || str_starts_with( $url, $base . '/' );
	}

	/**
	 * Register or update this site's own webhook endpoint on the account.
	 *
	 * The account's endpoints are listed first and the one whose URL names this site —
	 * {@see self::is_ours()} matches on the endpoint prefix — is updated in place; when none
	 * matches, a new endpoint is created. Foreign endpoints coexist and are never touched,
	 * which is why a full plan fails with `assinafy_webhook_limit` rather than an offer to
	 * take one over.
	 *
	 * On success the endpoint id is stored, and on API-key sites the endpoint's signing
	 * secret is read and stored encrypted. OAuth applications cannot read endpoint secrets,
	 * so an OAuth site keeps whatever the operator pasted into the settings field.
	 *
	 * @param AssinafyClient     $client Configured API client.
	 * @param string             $email  Address Assinafy alerts when delivery fails.
	 * @param array<int, string> $events Events to add; the defaults when the merge is empty.
	 * @return array<string, mixed>|WP_Error The stored endpoint, or the failure.
	 */
	public static function subscribe( AssinafyClient $client, string $email, array $events = array() ): array|WP_Error {
		if ( '' === self::url() ) {
			return new WP_Error(
				'assinafy_webhook_no_token',
				__( 'No webhook endpoint has been generated for this site yet.', 'assinafy' )
			);
		}

		$endpoints = self::endpoints( $client );

		if ( $endpoints instanceof WP_Error ) {
			return $endpoints;
		}

		$existing = self::own_endpoint( $endpoints );

		return self::write_endpoint( $client, $email, self::merged_events( $existing['events'] ?? null, $events ), $existing );
	}

	/**
	 * The account's endpoints, oldest first, or the failure that prevented reading them.
	 *
	 * A list that cannot be read is reported rather than assumed empty: assuming absence
	 * would create a second endpoint for a site that already has one.
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function endpoints( AssinafyClient $client ): array|WP_Error {
		try {
			return $client->webhooks()->listEndpoints();
		} catch ( \Throwable $e ) {
			return self::error( $e );
		}
	}

	/**
	 * The endpoint belonging to this site, when the account has one.
	 *
	 * @param array<int, array<string, mixed>> $endpoints The account's endpoints.
	 * @return array<string, mixed>|null
	 */
	private static function own_endpoint( array $endpoints ): ?array {
		foreach ( $endpoints as $endpoint ) {
			if ( self::is_ours( $endpoint ) ) {
				return $endpoint;
			}
		}

		return null;
	}

	/**
	 * Update the matching endpoint, or create this site's own when none matches.
	 *
	 * On success the endpoint id is stored, and on API-key sites the endpoint's signing
	 * secret is read and stored encrypted. OAuth applications cannot read endpoint secrets,
	 * so an OAuth site keeps whatever the operator pasted into the settings field.
	 *
	 * @param AssinafyClient            $client   Configured API client.
	 * @param string                    $email    Address Assinafy alerts when delivery fails.
	 * @param array<int, string>        $events   The merged event list.
	 * @param array<string, mixed>|null $existing This site's endpoint on file, or null.
	 * @return array<string, mixed>|WP_Error The stored endpoint, or the failure.
	 */
	private static function write_endpoint( AssinafyClient $client, string $email, array $events, ?array $existing ): array|WP_Error {
		$id = null !== $existing && is_string( $existing['id'] ?? null ) ? $existing['id'] : '';

		// A short label telling this site's endpoint apart from the account's others.
		$host = (string) wp_parse_url( self::url(), PHP_URL_HOST );

		try {
			$endpoint = '' !== $id
				? $client->webhooks()->updateEndpoint(
					$id,
					array(
						'url'             => self::url(),
						'email'           => $email,
						'events'          => $events,
						'is_active'       => true,
						'signing_enabled' => true,
					)
				)
				: $client->webhooks()->createEndpoint( self::url(), $email, $events, '' !== $host ? $host : 'WordPress', true, true );
		} catch ( \Throwable $e ) {
			return self::write_error( $e );
		}

		$id = is_string( $endpoint['id'] ?? null ) ? $endpoint['id'] : '';

		if ( '' !== $id ) {
			update_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, $id, false );
			SigningSecret::store( $client, $id );
		}

		return $endpoint;
	}

	/**
	 * Turn a failed endpoint write into a `WP_Error`.
	 *
	 * A 403 from the create route is the plan's endpoint limit, reported as such: foreign
	 * endpoints coexist with this site's, so there is nothing to take over — the operator
	 * removes one in the Assinafy dashboard first.
	 *
	 * @param \Throwable $e The failure.
	 */
	private static function write_error( \Throwable $e ): WP_Error {
		if ( $e instanceof ApiException && 403 === $e->getStatusCode() ) {
			return new WP_Error(
				'assinafy_webhook_limit',
				__( 'This Assinafy account already has the maximum number of webhook endpoints — paid plans allow up to three. Remove one in the Assinafy dashboard before registering this site.', 'assinafy' ),
				array( 'status' => 403 )
			);
		}

		return self::error( $e );
	}

	/**
	 * The events to ask for, keeping the ones already on the endpoint.
	 *
	 * The event list is merged rather than replaced: re-registering here can never stop an
	 * event this endpoint already received. Anything outside {@see self::EVENTS} is dropped,
	 * since the API rejects it.
	 *
	 * @param mixed             $kept   The endpoint's current events, when it has any.
	 * @param array<int, string> $events Events to add; the defaults when the merge is empty.
	 * @return array<int, string>
	 */
	private static function merged_events( mixed $kept, array $events ): array {
		$kept = is_array( $kept ) ? $kept : array();

		$merged = array_values(
			array_intersect(
				array_unique( array_merge( array_filter( $kept, 'is_string' ), $events ) ),
				self::EVENTS
			)
		);

		return array() === $merged ? self::DEFAULT_EVENTS : $merged;
	}

	/**
	 * Turn a thrown SDK failure into a `WP_Error` the admin screen can render.
	 *
	 * @param \Throwable $e The failure.
	 */
	private static function error( \Throwable $e ): WP_Error {
		$status = $e instanceof \Assinafy\SDK\Exceptions\ApiException ? $e->getStatusCode() : 0;

		return new WP_Error(
			'assinafy_webhook_request_failed',
			$e->getMessage(),
			array(
				'type'   => get_debug_type( $e ),
				'status' => $status > 0 ? $status : 500,
			)
		);
	}
}
