<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E;

use BizUpKeep\Tests\E2E\Support\E2ETestCase;

/**
 * Covers /apply/'s "Annual Return" section end to end over real HTTP,
 * using the "company not registered with us" path
 * (return_company_mode=new) for the same reason
 * CompanyAmendmentSubmissionTest does. Unlike Registration/Amendment,
 * a fresh Annual Return workflow must stay at Created (not auto-advance
 * to AwaitingPayment) - staff have to check CIPC and send a quote
 * first (see AnnualReturnGuard::guardRequestPayment()); asserting that
 * is the main point of this test.
 */
final class AnnualReturnSubmissionTest extends E2ETestCase
{
    public function test_submitting_a_filing_creates_a_workflow_awaiting_a_quote(): void
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        self::assertNotSame('', $nonce);

        $regNumber = 'E2E-' . uniqid();
        $companyName = 'E2E Annual Return Test ' . uniqid() . ' (Pty) Ltd';
        $financialYear = (int) date('Y') - 1;

        $response = $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $nonce,
            'application_type' => 'annual_return',
            'return_company_mode' => 'new',
            'return_new_company' => [
                'company_name' => $companyName,
                'registration_number' => $regNumber,
                'address' => [
                    'address_line_1' => '1 E2E Test Street',
                    'city' => 'Cape Town',
                    'postal_code' => '8001',
                ],
            ],
            'filing' => [
                ['financial_year' => $financialYear, 'turnover' => '500000'],
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

        self::assertNotNull($company);

        $workflow = $this->db->latestWorkflowForCompany('annual_return', $company['uuid']);

        self::assertNotNull($workflow, 'No annual_return workflow instance was created.');
        self::assertSame(
            'created',
            $workflow['status'],
            'A fresh Annual Return should stay at Created until staff send a quote, not auto-advance.'
        );

        $metadata = json_decode((string) $workflow['metadata'], true);

        self::assertSame($financialYear, $metadata['filings'][0]['financial_year'] ?? null);
        self::assertSame(500000.0, (float) ($metadata['filings'][0]['turnover'] ?? 0));
        self::assertSame('Created by the automated E2E suite.', $metadata['client_notes'] ?? null);
    }
}
