<?php
/**
 * Document post type tests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use WP_Post_Type;

/**
 * The registration arguments are the post type's security posture, not a style choice: a
 * mirror that is publicly queryable, exposed over REST, or left on `capability_type => 'post'`
 * leaks signer names and signing URLs to anyone who can guess a URL or hold `edit_posts`.
 *
 * @covers \Assinafy\WP\Documents\DocumentPostType
 */
final class DocumentPostTypeTest extends AssinafyTestCase {

	/**
	 * Remote document id used by the fixtures.
	 */
	private const DOCUMENT_ID = '104618b275d321f5de22240ebfda';

	/**
	 * The eleven codes of `GET /documents/statuses` mapped to the labels the list table shows.
	 *
	 * @var array<string, string>
	 */
	private const LABELS = array(
		'uploading'           => 'Uploading',
		'uploaded'            => 'Uploaded',
		'metadata_processing' => 'Processing',
		'metadata_ready'      => 'Ready to send',
		'pending_signature'   => 'Awaiting signatures',
		'certificating'       => 'All signed — certifying',
		'certificated'        => 'Signed and certified',
		'rejected_by_signer'  => 'Declined by a signer',
		'rejected_by_user'    => 'Cancelled',
		'expired'             => 'Expired',
		'failed'              => 'Failed',
	);

	/**
	 * Post type under test.
	 */
	private DocumentPostType $post_type;

	/**
	 * Local mirror writer.
	 */
	private DocumentRecord $records;

	/**
	 * Build the object the plugin builds.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->records   = new DocumentRecord();
		$this->post_type = new DocumentPostType( $this->records );
	}

	/**
	 * The plugin registers the type at `init`, so it is present without the test doing it.
	 */
	public function test_the_post_type_is_registered(): void {
		$this->assertInstanceOf( WP_Post_Type::class, get_post_type_object( DocumentPostType::POST_TYPE ) );
	}

	/**
	 * A document mirror has no front-end representation. Every flag that could give it one is
	 * off, so a signer's name and signing URL can never be reached by URL, by search, or by
	 * the REST API.
	 */
	public function test_the_mirror_is_invisible_to_the_front_end(): void {
		$object = get_post_type_object( DocumentPostType::POST_TYPE );

		$this->assertInstanceOf( WP_Post_Type::class, $object );

		$this->assertFalse( $object->public );
		$this->assertFalse( $object->publicly_queryable );
		$this->assertTrue( $object->exclude_from_search );
		$this->assertFalse( $object->show_in_rest );
		$this->assertFalse( $object->has_archive );
		$this->assertFalse( $object->rewrite );
		$this->assertFalse( $object->query_var );
		$this->assertFalse( $object->show_in_nav_menus );
	}

	/**
	 * The type carries its own capability names with `map_meta_cap` on. Left at
	 * `capability_type => 'post'` every check would collapse back onto `edit_posts` and the
	 * plugin's own capabilities would gate nothing.
	 */
	public function test_the_type_gates_on_its_own_capabilities(): void {
		$object = get_post_type_object( DocumentPostType::POST_TYPE );

		$this->assertInstanceOf( WP_Post_Type::class, $object );

		$this->assertTrue( $object->map_meta_cap );

		$this->assertSame( 'edit_assinafy_documents', $object->cap->edit_posts );
		$this->assertSame( 'edit_others_assinafy_documents', $object->cap->edit_others_posts );
		$this->assertSame( 'delete_assinafy_documents', $object->cap->delete_posts );
		$this->assertSame( 'delete_others_assinafy_documents', $object->cap->delete_others_posts );
		$this->assertSame( 'read_private_assinafy_documents', $object->cap->read_private_posts );

		$this->assertNotSame( 'edit_posts', $object->cap->edit_posts );
	}

