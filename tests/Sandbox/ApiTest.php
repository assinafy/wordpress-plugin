<?php
/**
 * Explicit live checks using WordPress's real HTTP transport.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Sandbox;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Configuration;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Resources\DocumentResource;
use Assinafy\WP\Http\WpHttpClient;
use Assinafy\WP\Admin\ContractForm;
use Assinafy\WP\Webhook\Route;

/** Creates disposable, unassigned PDFs and terms; no invitations or workspace settings changes. */
final class ApiTest extends \WP_UnitTestCase {

	/** Test real authentication, multipart upload, retrieval, pricing and binary download. */
	public function test_document_transport_and_verification_estimates(): void {
		$client  = $this->client();
		$account = (string) getenv( 'ASSINAFY_ACCOUNT_ID' );
		$this->assertSame( $account, $client->accounts()->get()['id'] );
		$this->assertNotEmpty( $client->documents()->statuses() );
		$events = array_filter( array_column( $client->webhooks()->eventTypes(), 'id' ), static fn( string $event ): bool => ! str_starts_with( $event, 'template_' ) );
		$this->assertEmpty( array_diff( $events, Route::EVENTS ), 'Every deployed document and signer event must be understood by the receiver.' );
		$this->assertIsArray( $client->accounts()->usage() );
		$this->assertIsArray( $client->accounts()->notificationPolicy() );
		$this->assertIsArray( $client->accounts()->embedOrigins() );
		$this->assertArrayHasKey( 'data', $client->envelopes()->list() );
		$document = $client->documents()->upload( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
		$id       = (string) $document['id'];
		$this->assertNotSame( '', $id );
		$refused = array();
		try {
			$this->assertSame( $id, $client->documents()->get( $id )['id'] );
			$this->assertStringStartsWith( '%PDF-', $client->documents()->download( $id, DocumentResource::ARTIFACT_ORIGINAL ) );
			foreach ( array( 'Email', 'Whatsapp', 'DigitalCertificate', 'IdDocumentFaceMatchBasic' ) as $method ) {
				$channels = in_array( $method, array( 'Email', 'Whatsapp' ), true ) ? array( $method ) : array( 'Email', 'Whatsapp' );
				foreach ( $channels as $channel ) {
					try {
						$estimate = $client->assignments()->estimateCost(
							$id,
							array(
								array(
									'verification_method'  => $method,
									'notification_methods' => array( $channel ),
								),
							)
						);
						$this->assertArrayHasKey( 'breakdown', $estimate, $method );
					} catch ( ApiException $error ) {
						$refused[] = $method . '/' . $channel . ': HTTP ' . $error->getStatusCode() . ' ' . $error->getMessage();
					}
				}
			}
		} finally {
			// Only the unassigned document created above is disposable.
			$client->documents()->delete( $id );
		}
		$this->assertSame( array(), $refused, 'All verification features must be enabled on the test account.' );
	}

	/** Exercise contract revisions and lifecycle only on PDFs created by this test. */
	public function test_disposable_contract_lifecycle(): void {
		$client = $this->client();
		$ids    = array();
		try {
			foreach ( range( 1, 2 ) as $index ) {
				$document = $client->documents()->upload( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
				$ids[]    = (string) $document['id'];
			}
			$contracts = $client->contracts();
			$terms     = $contracts->update(
				$ids[0],
				array(
					'status'              => 'active',
					'counterparty_name'   => 'Disposable integration fixture',
					'end_date'            => gmdate( 'Y-m-d', strtotime( '+2 years' ) ),
					'auto_renew'          => false,
					'renewal_term_months' => 12,
					'notice_period_days'  => 0,
					'value'               => 1,
				)
			);
			ContractForm::assert_contract( $terms, $ids[0] );
			$this->assertSame( $ids[0], $terms['document_id'] );
			$this->assertSame( 'document_contract', $contracts->get( $ids[0] )['resource'] );
			$this->assertArrayHasKey( 'data', $contracts->list() );
			$link = $contracts->update(
				$ids[1],
				array(
					'parent_document_id' => $ids[0],
					'relation_type'      => 'amendment',
					'value'              => 2,
				)
			);
			ContractForm::assert_contract( $link, $ids[1] );
			$this->assertSame( 'pending', $link['status'] );
			$this->assertSame( $ids[0], $contracts->related( $ids[1] )['document_id'] );
			$unlinked = $contracts->update(
				$ids[1],
				array(
					'parent_document_id' => null,
					'updated_at'         => $link['updated_at'],
				)
			);
			ContractForm::assert_contract( $unlinked, $ids[1] );
			$this->assertSame( $ids[1], $unlinked['document_id'] );
			$this->assertCount( 0, $contracts->related( $ids[0] )['amendments'] );
			try {
				$contracts->update(
					$ids[0],
					array(
						'value'      => 3,
						'updated_at' => '2000-01-01T00:00:00Z',
					)
				);
				$this->fail( 'A stale contract revision must be rejected.' );
			} catch ( ApiException $error ) {
				$this->assertSame( 409, $error->getStatusCode() );
			}
			$terms   = $contracts->get( $ids[0] );
			$renewed = $contracts->renew(
				$ids[0],
				array(
					'term'       => $terms['term'],
					'updated_at' => $terms['updated_at'],
				)
			);
			ContractForm::assert_contract( $renewed, $ids[0] );
			$this->assertSame( $terms['term'] + 1, $renewed['term'] );
			$ended = $contracts->terminate(
				$ids[0],
				array(
					'reason'     => 'Disposable integration fixture',
					'updated_at' => $renewed['updated_at'],
				)
			);
			ContractForm::assert_contract( $ended, $ids[0] );
			$this->assertSame( 'terminated', $ended['status'] );
		} finally {
			foreach ( array_reverse( $ids ) as $id ) {
				$client->documents()->delete( $id );
			}
		}
	}

	/** Production writes require an additional explicit opt-in. */
	private function client(): AssinafyClient {
		$key     = (string) getenv( 'ASSINAFY_API_KEY' );
		$account = (string) getenv( 'ASSINAFY_ACCOUNT_ID' );
		$this->assertNotSame( '', $key, 'ASSINAFY_API_KEY is required.' );
		$this->assertNotSame( '', $account, 'ASSINAFY_ACCOUNT_ID is required.' );
		$base   = '1' === getenv( 'ASSINAFY_LIVE_PRODUCTION' ) ? Configuration::DEFAULT_BASE_URL : Configuration::SANDBOX_BASE_URL;
		$config = new Configuration( $key, $account, $base );
		return new AssinafyClient( $config, new WpHttpClient( $config ) );
	}
}
