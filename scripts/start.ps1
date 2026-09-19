$ErrorActionPreference = 'Stop'

$projectDirectory = Split-Path -Parent $PSScriptRoot
$composeEnvironment = Join-Path $projectDirectory '.env'
$composeEnvironmentExample = Join-Path $projectDirectory '.env.example'

function Get-ComposeSetting {
    param(
        [Parameter(Mandatory)]
        [string] $Name,

        [Parameter(Mandatory)]
        [string] $Fallback
    )

    $environmentValue = [Environment]::GetEnvironmentVariable($Name, 'Process')

    if (-not [string]::IsNullOrWhiteSpace($environmentValue)) {
        return $environmentValue
    }

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
    docker compose version *> $null

    if ($LASTEXITCODE -ne 0) {
        throw 'Docker Compose no está disponible. Inicia Docker Desktop y vuelve a intentarlo.'
    }

    if (-not (Test-Path -LiteralPath $composeEnvironment)) {
        Copy-Item -LiteralPath $composeEnvironmentExample -Destination $composeEnvironment
        Write-Host 'Se ha creado .env con los puertos locales predeterminados.' -ForegroundColor Cyan
    }

    docker compose up -d --build --wait

    if ($LASTEXITCODE -ne 0) {
        throw 'SmartWallet no ha podido completar el arranque. Revisa los mensajes anteriores.'
    }

    $appPort = Get-ComposeSetting -Name 'SMARTWALLET_APP_PORT' -Fallback '8010'
    $mailpitPort = Get-ComposeSetting -Name 'SMARTWALLET_MAILPIT_PORT' -Fallback '8026'

    Write-Host ''
    Write-Host 'SmartWallet está preparada.' -ForegroundColor Green
    Write-Host "Aplicación: http://localhost:$appPort"
    Write-Host "Correo local: http://localhost:$mailpitPort"
    Write-Host 'Los datos se guardan en la base persistente smartwallet.'
}
finally {
    Pop-Location
}
