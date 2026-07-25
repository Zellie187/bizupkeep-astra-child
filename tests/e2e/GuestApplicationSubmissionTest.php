<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E;

use BizUpKeep\Tests\E2E\Support\Config;
use BizUpKeep\Tests\E2E\Support\Database;
use BizUpKeep\Tests\E2E\Support\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Covers /apply/'s guest path end to end over real HTTP: no login (or
 * even a prior account) required at all - a first-time visitor fills
 * in the "Your Details" fieldset alongside the rest of the form, and
 * bizupkeep_child_register_guest_applicant() silently creates and
 * logs them into a real WordPress account as part of the same
 * submission. Deliberately does NOT extend E2ETestCase, since that
 * base class logs in as the configured test client before every test
 * - the whole point here is an HttpClient that starts with no session
 * at all.
 */
final class GuestApplicationSubmissionTest extends TestCase
{
    private HttpClient $http;

    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new HttpClient(Config::baseUrl());
        $this->db = new Database(Config::dbPrefix());
    }

    public function test_a_guest_can_submit_an_application_and_is_logged_in_afterwards(): void
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        self::assertNotSame('', $nonce, 'Could not find the apply-form nonce - is the guest form actually rendering for a logged-out visitor?');

        $email = 'e2e-guest-' . uniqid() . '@example.com';
        $companyName = 'E2E Guest Registration ' . uniqid() . ' (Pty) Ltd';

        $response = $this->http->post('/apply/', [
            'bizupkeep_apply_nonce' => $nonce,
            'guest_first_name' => 'Guest',
            'guest_last_name' => 'Applicant',
            'guest_email' => $email,
            'guest_phone' => '0821234567',
            'application_type' => 'new_registration',
            'proposed_name' => [$companyName],
            'company_address' => [
                'address_line_1' => '1 Guest Street',
                'city' => 'Cape Town',
                'postal_code' => '8001',
            ],
            'director' => [
                ['first_name' => 'Guest', 'last_name' => 'Director', 'id_number' => '9001015800086'],
            ],
        ]);

        self::assertStringContainsString(
            'submitted=1',
            $response->finalUrl,
            'Expected a redirect to ?submitted=1; got: ' . $response->finalUrl
        );

        $wpUser = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('users') . ' WHERE user_email = ?',
            [$email]
        );

        self::assertNotNull($wpUser, 'No WordPress account was created for the guest applicant.');

        $client = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_clients') . ' WHERE wp_user_id = ?',
            [(int) $wpUser['ID']]
        );

        self::assertNotNull($client, 'No BizHub client record was created for the new account.');
        self::assertSame('Guest', $client['first_name']);
        self::assertSame('0821234567', $client['phone'], 'The phone typed into the guest form should carry through to the client profile.');

        $company = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_companies') . ' WHERE company_name = ?',
            [$companyName]
        );

        self::assertNotNull($company, 'No company was created for the submitted application.');

        // The whole point of the guest flow: no separate login step -
        // the auth cookie set during submission should already let
        // this same HttpClient instance reach a login-gated page.
        $myApplications = $this->http->get('/client-portal/client-portal-applications/');

        self::assertTrue(
            $myApplications->bodyContains($companyName),
            'The guest was not actually logged in after submitting - My Applications did not show their new application.'
        );
    }

    public function test_submitting_with_an_already_registered_email_is_rejected(): void
    {
        $applyPage = $this->http->get('/apply/');
        $nonce = $applyPage->nonce('bizupkeep_apply_nonce');

        $email = 'e2e-guest-dup-' . uniqid() . '@example.com';
        $companyName = 'E2E Guest Duplicate ' . uniqid() . ' (Pty) Ltd';

        $fields = [
            'bizupkeep_apply_nonce' => $nonce,
            'guest_first_name' => 'First',
            'guest_last_name' => 'Attempt',
            'guest_email' => $email,
            'guest_phone' => '',
            'application_type' => 'new_registration',
            'proposed_name' => [$companyName],
            'company_address' => [
                'address_line_1' => '1 Guest Street',
                'city' => 'Cape Town',
                'postal_code' => '8001',
            ],
            'director' => [
                ['first_name' => 'A', 'last_name' => 'B', 'id_number' => '9001015800086'],
            ],
        ];

        $first = $this->http->post('/apply/', $fields);
        self::assertStringContainsString('submitted=1', $first->finalUrl);

        // Second submission, same email, fresh (logged-out) client -
        // the exact scenario the "silent create" flow can never be
        // allowed to just log into: nothing here proves this second
        // request actually is the same person.
        $secondFields = $fields;
        $secondFields['guest_first_name'] = 'Second';
        $secondFields['proposed_name'] = ['E2E Guest Should Not Be Created ' . uniqid() . ' (Pty) Ltd'];

        $secondClient = new HttpClient(Config::baseUrl());
        $secondApplyPage = $secondClient->get('/apply/');
        $secondFields['bizupkeep_apply_nonce'] = $secondApplyPage->nonce('bizupkeep_apply_nonce');

        $second = $secondClient->post('/apply/', $secondFields);

        self::assertStringContainsString(
            'apply_error=email_exists',
            $second->finalUrl,
            'Expected the duplicate-email submission to be rejected with apply_error=email_exists; got: ' . $second->finalUrl
        );

        $duplicateCompany = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('bizhub_companies') . ' WHERE company_name LIKE ?',
            ['E2E Guest Should Not Be Created%']
        );

        self::assertNull($duplicateCompany, 'A company was created despite the duplicate-email submission being rejected.');

        $accountCount = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM ' . $this->db->table('users') . ' WHERE user_email = ?',
            [$email]
        );

        self::assertSame(1, (int) ($accountCount['total'] ?? 0), 'Exactly one account should exist for this email - the duplicate attempt must not create a second one.');
    }
}
