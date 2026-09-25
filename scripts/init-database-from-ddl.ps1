Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$ddlPath = Join-Path $projectRoot 'web\database\ddl\smartwallet.mysql.sql'
$rootPassword = 'smartwallet_root_dev'
$expectedMigrations = 19
$previousLocation = Get-Location

try {
    Set-Location $projectRoot

    if (-not (Test-Path -LiteralPath $ddlPath -PathType Leaf)) {
        throw "No se encuentra el DDL esperado: $ddlPath"
    }

    docker compose config --quiet
    if ($LASTEXITCODE -ne 0) {
        throw 'La configuración de Docker Compose no es válida.'
    }

    docker compose up -d mysql
    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo iniciar MySQL.'
    }

    $mysqlContainer = docker compose ps -q mysql
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($mysqlContainer)) {
        throw 'No se pudo localizar el contenedor de MySQL.'
    }

    $healthy = $false

    for ($attempt = 1; $attempt -le 60; $attempt++) {
        $status = docker inspect --format '{{.State.Health.Status}}' $mysqlContainer 2>$null

        if ($LASTEXITCODE -eq 0 -and $status -eq 'healthy') {
            $healthy = $true
            break
        }

        Start-Sleep -Seconds 2
    }

    if (-not $healthy) {
        throw 'MySQL no alcanzó el estado healthy en el tiempo previsto.'
    }

    $tableCount = docker compose exec -T mysql mysql `
        --batch --skip-column-names `
        -uroot "-p$rootPassword" `
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'smartwallet';"

    if ($LASTEXITCODE -ne 0) {
        throw 'No se pudo comprobar el estado de la base smartwallet.'
    }

    if ([int]$tableCount -ne 0) {
        throw "La base smartwallet ya contiene $tableCount tablas. El DDL solo puede importarse en una base vacía."
    }

    Get-Content -LiteralPath $ddlPath -Raw |
        docker compose exec -T mysql mysql -uroot "-p$rootPassword"

    if ($LASTEXITCODE -ne 0) {
        throw 'La importación del DDL no finalizó correctamente.'
    }

    $migrationCount = docker compose exec -T mysql mysql `
        --batch --skip-column-names `
        -uroot "-p$rootPassword" `
        smartwallet `
        -e 'SELECT COUNT(*) FROM migrations;'

    if ($LASTEXITCODE -ne 0 -or [int]$migrationCount -ne $expectedMigrations) {
        throw "La base se creó, pero no contiene las $expectedMigrations migraciones esperadas."
    }

    Write-Host "Base smartwallet creada desde el DDL con $migrationCount migraciones registradas."
    Write-Host 'Ejecuta .\scripts\start.ps1 para iniciar el resto de SmartWallet.'
}
finally {
    Set-Location $previousLocation
}
