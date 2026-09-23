$ErrorActionPreference = 'Stop'

$projectDirectory = Split-Path -Parent $PSScriptRoot
$versionFile = Join-Path $projectDirectory 'VERSION'
$applicationEnvironmentExample = Join-Path $projectDirectory 'web/.env.example'
$packageFile = Join-Path $projectDirectory 'web/package.json'
$changelogFile = Join-Path $projectDirectory 'CHANGELOG.md'

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory)]
        [scriptblock] $Command,

        [Parameter(Mandatory)]
        [string] $FailureMessage
    )

    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw $FailureMessage
    }
}

function Get-ComposeSetting {
    param(
        [Parameter(Mandatory)]
        [string] $Name,

        [Parameter(Mandatory)]
        [string] $Fallback
    )

    $composeEnvironment = Join-Path $projectDirectory '.env'
    $setting = Get-Content -LiteralPath $composeEnvironment |
        Where-Object { $_ -match "^$([regex]::Escape($Name))=" } |
        Select-Object -Last 1

    if ($null -eq $setting) {
        return $Fallback
    }

    $value = ($setting -split '=', 2)[1].Trim()

    if ([string]::IsNullOrWhiteSpace($value)) {
        return $Fallback
    }

    return $value
}

Push-Location $projectDirectory

try {
    $version = (Get-Content -LiteralPath $versionFile -Raw).Trim()

    if ($version -notmatch '^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$') {
        throw "VERSION no contiene un identificador semántico válido: $version"
    }

    $environmentVersion = Get-Content -LiteralPath $applicationEnvironmentExample |
        Where-Object { $_ -match '^APP_VERSION=' } |
        Select-Object -Last 1

    if ($null -eq $environmentVersion -or ($environmentVersion -split '=', 2)[1].Trim() -ne $version) {
        throw 'VERSION y APP_VERSION de web/.env.example no coinciden.'
    }

    $packageVersionMatch = [regex]::Match(
        (Get-Content -LiteralPath $packageFile -Raw),
        '"version"\s*:\s*"([^"]+)"'
    )

    if (-not $packageVersionMatch.Success -or $packageVersionMatch.Groups[1].Value -ne $version) {
        throw 'VERSION y la versión de web/package.json no coinciden.'
    }

    if (-not (Select-String -LiteralPath $changelogFile -SimpleMatch "## [$version]" -Quiet)) {
        throw "CHANGELOG.md no contiene una entrada para $version."
    }

    Write-Host "Comprobando SmartWallet $version..." -ForegroundColor Cyan

    Invoke-CheckedCommand -Command { docker compose config --quiet } `
        -FailureMessage 'La configuración de Docker Compose no es válida.'

    & (Join-Path $PSScriptRoot 'start.ps1')

    Invoke-CheckedCommand -Command { docker compose exec -T app php artisan migrate:status --no-ansi } `
        -FailureMessage 'No se pudo comprobar el estado de las migraciones.'

    Invoke-CheckedCommand -Command { docker compose exec -T app php vendor/bin/pint --test } `
        -FailureMessage 'Laravel Pint ha detectado problemas de formato.'

    Invoke-CheckedCommand -Command { docker compose exec -T node npm run build } `
        -FailureMessage 'La compilación de producción ha fallado.'

    & (Join-Path $PSScriptRoot 'test.ps1')

    $appPort = Get-ComposeSetting -Name 'SMARTWALLET_APP_PORT' -Fallback '8010'
    $response = Invoke-WebRequest -Uri "http://localhost:$appPort/acceder" -UseBasicParsing

    if ($response.StatusCode -ne 200) {
        throw "La pantalla de acceso respondió con HTTP $($response.StatusCode)."
    }

    Invoke-CheckedCommand -Command { docker compose ps } `
        -FailureMessage 'No se pudo consultar el estado final de los servicios.'

    Write-Host ''
    Write-Host "Comprobación automática de $version superada." -ForegroundColor Green
    Write-Host 'Queda la comprobación humana con zoom nativo al 200 % descrita en el documento de liberación de la versión actual.'
}
finally {
    Pop-Location
}
