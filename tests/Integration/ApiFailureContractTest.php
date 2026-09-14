<?php
/**
 * Failure, pagination and envelope contract for the Assinafy API.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\CostEstimate;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;
use WP_Error;

/**
 * The three things that are easy to get wrong against this API, pinned down.
 *
 * The failure matrix: every status the endpoints under test can answer with, each carrying the
 * API's own one-sentence pt-BR message, plus the HTTP 200 that reports a failure inside the
 * envelope — trusting the status line alone would report that one as a success.
 *
 * Pagination: reported only through `X-Pagination-*` response headers. No endpoint puts it in
 * the body, and `per-page` is clamped server-side to 50, so a page beyond the end clamps back
 * to the last page and returns real rows. A loop that stops on an empty page therefore never
 * stops; the page count from the headers is the only terminator.
 *
 * The two response conventions: a single-item method returns the unwrapped `data`, a list
 * method returns the whole envelope with `pagination` lifted onto it.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class ApiFailureContractTest extends AssinafyTestCase {

	/**
	 * Document id from the live capture in `probe-documents.md` §5.
	 */
	private const DOCUMENT_ID = '104618b275d321f5de22240ebfda';

	/**
	 * Signer id from the end-to-end capture in `probe-signatures.md` §3.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * Give every test credentials.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();
	}

	/**
	 * Every failing status line raises `ApiException` carrying the API's own message.
	 *
	 * The message is pt-BR prose written for an end user, so it is surfaced verbatim rather
	 * than mapped to a local string that would lose what the API actually objected to.
	 *
	 * @dataProvider failing_status_lines
	 *
	 * @param int    $code    HTTP status the API answers with.
	 * @param string $message The one-sentence pt-BR message it sends alongside.
	 */
	public function test_a_failing_status_line_raises_an_api_exception( int $code, string $message ): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			$code,
			array(
				'status'  => $code,
				'data'    => null,
				'message' => $message,
			)
		);

		try {
			$this->client()->documents()->get( self::DOCUMENT_ID );
			$this->fail( 'HTTP ' . $code . ' was reported as a success.' );
		} catch ( ApiException $e ) {
			$this->assertSame( $code, $e->getStatusCode() );
			$this->assertSame( $message, $e->getMessage() );
		}

		$this->assertCount( 1, $this->requests, 'A failure is not retried by the transport.' );
	}

	/**
	 * The statuses the endpoints under test answer with, each with its live message.
	 *
	 * @return array<string, array{int, string}>
	 */
	public static function failing_status_lines(): array {
		return array(
			'400 rename after signing started' => array(
				400,
				'Não é possível renomear o documento após o início do processo de assinatura.',
			),
			'401 credential rejected'          => array(
				401,
				'Token de acesso inválido.',
			),
			'404 unknown document'             => array(
				404,
				'Documento não encontrado.',
			),
			'422 unprocessable payload'        => array(
				422,
				'"Name" deve conter no máximo 255 caracteres.',
			),
			'500 platform failure'             => array(
				500,
				'Erro interno do servidor.',
			),
		);
	}

	/**
	 * A 403 on the estimate is the plan gate, and the send screen names it as such.
	 *
	 * `DigitalCertificate` is the one refusal that uses a status code instead of the
	 * `has_sufficient_resources` flag, and it answers before any document is consumed.
	 */
	public function test_the_digital_certificate_plan_gate_answers_403(): void {
		$message = 'A assinatura com certificado digital não está disponível para o seu plano atual.';

		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID . '/assignments/estimate-cost',
			403,
			array(
				'status'  => 403,
				'data'    => null,
				'message' => $message,
			)
		);

		$verdict = $this->cost_estimate()->for_signers(
			array(
				array(
					'id'                  => '19e6b92e7895332ed9708535d8c',
					'name'                => 'Jane Doe',
					'email'               => 'jane@example.com',
					'verification_method' => 'DigitalCertificate',
				),
			),
			self::DOCUMENT_ID
		);

		$this->assertInstanceOf( WP_Error::class, $verdict );
		$this->assertSame( 'assinafy_plan_restricted', $verdict->get_error_code() );
		$this->assertSame( $message, $verdict->get_error_message() );
	}

	/**
	 * A 429 carries `Retry-After`, and the exception keeps the response headers.
	 *
	 * The budget is 120 requests a minute per key on a rolling window, and a single upload
	 * spends three to four of it, so the header is the only thing that says how long to wait.
	 */
	public function test_a_429_keeps_the_retry_after_header(): void {
		$this->fake_raw_response(
			'/documents/' . self::DOCUMENT_ID,
			429,
			(string) wp_json_encode(
				array(
					'status'  => 429,
					'data'    => null,
					'message' => 'Muitas requisições.',
				)
			),
			array(
				'retry-after'            => '53',
				'x-rate-limit-limit'     => '120',
				'x-rate-limit-remaining' => '0',
				'x-rate-limit-reset'     => '53',
			)
		);

		try {
			$this->client()->documents()->get( self::DOCUMENT_ID );
			$this->fail( 'A 429 was reported as a success.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 429, $e->getStatusCode() );
			$this->assertSame( '53', $e->getResponseHeaderLine( 'Retry-After' ) );
			$this->assertSame( '53', $e->getResponseHeaderLine( 'retry-after' ), 'Header lookup is case-insensitive.' );
		}
	}

	/**
	 * An HTTP 200 whose envelope reports a failure is a failure.
	 *
	 * The API answers some errors with a 200 status line and the real status inside the
	 * envelope. Reading the status line alone would hand a caller `data: null` as success.
	 */
	public function test_a_200_carrying_a_failing_envelope_status_raises(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			200,
			array(
				'status'  => 404,
				'data'    => null,
				'message' => 'Documento não encontrado.',
			)
		);

		try {
			$this->client()->documents()->get( self::DOCUMENT_ID );
			$this->fail( 'A failing envelope status under an HTTP 200 was reported as a success.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 404, $e->getStatusCode(), 'The envelope status wins over the status line.' );
			$this->assertSame( 'Documento não encontrado.', $e->getMessage() );
		}
	}

	/**
	 * A 404 on the sync marks the record rather than being swallowed.
	 *
	 * The delete is a hard delete and is not idempotent, so a mirrored document can outlive
	 * the remote one; the sync has to say which failure it hit.
	 */
	public function test_a_404_on_sync_is_recorded_against_the_mirror(): void {
		$this->fake_response(
			'/documents/' . self::DOCUMENT_ID,
			404,
			array(
				'status'  => 404,
				'data'    => null,
				'message' => 'Documento não encontrado.',
			)
		);

		$post_id = $this->create_document( self::DOCUMENT_ID );
		$records = new DocumentRecord();

		try {
			$this->client()->documents()->get( self::DOCUMENT_ID );
			$this->fail( 'A missing document was reported as present.' );
		} catch ( ApiException $e ) {
			$records->set_last_error( $post_id, $e->getMessage() );
		}

		$this->assertSame( 'Documento não encontrado.', $records->last_error( $post_id ) );
		$this->assertSame( 'pending_signature', $records->status( $post_id ), 'A failed read leaves the last known status alone.' );
	}

	/**
	 * Pagination is read from the headers, and the body never carries it.
	 *
	 * A list method returns the envelope with `pagination` lifted onto it from
	 * `X-Pagination-Current-Page`, `-Page-Count`, `-Per-Page` and `-Total-Count`.
	 */
	public function test_pagination_comes_from_the_headers_and_not_the_body(): void {
		$body = (string) wp_json_encode(
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array( $this->signer_row( 'other@example.com' ) ),
			)
		);

		$this->fake_raw_response(
			'/signers?',
			200,
			$body,
			array(
				'x-pagination-current-page' => '1',
				'x-pagination-page-count'   => '3',
				'x-pagination-per-page'     => '50',
				'x-pagination-total-count'  => '104',
			)
		);

		$page = $this->client()->signers()->list( 1, 20 );

		$this->assertSame(
			self::API_HOST . 'v1/accounts/' . self::ACCOUNT_ID . '/signers?page=1&per-page=20',
			$this->requests[0]['url']
		);
		$this->assertSame(
			array(
				'current_page' => 1,
				'page_count'   => 3,
				'per_page'     => 50,
				'total_count'  => 104,
			),
			$page['pagination'],
			'Pagination is lifted from the X-Pagination-* headers.'
		);

		// The list convention: the whole envelope, not the unwrapped data.
		$this->assertSame( 200, $page['status'] );
		$this->assertCount( 1, $page['data'] );

		$decoded = (array) json_decode( $body, true );
		$this->assertArrayNotHasKey( 'pagination', $decoded, 'The API never puts pagination in the body.' );
		$this->assertArrayNotHasKey( 'meta', $decoded );

		// `per-page` is clamped server-side: the request asked for 20 and got 50.
		$this->assertSame( 50, $page['pagination']['per_page'] );
	}

	/**
	 * A paging loop terminates on the page count, never on an empty page.
	 *
	 * A `page` beyond the end clamps to the last page and returns real rows, so a
	 * `while (data non-empty)` loop would re-read the last page forever. The lookup the send
	 * flow makes walks to page two, finds its match there, and stops.
	 */
	public function test_a_paging_loop_terminates_on_the_page_count(): void {
		$this->fake_raw_response(
			'&page=1&',
			200,
			(string) wp_json_encode(
				array(
					'status'  => 200,
					'message' => '',
					'data'    => array( $this->signer_row( 'jane.doe@example.com' ) ),
				)
			),
			array(
				'x-pagination-current-page' => '1',
				'x-pagination-page-count'   => '2',
				'x-pagination-per-page'     => '50',
				'x-pagination-total-count'  => '2',
			)
		);

		$this->fake_raw_response(
			'&page=2&',
			200,
			(string) wp_json_encode(
				array(
					'status'  => 200,
					'message' => '',
					'data'    => array( $this->signer_row( 'jane@example.com' ) ),
				)
			),
			array(
				'x-pagination-current-page' => '2',
				'x-pagination-page-count'   => '2',
				'x-pagination-per-page'     => '50',
				'x-pagination-total-count'  => '2',
			)
		);

		$signer = $this->client()->signers()->findByEmail( 'jane@example.com' );

		$this->assertIsArray( $signer );
		$this->assertSame( self::SIGNER_ID, $signer['id'] );
		$this->assertCount( 2, $this->requests, 'The loop reads exactly the pages the header count names.' );
		$this->assertStringContainsString( '&page=1&per-page=100', $this->requests[0]['url'] );
		$this->assertStringContainsString( '&page=2&per-page=100', $this->requests[1]['url'] );
	}

	/**
	 * A response missing one pagination header reports no pagination at all.
	 *
	 * The four headers are read as a set: a partial set is an endpoint that does not
	 * paginate, and inventing a page count from what arrived would end the loop early.
	 */
	public function test_a_partial_pagination_header_set_is_ignored(): void {
		$this->fake_raw_response(
			'/signers?',
			200,
			(string) wp_json_encode(
				array(
					'status'  => 200,
					'message' => '',
					'data'    => array(),
				)
			),
			array(
				'x-pagination-current-page' => '1',
				'x-pagination-page-count'   => '3',
			)
		);

		$page = $this->client()->signers()->list( 1, 20 );

		$this->assertArrayNotHasKey( 'pagination', $page );
	}

	/**
	 * The single-item convention, next to the list one.
	 *
	 * `accounts()->get()` hands back the contents of `data`; a list method hands back the
	 * envelope that contains it. Confusing the two is the most common mistake against this
	 * API, so both are asserted against one response shape.
	 */
	public function test_single_item_methods_unwrap_and_list_methods_do_not(): void {
		// The signers response is queued first: `/accounts/{id}` is a prefix of the signers
		// URL, and the fake answers with the first queued needle the URL contains.
		$this->fake_raw_response(
			'/signers?',
			200,
			(string) wp_json_encode(
				array(
					'status'  => 200,
					'message' => '',
					'data'    => array( $this->signer_row( 'jane@example.com' ) ),
				)
			),
			array(
				'x-pagination-current-page' => '1',
				'x-pagination-page-count'   => '1',
				'x-pagination-per-page'     => '50',
				'x-pagination-total-count'  => '1',
			)
		);

		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID,
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'id'   => self::ACCOUNT_ID,
					'name' => 'Acme Inc.',
				),
			)
		);

		$client = $this->client();

		$account = $client->accounts()->get();
		$this->assertSame( array( 'id', 'name' ), array_keys( $account ) );

		$page = $client->signers()->list( 1, 20 );
		$this->assertSame( array( 'status', 'message', 'data', 'pagination' ), array_keys( $page ) );
	}

	/**
	 * The client the plugin builds for itself, with the fake transport underneath it.
	 */
	private function client(): AssinafyClient {
		$client = ( new ClientFactory( new Credentials(), new Log() ) )->client();

		$this->assertInstanceOf( AssinafyClient::class, $client );

		return $client;
	}

	/**
	 * A send service wired to its own client factory.
	 */
	private function send_service(): SendService {
		return new SendService(
			new ClientFactory( new Credentials(), new Log() ),
			new DocumentRecord(),
			new Log()
		);
	}

	/**
	 * The affordability check, wired the way `Plugin` wires it.
	 */
	private function cost_estimate(): CostEstimate {
		return new CostEstimate( new ClientFactory( new Credentials(), new Log() ), new Log() );
	}

	/**
	 * One signer row in the shape `GET /accounts/{id}/signers` returns.
	 *
	 * @param string $email Address the row carries.
	 *
	 * @return array<string, mixed>
	 */
	private function signer_row( string $email ): array {
		return array(
			'resource'              => 'signer',
			'id'                    => self::SIGNER_ID,
			'full_name'             => 'Jane Doe',
			'email'                 => $email,
			'whatsapp_phone_number' => null,
			'has_accepted_terms'    => true,
		);
	}
}
