<?php
/**
 * The local mirror of a remote document.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\DocumentRecord;
use PHPUnit\Framework\TestCase;

/**
 * Document, assignment and signer ids are opaque variable-length hex: 26, 27 and 28 characters
 * have all been observed inside one workspace, so a fixed-length pattern rejects real ids. The
 * other hazard here is JSON shape — an empty object decodes to an empty array, so `artifacts`,
 * `assignment` and `signers` arrive as either and every read has to cope with both.
 *
 * @covers \Assinafy\WP\Documents\DocumentRecord
 * @covers \Assinafy\WP\Documents\DocumentPostType::status_label
 */
final class DocumentRecordTest extends TestCase {

	private DocumentRecord $records;

	/**
	 * The queries that locate records, as opposed to reading one.
	 */
	private DocumentIndex $index;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['assinafy_test_meta']  = array();
		$GLOBALS['assinafy_test_posts'] = array();

		$this->records = new DocumentRecord();
		$this->index   = new DocumentIndex();
	}

	/** An upload without an assignment retains retry identity, including prior erasure. */
	public function test_pending_signers_survive_unassigned_upload(): void {
		$this->seed_post( 41 );
		$signers = array(
			array(
				'id'          => '',
				'name'        => '[redacted-token]',
				'email'       => '[redacted-token]',
				'step'        => 1,
				'notified'    => false,
				'completed'   => false,
				'signing_url' => '',
			),
		);
		$this->records->set_signers( 41, $signers );
		$this->records->hydrate_from_api(
			41,
			array(
				'id'         => 'abc123',
				'status'     => 'uploaded',
				'assignment' => null,
			)
		);
		$this->assertSame( $signers, $this->records->signers( 41 ) );
		$this->records->hydrate_from_api(
			41,
			array(
				'id'         => 'abc123',
				'assignment' => array(
					'id'      => 'def456',
					'signers' => array(),
				),
			)
		);
		$this->assertSame( array(), $this->records->signers( 41 ) );
	}

	/** An assignment arriving after pending erasure cannot restore the contact details. */
	public function test_erased_email_survives_new_remote_signer_identity(): void {
		$this->seed_post( 42 );
		$this->records->set_signers(
			42,
			array(),
			array(
				'email' => 'Jane@Example.com',
				'token' => '[redacted-pending]',
			)
		);
		$this->records->hydrate_from_api(
			42,
			array(
				'id'         => 'abc123',
				'assignment' => array(
					'id'      => 'def456',
					'signers' => array(
						array(
							'id'        => 'abcdef',
							'email'     => 'jane@example.com',
							'full_name' => 'Jane Doe',
						),
					),
				),
			)
		);
		$signer = $this->records->signers( 42 )[0];
		$this->assertSame( '[redacted-pending]', $signer['email'] );
		$this->assertSame( '[redacted-pending]', $signer['name'] );
		$this->records->hydrate_from_api(
			42,
			array(
				'id'         => 'abc123',
				'assignment' => array(
					'id'      => 'def456',
					'signers' => array(
						array(
							'id'        => 'abcdef',
							'email'     => 'changed@example.com',
							'full_name' => 'Jane Doe',
						),
					),
				),
			)
		);
		$this->assertSame( '[redacted-pending]', $this->records->signers( 42 )[0]['email'] );
	}

	/**
	 * Seed a local post of the plugin's own type.
	 *
	 * @param int $post_id Post id.
	 */
	private function seed_post( int $post_id ): void {
		$GLOBALS['assinafy_test_posts'][ $post_id ] = array(
			'ID'         => $post_id,
			'post_type'  => DocumentPostType::POST_TYPE,
			'post_title' => 'Untitled',
		);
	}

	/**
	 * Ids of every length the sandbox has produced.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function document_ids(): array {
		return array(
			'26 characters' => array( '104618b275d321f5de22240ebf' ),
			'27 characters' => array( '104618b275d321f5de22240ebfd' ),
			'28 characters' => array( '104618b275d321f5de22240ebfda' ),
		);
	}

	/**
	 * @dataProvider document_ids
	 *
	 * @param string $document_id Remote document id.
	 */
	public function test_a_document_id_round_trips_whatever_its_length( string $document_id ): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'     => $document_id,
				'status' => 'pending_signature',
			)
		);

		$this->assertTrue( DocumentRecord::is_valid_id( $document_id ) );
		$this->assertSame( $document_id, $this->records->document_id( 11 ) );
		$this->assertSame( 11, $this->index->find_by_document_id( $document_id ) );
		$this->assertSame( $document_id, $this->index->latest_document_id() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function invalid_ids(): array {
		return array(
			'empty'          => array( '' ),
			'not hex'        => array( 'not-a-document-id' ),
			'path traversal' => array( '../accounts' ),
			'slash'          => array( '104618b2/download' ),
			'space'          => array( '104618b2 75d321f5' ),
			'absurdly long'  => array( str_repeat( 'a', 65 ) ),
		);
	}

	/**
	 * The id becomes a path segment, so a malformed one is refused before it can be
	 * concatenated into a request URI.
	 *
	 * @dataProvider invalid_ids
	 *
	 * @param string $document_id Candidate id.
	 */
	public function test_a_malformed_id_is_refused( string $document_id ): void {
		$this->assertFalse( DocumentRecord::is_valid_id( $document_id ) );
		$this->assertSame( 0, $this->index->find_by_document_id( $document_id ) );
	}

	public function test_a_64_character_id_is_still_accepted(): void {
		$this->assertTrue( DocumentRecord::is_valid_id( str_repeat( 'a', 64 ) ) );
	}

	public function test_an_unknown_document_id_resolves_to_zero(): void {
		$this->seed_post( 11 );
		$this->records->hydrate_from_api( 11, array( 'id' => '104618b275d321f5de22240ebfda' ) );

		$this->assertSame( 0, $this->index->find_by_document_id( '1a09c15990f0144256b98ff38aa' ) );
	}

	/**
	 * Every artifact URL needs the account API key to fetch, so only the names are stored —
	 * a URL that never reaches the database can never be rendered into a page by mistake.
	 */
	public function test_only_artifact_names_are_stored(): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'        => '104618b275d321f5de22240ebfda',
				'status'    => 'certificated',
				'artifacts' => array(
					'original'         => 'https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/download/original',
					'thumbnail'        => 'https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/thumbnail',
					'certificated'     => 'https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/download/certificated',
					'certificate-page' => 'https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/download/certificate-page',
					'bundle'           => 'https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/download/bundle',
				),
			)
		);

		$artifacts = $this->records->artifacts( 11 );

		$this->assertSame(
			array( 'original', 'thumbnail', 'certificated', 'certificate-page', 'bundle' ),
			$artifacts
		);
		$this->assertStringNotContainsString( 'https://', (string) wp_json_encode( $artifacts ) );
	}

	/**
	 * An empty JSON object decodes to an empty PHP array, so `artifacts`, `assignment` and
	 * `signers` all arrive as either an array or a map depending on how full they are. Both
	 * shapes have to land without a notice and without inventing data.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: int, 2: string}>
	 */
	public static function empty_and_populated_shapes(): array {
		return array(
			'artifacts as an empty JSON object'  => array(
				array(
					'id'         => '104618b275d321f5de22240ebfda',
					'status'     => 'uploading',
					'artifacts'  => array(),
					'assignment' => null,
				),
				0,
				'',
			),
			'assignment as an empty JSON object' => array(
				array(
					'id'         => '104618b275d321f5de22240ebfda',
					'status'     => 'metadata_ready',
					'assignment' => array(),
				),
				0,
				'',
			),
			'assignment absent entirely'         => array(
				array(
					'id'     => '104618b275d321f5de22240ebfda',
					'status' => 'uploaded',
				),
				0,
				'',
			),
			'assignment populated'               => array(
				array(
					'id'         => '104618b275d321f5de22240ebfda',
					'status'     => 'pending_signature',
					'assignment' => array(
						'id'      => '1a09c15990f0144256b98ff38aa',
						'signers' => array(
							array(
								'id'        => '19e6b92e7895332ed9708535d8c',
								'full_name' => 'Jane Doe',
								'email'     => 'jane@example.com',
								'step'      => 1,
								'notified'  => true,
								'completed' => false,
							),
						),
					),
				),
				1,
				'1a09c15990f0144256b98ff38aa',
			),
		);
	}

	/**
	 * @dataProvider empty_and_populated_shapes
	 *
	 * @param array<string, mixed> $document      Unwrapped API document.
	 * @param int                  $signer_count  Signers the record should end up with.
	 * @param string               $assignment_id Assignment id the record should end up with.
	 */
	public function test_both_json_shapes_hydrate( array $document, int $signer_count, string $assignment_id ): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api( 11, $document );

		$this->assertCount( $signer_count, $this->records->signers( 11 ) );
		$this->assertSame( $assignment_id, $this->records->assignment_id( 11 ) );
		$this->assertSame( array(), $this->records->artifacts( 11 ) );
	}

	/**
	 * `signing_urls` lists only signers who have already been notified, so a signer on a later
	 * step has no URL until the step before it completes. That is "not yet invited", not an
	 * error, and the two must be told apart by `notified` rather than by an empty URL.
	 */
	public function test_signers_are_flattened_with_their_signing_urls(): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'         => '104618b275d321f5de22240ebfda',
				'status'     => 'pending_signature',
				'assignment' => array(
					'id'           => '1a09c15990f0144256b98ff38aa',
					'signers'      => array(
						array(
							'id'        => '19e6b92e7895332ed9708535d8c',
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
							'step'      => 1,
							'notified'  => true,
							'completed' => true,
						),
						array(
							'id'        => '19e6b92e7895332ed9708535d90',
							'full_name' => 'John Roe',
							'email'     => 'john@example.com',
							'step'      => 2,
							'notified'  => false,
							'completed' => false,
						),
					),
					'signing_urls' => array(
						array(
							'signer_id' => '19e6b92e7895332ed9708535d8c',
							'url'       => 'https://app-sandbox.assinafy.com.br/sign/104618d0d63884bc446c534e5ff5?email=jane%40example.com',
						),
					),
				),
			)
		);

		$signers = $this->records->signers( 11 );

		$this->assertCount( 2, $signers );

		$this->assertSame( 'Jane Doe', $signers[0]['name'] );
		$this->assertSame( 'jane@example.com', $signers[0]['email'] );
		$this->assertSame( 1, $signers[0]['step'] );
		$this->assertTrue( $signers[0]['notified'] );
		$this->assertTrue( $signers[0]['completed'] );
		$this->assertStringStartsWith( 'https://app-sandbox.assinafy.com.br/sign/', $signers[0]['signing_url'] );

		$this->assertSame( 'John Roe', $signers[1]['name'] );
		$this->assertSame( 2, $signers[1]['step'] );
		$this->assertFalse( $signers[1]['notified'] );
		$this->assertSame( '', $signers[1]['signing_url'], 'A signer on a later step has no link yet.' );
	}

	public function test_a_signer_without_a_step_defaults_to_one(): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'         => '104618b275d321f5de22240ebfda',
				'assignment' => array(
					'id'      => '1a09c15990f0144256b98ff38aa',
					'signers' => array(
						array(
							'id'        => '19e6b92e7895332ed9708535d8c',
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
						),
					),
				),
			)
		);

		$signers = $this->records->signers( 11 );

		$this->assertSame( 1, $signers[0]['step'] );
		$this->assertFalse( $signers[0]['notified'] );
		$this->assertFalse( $signers[0]['completed'] );
	}

	/**
	 * Closure is mirrored from the document's own flag rather than a hand-maintained list of
	 * terminal codes, so a status added to the platform cannot strand a record in the
	 * reconcile queue forever.
	 */
	public function test_closure_mirrors_the_documents_own_flag(): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'        => '104618b275d321f5de22240ebfda',
				'status'    => 'pending_signature',
				'is_closed' => false,
			)
		);

		$this->assertFalse( $this->records->is_closed( 11 ) );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'        => '104618b275d321f5de22240ebfda',
				'status'    => 'certificated',
				'is_closed' => true,
			)
		);

		$this->assertTrue( $this->records->is_closed( 11 ) );
	}

	/**
	 * The return value is what drives `assinafy_document_status_changed`, so it has to be the
	 * status the record held *before* the call.
	 */
	public function test_hydrate_returns_the_previous_status(): void {
		$this->seed_post( 11 );

		$this->assertSame(
			'',
			$this->records->hydrate_from_api(
				11,
				array(
					'id'     => '104618b275d321f5de22240ebfda',
					'status' => 'pending_signature',
				)
			)
		);

		$this->assertSame(
			'pending_signature',
			$this->records->hydrate_from_api(
				11,
				array(
					'id'     => '104618b275d321f5de22240ebfda',
					'status' => 'certificated',
				)
			)
		);

		$this->assertSame( 'certificated', $this->records->status( 11 ) );
	}

	/**
	 * The API folds diacritics and replaces unsupported characters at upload, so the stored
	 * title is the server's name, never the one that was sent.
	 */
	public function test_the_post_title_follows_the_servers_name(): void {
		$this->seed_post( 11 );

		$this->records->hydrate_from_api(
			11,
			array(
				'id'   => '104618b275d321f5de22240ebfda',
				'name' => 'contrato-de-prestacao.pdf',
			)
		);

		$this->assertSame( 'contrato-de-prestacao.pdf', get_post_field( 'post_title', 11 ) );
	}

	public function test_the_sync_timestamp_is_written_and_readable(): void {
		$this->seed_post( 11 );

		$this->assertSame( 0, $this->records->synced_at( 11 ) );

		$this->records->hydrate_from_api( 11, array( 'id' => '104618b275d321f5de22240ebfda' ) );

		$this->assertGreaterThan( 0, $this->records->synced_at( 11 ) );
	}

	public function test_the_last_error_is_written_and_cleared(): void {
		$this->seed_post( 11 );

		$this->assertSame( '', $this->records->last_error( 11 ) );

		$this->records->set_last_error( 11, 'Documento não encontrado.' );
		$this->assertSame( 'Documento não encontrado.', $this->records->last_error( 11 ) );

		$this->records->set_last_error( 11, '' );
		$this->assertSame( '', $this->records->last_error( 11 ) );
	}

	/**
	 * The privacy eraser rewrites the signer list in place, keeping the ids as evidence.
	 */
	public function test_the_signer_list_can_be_replaced(): void {
		$this->seed_post( 11 );

		$this->records->set_signers(
			11,
			array(
				array(
					'id'          => '19e6b92e7895332ed9708535d8c',
					'name'        => '[redacted-9f2d47a8b6c0]',
					'email'       => '[redacted-9f2d47a8b6c0]',
					'step'        => 1,
					'notified'    => true,
					'completed'   => true,
					'signing_url' => '',
				),
			)
		);

		$signers = $this->records->signers( 11 );

		$this->assertSame( '19e6b92e7895332ed9708535d8c', $signers[0]['id'] );
		$this->assertStringStartsWith( '[redacted-', $signers[0]['email'] );

		// A signer can disappear from a partial response and return without losing erasure.
		$redacted = $this->records->signers( 11 )[0]['email'];
		$this->records->hydrate_from_api( 11, array( 'id' => '104618b275d321f5de22240ebfda' ) );
		$this->assertSame( array(), $this->records->signers( 11 ) );
		$this->records->hydrate_from_api(
			11,
			array(
				'id'         => '104618b275d321f5de22240ebfda',
				'assignment' => array(
					'signers'      => array(
						array(
							'id'        => '19e6b92e7895332ed9708535d8c',
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
							'completed' => true,
						),
					),
					'signing_urls' => array(
						array(
							'signer_id' => '19e6b92e7895332ed9708535d8c',
							'url'       => 'https://example.test/sign?email=jane%40example.com',
						),
					),
				),
			)
		);

		$signer = $this->records->signers( 11 )[0];
		$this->assertSame( $redacted, $signer['email'] );
		$this->assertSame( $redacted, $signer['name'] );
		$this->assertSame( '', $signer['signing_url'] );
		$this->assertTrue( $signer['completed'] );
	}

	/**
	 * Exactly the eleven codes `GET /documents/statuses` returns. There is no `ready` code,
	 * whatever `DocumentResource::STATUS_READY` suggests, and an unrecognised code is shown
	 * as-is rather than blanked.
	 */
	public function test_every_status_code_has_a_label(): void {
		$codes = array(
			'uploading',
			'uploaded',
			'metadata_processing',
			'metadata_ready',
			'pending_signature',
			'certificating',
			'certificated',
			'rejected_by_signer',
			'rejected_by_user',
			'expired',
			'failed',
		);

		foreach ( $codes as $code ) {
			$this->assertNotSame( $code, DocumentPostType::status_label( $code ), "'{$code}' has no label." );
		}

		$this->assertSame( 'ready', DocumentPostType::status_label( 'ready' ) );
		$this->assertSame( '', DocumentPostType::status_label( '' ) );
	}
}
