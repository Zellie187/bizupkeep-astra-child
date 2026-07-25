<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E;

use BizUpKeep\Tests\E2E\Support\E2ETestCase;

/**
 * Covers /apply/'s "Company Amendment" section end to end over real
 * HTTP, using the "company not registered with us" path
 * (amendment_company_mode=new) since it's self-contained - no
 * pre-existing company fixture needed. Submits an address-only
 * change: the simplest of the three combinable amendment types, and
 * enough to prove the amendment_types metadata and the resulting
 * workflow status are both correct.
 */
final class CompanyAmendmentSubmissionTest extends E2ETestCase
{
    public function test_submitting_an_address_change_creates_the_workflow(): void
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        self::assertNotSame('', $nonce);

        $regNumber = 'E2E-' . uniqid();
        $companyName = 'E2E Amendment Test ' . uniqid() . ' (Pty) Ltd';

        $response = $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $nonce,
            'application_type' => 'company_amendment',
            'amendment_company_mode' => 'new',
            'amendment_new_company' => [
                'company_name' => $companyName,
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
            'notes' => 'Created by the automated E2E suite.',
        ]);

        self::assertStringContainsString(
            'submitted=1',
            $response->finalUrl,
            'Expected a redirect to ?submitted=1; got: ' . $response->finalUrl
        );

        $company = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_companies') . ' WHERE registration_number = ?',
            [$regNumber]
        );

        self::assertNotNull($company, 'No external company row was created with the submitted registration number.');
        self::assertSame($companyName, $company['company_name']);
        self::assertSame('active', $company['status']);

        $workflow = $this->db->latestWorkflowForCompany('company_amendment', $company['uuid']);

        self::assertNotNull($workflow, 'No company_amendment workflow instance was created.');
        self::assertSame(
            'awaiting_payment',
            $workflow['status'],
            'Submitting should now land straight at AwaitingPayment - payment no longer waits on documents being uploaded first.'
        );

        $metadata = json_decode((string) $workflow['metadata'], true);

        self::assertSame(['address'], $metadata['amendment_types'] ?? null);
        self::assertSame('2 New Address Avenue', $metadata['new_address']['address_line_1'] ?? null);

        $director = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_directors') . ' WHERE company_uuid = ?',
            [$company['uuid']]
        );

        self::assertNotNull($director, 'No director row was created for the new company - required since the Power of Attorney needs someone to sign it.');
        self::assertSame('E2E', $director['first_name']);
    }
}
