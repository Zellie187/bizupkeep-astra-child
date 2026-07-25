<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E;

use BizUpKeep\Tests\E2E\Support\E2ETestCase;

/**
 * Covers My Documents' upload form end to end over real HTTP,
 * including the file itself (multipart/form-data) - the one flow in
 * this suite that a plain http_build_query() POST can't exercise.
 *
 * Starts a fresh Company Registration first (mirroring the real
 * client journey: apply -> land in PendingDocuments -> go upload
 * documents) rather than assuming a fixture workflow already exists,
 * so this test is self-contained and doesn't depend on
 * CompanyRegistrationSubmissionTest having run first.
 */
final class DocumentUploadTest extends E2ETestCase
{
    private string $fixtureFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureFilePath = tempnam(sys_get_temp_dir(), 'bizupkeep-e2e-doc-') . '.pdf';
        file_put_contents($this->fixtureFilePath, "%PDF-1.4\n% E2E test fixture document\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->fixtureFilePath);

        parent::tearDown();
    }

    public function test_uploading_id_document_stores_it_against_the_application(): void
    {
        $workflowUuid = $this->startARegistrationReadyForDocuments();

        $documentsPage = $this->http->get('/client-portal/client-portal-documents/');
        $nonce = $documentsPage->nonce('bizupkeep_upload_nonce');

        self::assertNotSame('', $nonce, 'Could not find the upload-form nonce on My Documents.');

        $response = $this->http->post(
            '/client-portal/client-portal-documents/',
            [
                'bizupkeep_upload_nonce' => $nonce,
                'workflow_uuid' => $workflowUuid,
                'category' => 'id_document',
            ],
            ['document' => $this->fixtureFilePath]
        );

        self::assertStringContainsString(
            'uploaded=1',
            $response->finalUrl,
            'Expected a redirect to ?uploaded=1; got: ' . $response->finalUrl
        );

        $workflow = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_workflow_instances') . ' WHERE uuid = ?',
            [$workflowUuid]
        );

        self::assertNotNull($workflow);

        $document = $this->db->fetchOne(
            'SELECT d.* FROM ' . $this->db->table('bizhub_documents') . ' d
             WHERE d.owner_type = ? AND d.owner_uuid = ? AND d.category = ?
             ORDER BY d.created_at DESC LIMIT 1',
            ['company', $workflow['subject_uuid'], 'id_document']
        );

        self::assertNotNull($document, 'No id_document row was created for this application\'s company.');

        $version = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_document_versions') . '
             WHERE document_uuid = ? ORDER BY version_number DESC LIMIT 1',
            [$document['uuid']]
        );

        self::assertNotNull($version, 'The uploaded document has no stored version.');
        self::assertGreaterThan(0, (int) $version['file_size']);
    }

    /**
     * Submit a fresh registration and return its workflow UUID - it
     * lands straight at AwaitingPayment now (see
     * bizupkeep_child_advance_to_awaiting_payment()), but document
     * upload stays available regardless of status
     * (BIZUPKEEP_DOCUMENT_UPLOAD_STATUSES), so this is still ready for
     * an upload immediately either way.
     */
    private function startARegistrationReadyForDocuments(): string
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $nonce,
            'application_type' => 'new_registration',
            'proposed_name' => ['E2E Upload Fixture ' . uniqid() . ' (Pty) Ltd'],
            'company_address' => [
                'address_line_1' => '1 E2E Test Street',
                'city' => 'Cape Town',
                'postal_code' => '8001',
            ],
            'director' => [
                ['first_name' => 'E2E', 'last_name' => 'Tester', 'id_number' => '9001015800086'],
            ],
        ]);

        $company = $this->db->latestCompanyForClient($this->clientId);
        self::assertNotNull($company, 'Fixture registration did not create a company.');

        $workflow = $this->db->latestWorkflowForCompany('company_registration', $company['uuid']);
        self::assertNotNull($workflow, 'Fixture registration did not create a workflow.');
        self::assertSame('awaiting_payment', $workflow['status']);

        return $workflow['uuid'];
    }
}
