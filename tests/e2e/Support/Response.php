<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E\Support;

/**
 * The result of one HttpClient request: the final HTTP status and
 * body after following redirects, plus the URL actually landed on -
 * tests assert against that URL to confirm a form redirected where it
 * should have (e.g. "?submitted=1" vs "?apply_error=1"), the same way
 * a real browser (or the curl -L this class wraps) would.
 */
final readonly class Response
{
    public function __construct(
        public int $status,
        public string $body,
        public string $finalUrl,
    ) {
    }

    /**
     * Scrape a WordPress nonce field's current value out of this
     * response's HTML - the same thing done by hand via `grep -o`
     * throughout manual testing this project, just as a reusable
     * helper. Returns '' if the field isn't present.
     */
    public function nonce(string $fieldName): string
    {
        if (preg_match('/name="' . preg_quote($fieldName, '/') . '"\s+value="([^"]*)"/', $this->body, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    public function bodyContains(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }
}
