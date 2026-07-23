<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use BizUpKeep\Tests\E2E\Support\Config;

$baseUrl = Config::baseUrl();
$handle = @fopen($baseUrl, 'r');

if ($handle === false) {
    fwrite(
        STDERR,
        "\nCould not reach {$baseUrl} - this suite needs the local WordPress test environment "
            . "(mariadbd + php -S) actually running. See tests/e2e/README.md.\n\n"
    );
    exit(1);
}

fclose($handle);
