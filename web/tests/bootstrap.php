<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$environment = getenv('APP_ENV') ?: '';
$database = getenv('DB_DATABASE') ?: '';

if ($environment !== 'testing' || ! str_ends_with($database, '_test')) {
    fwrite(STDERR, "Pruebas canceladas: APP_ENV debe ser testing y la base debe terminar en _test.\n");
    exit(1);
}
