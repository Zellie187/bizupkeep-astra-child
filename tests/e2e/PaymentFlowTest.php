<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E;

use BizUpKeep\Tests\E2E\Support\E2ETestCase;

/**
 * Covers the Company Amendment "Pay Now" flow end to end over real
 * HTTP: submit an amendment (which now lands straight at
 * AwaitingPayment - see bizupkeep_child_advance_to_awaiting_payment()),
 * upload all four required documents anyway to prove that still works
 * even though it's no longer what unlocks payment
 * (BIZUPKEEP_DOCUMENT_UPLOAD_STATUSES), then follow the payment link
 * and confirm it lands on a real WooCommerce checkout with the ONE
 * product matching this amendment's exact amendment_types - the fix
 * for the pricing gap the user asked about directly (a client
 * bundling several change types could otherwise pick any product,
 * with no connection to what was actually requested).
 */
final class PaymentFlowTest extends E2ETestCase
{
    private string $idDocumentPath;

    private string $signedPoaPath;

    private string $signedResolutionPath;

    private string $signedMinutesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idDocumentPath = tempnam(sys_get_temp_dir(), 'bizupkeep-e2e-id-') . '.pdf';
        $this->signedPoaPath = tempnam(sys_get_temp_dir(), 'bizupkeep-e2e-poa-') . '.pdf';
        $this->signedResolutionPath = tempnam(sys_get_temp_dir(), 'bizupkeep-e2e-resolution-') . '.pdf';
        $this->signedMinutesPath = tempnam(sys_get_temp_dir(), 'bizupkeep-e2e-minutes-') . '.pdf';
        file_put_contents($this->idDocumentPath, "%PDF-1.4\n% E2E ID document fixture\n");
        file_put_contents($this->signedPoaPath, "%PDF-1.4\n% E2E signed POA fixture\n");
        file_put_contents($this->signedResolutionPath, "%PDF-1.4\n% E2E signed resolution fixture\n");
        file_put_contents($this->signedMinutesPath, "%PDF-1.4\n% E2E signed minutes fixture\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->idDocumentPath);
        @unlink($this->signedPoaPath);
        @unlink($this->signedResolutionPath);
        @unlink($this->signedMinutesPath);

        parent::tearDown();
    }

    public function test_paying_for_an_address_only_amendment_routes_to_the_matching_product(): void
    {
        $workflowUuid = $this->startAnAddressOnlyAmendmentAwaitingPayment();

        $response = $this->http->get('/?bizupkeep_pay_amendment=' . $workflowUuid);

        self::assertStringContainsString(
            '/checkout/',
            $response->finalUrl,
            'Expected the payment link to land on checkout; got: ' . $response->finalUrl
        );
        self::assertStringContainsString(
            'Address Change',
            $response->body,
            'Checkout does not show the product matching this amendment\'s exact change type.'
        );

        $product = $this->db->fetchOne(
            "SELECT ID FROM " . $this->db->table('posts') . "
             WHERE post_type = 'product' AND post_name = 'address-change'"
        );

        self::assertNotNull($product, 'The real "address-change" product does not exist on this environment - see BIZUPKEEP_AMENDMENT_PRODUCT_SLUGS.');
    }

    /**
     * Submit an address-only amendment (already AwaitingPayment on its
     * own by the time this returns) and upload all four required
     * documents anyway, mirroring a real client journey that submits
     * documents alongside paying rather than instead of it. Returns
     * the workflow UUID.
     */
    private function startAnAddressOnlyAmendmentAwaitingPayment(): string
    {
        $applyPage = $this->http->get('/apply/');
        $applyNonce = $applyPage->nonce('bizupkeep_apply_nonce');

        $regNumber = 'E2E-' . uniqid();

        $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $applyNonce,
            'application_type' => 'company_amendment',
            'amendment_company_mode' => 'new',
            'amendment_new_company' => [
                'company_name' => 'E2E Payment Fixture ' . uniqid() . ' (Pty) Ltd',
                'registration_number' => $regNumber,
                'address' => [
                    'address_line_1' => '1 Original Address Street',
                    'city' => 'Cape Town',
                    'postal_code' => '8001',
                ],
                'director' => [
                    ['first_name' => 'E2E', 'last_name' => 'Tester', 'id_number' => '9001015800086'],
                ],
            ],
            'amendment_types' => ['address'],
            'amendment_address' => [
                'address_line_1' => '2 New Address Avenue',
                'city' => 'Johannesburg',
                'province' => 'Gauteng',
                'postal_code' => '2000',
            ],
        ]);

        $company = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_companies') . ' WHERE registration_number = ?',
            [$regNumber]
        );
        self::assertNotNull($company, 'Fixture amendment did not create a company.');

        $workflow = $this->db->latestWorkflowForCompany('company_amendment', $company['uuid']);
        self::assertNotNull($workflow, 'Fixture amendment did not create a workflow.');
        self::assertSame(
            'awaiting_payment',
            $workflow['status'],
            'Submitting should now land straight at AwaitingPayment - payment no longer waits on documents being uploaded first.'
        );

        $documentsPage = $this->http->get('/client-portal/client-portal-documents/');
        $uploadNonce = $documentsPage->nonce('bizupkeep_upload_nonce');

        $this->http->post(
            '/client-portal/client-portal-documents/',
            [
                'bizupkeep_upload_nonce' => $uploadNonce,
                'workflow_uuid' => $workflow['uuid'],
                'category' => 'id_document',
            ],
            ['document' => $this->idDocumentPath]
        );

        $documentsPage = $this->http->get('/client-portal/client-portal-documents/');
        $uploadNonce = $documentsPage->nonce('bizupkeep_upload_nonce');

        $this->http->post(
            '/client-portal/client-portal-documents/',
            [
                'bizupkeep_upload_nonce' => $uploadNonce,
                'workflow_uuid' => $workflow['uuid'],
                'category' => 'signed_poa',
            ],
            ['document' => $this->signedPoaPath]
        );

        $documentsPage = $this->http->get('/client-portal/client-portal-documents/');
        $uploadNonce = $documentsPage->nonce('bizupkeep_upload_nonce');

        $this->http->post(
            '/client-portal/client-portal-documents/',
            [
                'bizupkeep_upload_nonce' => $uploadNonce,
                'workflow_uuid' => $workflow['uuid'],
                'category' => 'signed_resolution',
            ],
            ['document' => $this->signedResolutionPath]
        );

        $documentsPage = $this->http->get('/client-portal/client-portal-documents/');
        $uploadNonce = $documentsPage->nonce('bizupkeep_upload_nonce');

        $this->http->post(
            '/client-portal/client-portal-documents/',
            [
                'bizupkeep_upload_nonce' => $uploadNonce,
                'workflow_uuid' => $workflow['uuid'],
                'category' => 'signed_minutes',
            ],
            ['document' => $this->signedMinutesPath]
        );

        $updated = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_workflow_instances') . ' WHERE uuid = ?',
            [$workflow['uuid']]
        );
        self::assertNotNull($updated);
        self::assertSame(
            'awaiting_payment',
            $updated['status'],
            'Uploading documents should not have moved the workflow off AwaitingPayment (it landed there at submission already).'
        );

        return $workflow['uuid'];
    }
}