	/**
	 * A record only means something alongside a remote document, so there is no way to create
	 * an empty one from the admin. `do_not_allow` is granted to nobody, administrators
	 * included.
	 */
	public function test_no_one_may_create_a_record_by_hand(): void {
		$object = get_post_type_object( DocumentPostType::POST_TYPE );

		$this->assertInstanceOf( WP_Post_Type::class, $object );
		$this->assertSame( 'do_not_allow', $object->cap->create_posts );

		$administrator = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $administrator, $object->cap->create_posts ) );
	}

	/**
	 * Only the title is editable. Nothing else about a mirror is the site's to change: the
	 * remote document is the record of truth.
	 */
	public function test_the_mirror_supports_only_a_title(): void {
		$this->assertTrue( post_type_supports( DocumentPostType::POST_TYPE, 'title' ) );
		$this->assertFalse( post_type_supports( DocumentPostType::POST_TYPE, 'editor' ) );
		$this->assertFalse( post_type_supports( DocumentPostType::POST_TYPE, 'custom-fields' ) );
		$this->assertFalse( post_type_supports( DocumentPostType::POST_TYPE, 'comments' ) );
	}

	/**
	 * The list table drops the generic date column for the four that say something about a
	 * signature request.
	 */
	public function test_the_list_columns_replace_the_date_with_signature_state(): void {
		$columns = $this->post_type->columns(
			array(
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame(
			array( 'cb', 'title', 'assinafy_status', 'assinafy_signers', 'assinafy_sent', 'assinafy_actions' ),
			array_keys( $columns )
		);

		$this->assertArrayNotHasKey( 'date', $columns );
	}

	/**
	 * Registration attaches the column filter and renderer, so the list table gets them
	 * without any screen having to opt in.
	 */
	public function test_registration_attaches_the_column_hooks(): void {
		$this->post_type->register();

		$this->assertNotFalse( has_filter( 'manage_' . DocumentPostType::POST_TYPE . '_posts_columns' ) );
		$this->assertNotFalse( has_action( 'manage_' . DocumentPostType::POST_TYPE . '_posts_custom_column' ) );
	}

	/**
	 * Each of the eleven codes has a human label.
	 *
	 * @dataProvider status_labels
	 *
	 * @param string $code  Status code.
	 * @param string $label Label shown in the list table.
	 */
	public function test_each_status_code_has_a_label( string $code, string $label ): void {
		$this->assertSame( $label, DocumentPostType::status_label( $code ) );
	}

	/**
	 * The eleven codes and their labels.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function status_labels(): array {
		$cases = array();

		foreach ( self::LABELS as $code => $label ) {
			$cases[ $code ] = array( $code, $label );
		}

		return $cases;
	}

	/**
	 * There are eleven codes, and `ready` is not among them: the SDK's `STATUS_READY` has no
	 * counterpart in the API. An unmapped code is shown as it arrived rather than vanishing
	 * behind a blank badge, so a status the platform adds later is visible on the day it
	 * appears.
	 */
	public function test_an_unmapped_code_is_shown_as_it_arrived(): void {
		$this->assertCount( 11, self::LABELS );
		$this->assertArrayNotHasKey( 'ready', self::LABELS );

		$this->assertSame( 'ready', DocumentPostType::status_label( 'ready' ) );
		$this->assertSame( 'invented_status', DocumentPostType::status_label( 'invented_status' ) );
	}

	/**
	 * The status column renders the label, tagged with the raw code so a stylesheet can
	 * colour it.
	 */
	public function test_the_status_column_renders_the_label(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'pending_signature' );

		$output = $this->render( 'assinafy_status', $post_id );

		$this->assertStringContainsString( 'assinafy-status--pending_signature', $output );
		$this->assertStringContainsString( 'Awaiting signatures', $output );
	}

	/**
	 * A record that has never synced says so rather than rendering an empty badge.
	 */
	public function test_the_status_column_marks_a_record_that_never_synced(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, '' );

		$this->assertStringContainsString( 'Not synced', $this->render( 'assinafy_status', $post_id ) );
	}

	/**
	 * A status code is API output, and API output reaches the column as-is. Both the class
	 * attribute and the label go through escaping, so a code carrying markup renders as text.
	 */
	public function test_a_status_code_carrying_markup_is_escaped(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, '<script>alert(1)</script>' );

		$output = $this->render( 'assinafy_status', $post_id );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
		$this->assertStringContainsString( 'assinafy-status', $output );
	}

	/**
	 * A code carrying an attribute break cannot escape the class attribute it is written
	 * into.
	 */
	public function test_a_status_code_cannot_break_out_of_the_class_attribute(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, '" onmouseover="alert(1)' );

		$output = $this->render( 'assinafy_status', $post_id );

		$this->assertStringNotContainsString( 'onmouseover="', $output );
	}

	/**
	 * A sync failure is shown against the record it belongs to, escaped: the message is
	 * whatever the API or the transport said.
	 */
	public function test_a_sync_error_is_rendered_escaped(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		$this->records->set_last_error( $post_id, '<script>alert(1)</script>' );

		$output = $this->render( 'assinafy_status', $post_id );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
		$this->assertStringContainsString( 'assinafy-sync-error', $output );
	}

	/**
	 * The signer column counts progress against the embedded assignment.
	 */
	public function test_the_signer_column_counts_completed_signers(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		$this->records->set_signers(
			$post_id,
			array(
				array(
					'id'          => '19e6b92e7895332ed9708535d8c',
					'name'        => 'Jane Doe',
					'email'       => 'jane@example.com',
					'step'        => 1,
					'notified'    => true,
					'completed'   => true,
					'signing_url' => '',
				),
				array(
					'id'          => '19e6b92e7895332ed9708535d8d',
					'name'        => 'John Roe',
					'email'       => 'john@example.com',
					'step'        => 2,
					'notified'    => false,
					'completed'   => false,
					'signing_url' => '',
				),
			)
		);

		$this->assertSame( '1 of 2 signed', $this->render( 'assinafy_signers', $post_id ) );
	}

	/**
	 * A document with no assignment yet says so, rather than claiming nobody has signed.
	 */
	public function test_the_signer_column_reports_a_document_with_no_assignment(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		$this->assertSame( 'No assignment yet', $this->render( 'assinafy_signers', $post_id ) );
	}

	/**
	 * The action column links only the artifacts the record actually carries, and never
	 * `thumbnail`: it is a key in the `artifacts` map but is not a name the download route
	 * accepts.
	 */
	public function test_the_action_column_links_downloadable_artifacts_only(): void {
		$post_id = $this->create_document(
			self::DOCUMENT_ID,
			'certificated',
			array( 'original', 'thumbnail', 'certificated', 'certificate-page' )
		);

		$output = $this->render( 'assinafy_actions', $post_id );

		$this->assertStringContainsString( '>Original</a>', $output );
		$this->assertStringContainsString( '>Signed</a>', $output );
		$this->assertStringContainsString( '>Certificate</a>', $output );
		$this->assertStringNotContainsString( 'thumbnail', $output );
	}

	/**
	 * A record with nothing downloadable renders a dash, not an empty cell.
	 */
	public function test_the_action_column_renders_a_dash_with_no_artifacts(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID, 'uploading', array() );

		$output = $this->render( 'assinafy_actions', $post_id );

		$this->assertStringNotContainsString( '<a ', $output );
		$this->assertNotSame( '', $output );
	}

	/**
	 * A column key the plugin does not own renders nothing at all.
	 */
	public function test_an_unknown_column_renders_nothing(): void {
		$post_id = $this->create_document( self::DOCUMENT_ID );

		$this->assertSame( '', $this->render( 'title', $post_id ) );
	}

	/**
	 * Capture what one column prints.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Local post id.
	 */
	private function render( string $column, int $post_id ): string {
		ob_start();

		$this->post_type->render_column( $column, $post_id );

		return trim( (string) ob_get_clean() );
	}
}
