<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E\Support;

use CURLFile;
use RuntimeException;

/**
 * A thin cURL wrapper driving the site exactly the way manual testing
 * did throughout this project: real HTTP requests carrying real
 * cookies, following redirects, submitting real (possibly multipart)
 * form data. No browser, no JavaScript execution - every flow this
 * suite covers is a classic WordPress form POST that never needed one.
 */
final class HttpClient
{
    private readonly string $cookieJar;

    public function __construct(
        private readonly string $baseUrl
    ) {
        $cookieJar = tempnam(sys_get_temp_dir(), 'bizupkeep-e2e-');

        if ($cookieJar === false) {
            throw new RuntimeException('Could not create a cookie jar for the HTTP client.');
        }

        $this->cookieJar = $cookieJar;
    }

    public function __destruct()
    {
        @unlink($this->cookieJar);
    }

    /**
     * Log in as a WordPress user, carrying the resulting auth cookies
     * for every subsequent request from this client instance.
     */
    public function login(string $username, string $password): void
    {
        $this->post('/wp-login.php', [
            'log' => $username,
            'pwd' => $password,
            'wp-submit' => 'Log In',
            'redirect_to' => $this->baseUrl . '/wp-admin/',
        ]);
    }

    public function get(string $path): Response
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string,mixed> $fields
     * @param array<string,string> $files Field name => absolute file path. Sent as
     *                                     multipart/form-data whenever non-empty.
     */
    public function post(string $path, array $fields, array $files = []): Response
    {
        return $this->request('POST', $path, $fields, $files);
    }

    /**
     * @param array<string,mixed>  $fields
     * @param array<string,string> $files
     */
    private function request(string $method, string $path, array $fields = [], array $files = []): Response
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);

            $postFields = $files === []
                ? http_build_query($fields)
                : $this->multipartFields($fields, $files);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);

        if ($body === false || $body === true) {
            throw new RuntimeException(sprintf('HTTP request to %s failed: %s', $path, $error));
        }

        return new Response($status, $body, $finalUrl);
    }

    /**
     * Flatten posted fields (including nested arrays, e.g.
     * proposed_name[] or director[0][first_name]) into the
     * name => value pairs curl's multipart encoder expects, then add
     * each file as a CURLFile under its own field name.
     *
     * @param array<string,mixed>  $fields
     * @param array<string,string> $files
     *
     * @return array<string,mixed>
     */
    private function multipartFields(array $fields, array $files): array
    {
        $flat = [];
        $this->flatten($fields, '', $flat);

        foreach ($files as $fieldName => $filePath) {
            $flat[$fieldName] = new CURLFile($filePath);
        }

        return $flat;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $result
     */
    private function flatten(array $data, string $prefix, array &$result): void
    {
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $this->flatten($value, $name, $result);

                continue;
            }

            $result[$name] = (string) $value;
        }
    }
}
