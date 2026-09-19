$ErrorActionPreference = 'Stop'

$projectDirectory = Split-Path -Parent $PSScriptRoot
$testingDatabase = 'smartwallet_test'

if (-not $testingDatabase.EndsWith('_test')) {
    throw 'La base de pruebas debe terminar en _test. Ejecución cancelada para proteger los datos reales.'
}

Push-Location $projectDirectory

try {
    docker compose --profile testing up -d --wait mysql_test

    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo iniciar la base de pruebas.'
    }

    docker compose --profile testing run --rm --no-deps -e APP_ENV=testing -e DB_HOST=mysql_test -e DB_DATABASE=$testingDatabase -e DB_USERNAME=smartwallet_test -e DB_PASSWORD=smartwallet_test app sh -lc 'composer install --no-interaction --prefer-dist && php artisan test'

    if ($LASTEXITCODE -ne 0) {
        throw 'La suite automática ha detectado errores.'
    }
}
finally {
    docker compose --profile testing stop mysql_test
    Pop-Location
}
