<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E\Support;

/**
 * Every environment-specific value the suite needs, read from
 * environment variables with defaults matching the local wp-local
 * test setup documented in tests/e2e/README.md. Deliberately not
 * hardcoded: this environment tends to live under a different temp
 * path each time it's rebuilt, and a real CI/staging run would need
 * entirely different values.
 */
final class Config
{
    public static function baseUrl(): string
    {
        return rtrim(getenv('BIZUPKEEP_E2E_BASE_URL') ?: 'http://localhost:8766', '/');
    }

    public static function dbHost(): string
    {
        return getenv('BIZUPKEEP_E2E_DB_HOST') ?: '127.0.0.1';
    }

    public static function dbPort(): string
    {
        return getenv('BIZUPKEEP_E2E_DB_PORT') ?: '33061';
    }

    public static function dbName(): string
    {
        return getenv('BIZUPKEEP_E2E_DB_NAME') ?: 'bizupkeep_live_copy';
    }

    public static function dbUser(): string
    {
        return getenv('BIZUPKEEP_E2E_DB_USER') ?: 'root';
    }

    public static function dbPassword(): string
    {
        return getenv('BIZUPKEEP_E2E_DB_PASSWORD') ?: '';
    }

    /**
     * WordPress table prefix for the target database - varies per
     * install, so this has no sensible universal default; the one set
     * here matches the specific production-copy database this suite
     * has been developed and run against.
     */
    public static function dbPrefix(): string
    {
        return getenv('BIZUPKEEP_E2E_DB_PREFIX') ?: 'cvqi2xqi_';
    }

    /**
     * A client-role WordPress account the suite logs in as to drive
     * every client-facing form. Must already exist with this password
     * set - the suite does not create or provision it (see the
     * README's "One-time setup" section for how to do that once via
     * wp-cli).
     */
    public static function clientUsername(): string
    {
        return getenv('BIZUPKEEP_E2E_CLIENT_USERNAME') ?: 'testclient3';
    }

    public static function clientPassword(): string
    {
        return getenv('BIZUPKEEP_E2E_CLIENT_PASSWORD') ?: 'ClientTest123!';
    }
}
