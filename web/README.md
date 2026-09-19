# Aplicación web de SmartWallet

Esta carpeta contiene la aplicación Laravel de SmartWallet. El entorno se administra
desde la raíz del proyecto con Docker Compose; no es necesario instalar PHP,
Composer, Node.js ni MySQL directamente en Windows.

## Tecnología

- PHP 8.5 y Laravel 13.
- Blade y MVC renderizado en servidor.
- CSS propio organizado con BEM.
- JavaScript vanilla y Vite.
- MySQL 8.4 LTS.

## Estructura principal

- `app/Actions`: operaciones financieras y casos de uso reutilizables.
- `app/Http/Controllers`: entrada web y validación de solicitudes.
- `app/Models`: entidades y relaciones Eloquent.
- `database/migrations`: esquema versionado de MySQL.
- `database/seeders`: datos exclusivamente de desarrollo bajo ejecución explícita.
- `resources/views`: vistas Blade.
- `resources/css/app.css`: sistema visual y bloques BEM.
- `resources/js/app.js`: mejoras progresivas sin framework.
- `tests`: pruebas unitarias y funcionales.

## Comandos habituales

Ejecuta estos comandos desde la raíz `2027-web-gastos`:

```powershell
.\scripts\start.ps1
.\scripts\stop.ps1
.\scripts\test.ps1
.\scripts\release-check.ps1
```

`release-check.ps1` es el control previo a una entrega: valida la versión,
arranca los servicios, comprueba migraciones y formato, compila los recursos,
ejecuta la suite aislada y verifica la pantalla de acceso.

Para comprobar el formato PHP:

```powershell
docker compose exec -T app php vendor/bin/pint --test
```

Para compilar los recursos destinados a una entrega:

```powershell
docker compose exec -T node npm run build
```

Las pruebas nunca deben lanzarse directamente contra la configuración normal de
`web/.env`. `scripts/test.ps1` es el punto de entrada seguro porque fuerza
`APP_ENV=testing` y la base aislada `smartwallet_test`.

La instalación completa, la persistencia y el diagnóstico están documentados en
[INSTALACION_LOCAL.md](../INSTALACION_LOCAL.md).
