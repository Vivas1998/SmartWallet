$ErrorActionPreference = 'Stop'

$projectDirectory = Split-Path -Parent $PSScriptRoot

Push-Location $projectDirectory

try {
    docker compose stop

    if ($LASTEXITCODE -ne 0) {
        throw 'No se han podido detener todos los contenedores de SmartWallet.'
    }

    Write-Host 'SmartWallet se ha detenido. Los datos permanecen en el volumen local.' -ForegroundColor Green
}
finally {
    Pop-Location
}
