<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E;

use BizUpKeep\Tests\E2E\Support\E2ETestCase;

/**
 * Covers /apply/'s "New Company Registration" section end to end over
 * real HTTP: submitting the form should create a Company row (with
 * the PENDING-{uuid} placeholder registration number every new
 * registration starts with) and a company_registration workflow
 * instance already advanced to PendingDocuments - the
 * request_documents transition bizupkeep_child_submit_new_registration()
 * fires automatically at submission.
 */
final class CompanyRegistrationSubmissionTest extends E2ETestCase
{
    public function test_submitting_creates_a_company_and_starts_the_workflow(): void
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        self::assertNotSame('', $nonce, 'Could not find the apply-form nonce on a logged-in GET /apply/.');

        $companyName = 'E2E Registration Test ' . uniqid() . ' (Pty) Ltd';

        $response = $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $nonce,
            'application_type' => 'new_registration',
            'proposed_name' => [$companyName],
            'company_address' => [
                'address_line_1' => '1 E2E Test Street',
                'city' => 'Cape Town',
                'province' => 'Western Cape',
                'postal_code' => '8001',
            ],
            'director' => [
                ['first_name' => 'E2E', 'last_name' => 'Tester', 'id_number' => '9001015800086'],
            ],
            'notes' => 'Created by the automated E2E suite.',
        ]);

        self::assertStringContainsString(
            'submitted=1',
            $response->finalUrl,
            'Expected a redirect to ?submitted=1; got: ' . $response->finalUrl
        );

        $company = $this->db->latestCompanyForClient($this->clientId);

        self::assertNotNull($company, 'No company row was created for this client.');
        self::assertSame($companyName, $company['company_name']);
        self::assertStringStartsWith('PENDING-', $company['registration_number']);

        $workflow = $this->db->latestWorkflowForCompany('company_registration', $company['uuid']);

        self::assertNotNull($workflow, 'No company_registration workflow instance was created.');
        self::assertSame('pending_documents', $workflow['status']);

        $metadata = json_decode((string) $workflow['metadata'], true);

        self::assertSame([$companyName], $metadata['proposed_names'] ?? null);
        self::assertSame('Created by the automated E2E suite.', $metadata['client_notes'] ?? null);
    }

    public function test_submitting_without_a_proposed_name_does_not_create_anything(): void
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        $companiesBefore = $this->db->fetchAll(
            'SELECT id FROM ' . $this->db->table('bizhub_companies') . ' WHERE client_id = ?',
            [$this->clientId]
        );

        $response = $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $nonce,
            'application_type' => 'new_registration',
            'proposed_name' => [''],
            'company_address' => [
                'address_line_1' => '1 E2E Test Street',
                'city' => 'Cape Town',
                'postal_code' => '8001',
            ],
            'director' => [
                ['first_name' => 'E2E', 'last_name' => 'Tester', 'id_number' => '9001015800086'],
            ],
        ]);

        self::assertStringContainsString('apply_error=1', $response->finalUrl);

        $companiesAfter = $this->db->fetchAll(
            'SELECT id FROM ' . $this->db->table('bizhub_companies') . ' WHERE client_id = ?',
            [$this->clientId]
        );

        self::assertCount(count($companiesBefore), $companiesAfter, 'A company was created despite invalid input.');
    }
}
