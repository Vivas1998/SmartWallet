param(
    [ValidateRange(1000, 100000)]
    [int] $Movements = 100000
)

$ErrorActionPreference = 'Stop'

$projectDirectory = Split-Path -Parent $PSScriptRoot
$testingDatabase = 'smartwallet_test'

if (-not $testingDatabase.EndsWith('_test')) {
    throw 'La base de rendimiento debe terminar en _test. Ejecución cancelada para proteger los datos reales.'
}

Push-Location $projectDirectory

try {
    docker compose --profile testing up -d --wait mysql_test

    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo iniciar la base temporal de rendimiento.'
    }

    docker compose --profile testing run --rm --no-deps `
        -e APP_ENV=testing `
        -e DB_HOST=mysql_test `
        -e DB_DATABASE=$testingDatabase `
        -e DB_USERNAME=smartwallet_test `
        -e DB_PASSWORD=smartwallet_test `
        app sh -lc "composer install --no-interaction --prefer-dist && php artisan migrate:fresh --force && php artisan smartwallet:benchmark-scale --movements=$Movements"

    if ($LASTEXITCODE -ne 0) {
        throw 'El banco de rendimiento ha detectado un error o un objetivo incumplido.'
    }
}
finally {
    docker compose --profile testing stop mysql_test
    Pop-Location
}
