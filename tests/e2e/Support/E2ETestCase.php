<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E\Support;

use PHPUnit\Framework\TestCase;

/**
 * Base for every E2E test: a fresh HttpClient logged in as the
 * configured test client for each test method (so tests never leak
 * cookies/session state between each other), plus a shared Database
 * connection for assertions.
 */
abstract class E2ETestCase extends TestCase
{
    protected HttpClient $http;

    protected Database $db;

    protected int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new HttpClient(Config::baseUrl());
        $this->db = new Database(Config::dbPrefix());

        $this->http->login(Config::clientUsername(), Config::clientPassword());

        $clientId = $this->db->clientIdForWpUsername(Config::clientUsername());

        if ($clientId === null) {
            self::fail(sprintf(
                'No bizhub_clients row for WordPress user "%s" - log in as this user through the site '
                    . 'once (the portal auto-provisions a client record on first login) before running this suite. '
                    . 'See tests/e2e/README.md.',
                Config::clientUsername()
            ));
        }

        $this->clientId = $clientId;
    }
}
