<?php
/**
 * The site's webhook endpoint and the account subscription that points at it.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Webhook;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\WP\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Registers `POST /wp-json/assinafy/v1/webhook/<token>` and owns everything about that URL:
 * the token that makes it unguessable, the rotation of that token, and the account-side
 * subscription that tells Assinafy to deliver to it.
 *
 * Assinafy deliveries are unsigned. `PUT /accounts/{id}/webhooks/subscriptions` accepts
 * exactly `events`, `is_active`, `url` and `email` — there is no secret to register, no
 * signature header to verify, and the plugin must never claim otherwise. The endpoint's
 * secrecy is the whole of its authentication, which is why `permission_callback` compares
 * the URL token with `hash_equals()` and is never `__return_true`, and why the delivery body
 * is treated as a hint rather than as truth (see {@see Handler}).
 *
 * The subscription is account-wide and singular: one URL per Assinafy account, not one per
 * integration. `register()` is a wholesale upsert of all four fields, so pointing it at this
 * site replaces whatever endpoint the account already uses. {@see self::subscribe()}
 * therefore reads the current subscription first and refuses to take over a foreign URL
 * without an explicit confirmation.
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
	 * is registered even while deliveries are switched off: the subscription is held on the
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
	 * Returning a `WP_Error` rather than `false` keeps the status precise. This is the only
	 * gate on the endpoint, so it compares in constant time and refuses outright when no
	 * token has been generated yet.
	 *
	 * The token is read from `get_url_params()`, never `get_param()`. `get_param()` resolves
	 * in the order the REST server declares — JSON body, then POST body, then query string,
	 * and only then the URL match — so a delivery carrying its own `token` key would decide
	 * its own authentication. The value this gate checks is the one the route pattern
	 * captured from the path and nothing else.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Incoming request.
	 * @return true|WP_Error True when the token matches, an error otherwise.
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

		return true;
	}

	/**
	 * The stored endpoint token, or an empty string before one is generated.
	 */
	public static function token(): string {
		return (string) Settings::get( Settings::OPTION_WEBHOOK_TOKEN );
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
	 * re-register the subscription with {@see self::subscribe()} or the account keeps
	 * delivering to a URL that now answers 403.
	 *
	 * The token is the whole of the endpoint's authentication, so it comes straight from
	 * `random_bytes()`: 128 bits rendered as the hex the route pattern accepts.
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
	 * Whether a subscription points at this site.
	 *
	 * Matches on the endpoint prefix rather than the full URL so that a subscription still
	 * carrying a rotated-away token is recognised as this site's own and can be replaced
	 * without asking the operator to confirm a takeover.
	 *
	 * @param array<string, mixed>|null $subscription Subscription as returned by the API.
	 */
	private static function is_ours( ?array $subscription ): bool {
		$url = is_array( $subscription ) && is_string( $subscription['url'] ?? null )
			? untrailingslashit( $subscription['url'] )
			: '';

		if ( '' === $url ) {
			return false;
		}

		$base = untrailingslashit( rest_url( self::REST_NAMESPACE . '/webhook' ) );

		return $url === $base || str_starts_with( $url, $base . '/' );
	}

	/**
	 * Read the account's current subscription.
	 *
	 * Returns `null` — not an empty array — when the account has never configured one.
	 *
	 * Shape when present:
	 * ```
	 * array(
	 *   'events'     => array( 'document_ready', 'signer_signed_document' ),
	 *   'is_active'  => false,
	 *   'url'        => 'https://example.com/hooks/assinafy',
	 *   'email'      => 'ops@example.com',
	 *   'updated_at' => '2026-08-27T17:55:12Z',
	 * )
	 * ```
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @return array<string, mixed>|null|WP_Error The subscription, null when unset, or the
	 *     failure that prevented reading it.
	 */
	private static function subscription( AssinafyClient $client ): array|null|WP_Error {
		try {
			return $client->webhooks()->get();
		} catch ( \Throwable $e ) {
			return self::error( $e );
		}
	}

	/**
	 * Point the account's subscription at this site, refusing a foreign endpoint.
	 *
	 * A subscription that names another endpoint fails with `assinafy_webhook_takeover` and
	 * the URL on file in the error data, which is what the caller shows the operator before
	 * asking again. Answering that prompt means calling {@see self::take_over()}.
	 *
	 * @param AssinafyClient     $client Configured API client.
	 * @param string             $email  Address Assinafy alerts when delivery fails.
	 * @param array<int, string> $events Events to add; the defaults when empty.
	 * @return array<string, mixed>|WP_Error The stored subscription, or the failure.
	 */
	public static function subscribe( AssinafyClient $client, string $email, array $events = array() ): array|WP_Error {
		$existing = self::readable_subscription( $client );

		if ( $existing instanceof WP_Error ) {
			return $existing;
		}

		if ( null !== $existing && ! self::is_ours( $existing ) ) {
			return new WP_Error(
				'assinafy_webhook_takeover',
				__( 'This Assinafy account already delivers webhooks to another endpoint. Confirm to replace it.', 'assinafy' ),
				array(
					'url'    => is_string( $existing['url'] ?? null ) ? $existing['url'] : '',
					'status' => 409,
				)
			);
		}

		return self::upsert( $client, $email, $events, $existing );
	}

	/**
	 * Point the account's subscription at this site, replacing a foreign endpoint.
	 *
	 * The confirmed half of {@see self::subscribe()}: identical in every respect except that a
	 * subscription belonging to another endpoint is replaced instead of reported. Call it only
	 * once the operator has answered the `assinafy_webhook_takeover` prompt.
	 *
	 * @param AssinafyClient     $client Configured API client.
	 * @param string             $email  Address Assinafy alerts when delivery fails.
	 * @param array<int, string> $events Events to add; the defaults when empty.
	 * @return array<string, mixed>|WP_Error The stored subscription, or the failure.
	 */
	public static function take_over( AssinafyClient $client, string $email, array $events = array() ): array|WP_Error {
		$existing = self::readable_subscription( $client );

		if ( $existing instanceof WP_Error ) {
			return $existing;
		}

		return self::upsert( $client, $email, $events, $existing );
	}

	/**
	 * Read the account's subscription, refusing before the request when there is no endpoint.
	 *
	 * The token check comes first so a site that has never generated an endpoint makes no API
	 * call at all: there is nothing to register it as.
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @return array<string, mixed>|null|WP_Error The subscription, null when the account has
	 *     none, or the failure that prevented reading it.
	 */
	private static function readable_subscription( AssinafyClient $client ): array|null|WP_Error {
		if ( '' === self::url() ) {
			return new WP_Error(
				'assinafy_webhook_no_token',
				__( 'No webhook endpoint has been generated for this site yet.', 'assinafy' )
			);
		}

		return self::subscription( $client );
	}

	/**
	 * Write the subscription, keeping the events already on it.
	 *
	 * The upsert is wholesale — all four fields are sent every time, a partial update is
	 * impossible — so the event list is merged rather than replaced: enabling deliveries here
	 * can never stop an event another consumer relies on. Anything outside {@see self::EVENTS}
	 * is dropped, since the API rejects it.
	 *
	 * @param AssinafyClient            $client   Configured API client.
	 * @param string                    $email    Address Assinafy alerts when delivery fails.
	 * @param array<int, string>        $events   Events to add; the defaults when the merge is empty.
	 * @param array<string, mixed>|null $existing The subscription on file, or null.
	 * @return array<string, mixed>|WP_Error The stored subscription, or the failure.
	 */
	private static function upsert( AssinafyClient $client, string $email, array $events, ?array $existing ): array|WP_Error {
		$kept = is_array( $existing['events'] ?? null ) ? $existing['events'] : array();

		$merged = array_values(
			array_intersect(
				array_unique( array_merge( array_filter( $kept, 'is_string' ), $events ) ),
				self::EVENTS
			)
		);

		if ( array() === $merged ) {
			$merged = self::DEFAULT_EVENTS;
		}

		try {
			return $client->webhooks()->register( self::url(), $email, $merged, true );
		} catch ( \Throwable $e ) {
			return self::error( $e );
		}
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
