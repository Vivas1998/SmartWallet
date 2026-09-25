# Instalación de SmartWallet en otro equipo

Revisión: 19 de septiembre de 2026.

Esta guía describe la instalación local de SmartWallet 1.2 en otro equipo con
Windows 11. Cubre dos casos distintos:

1. **Instalación nueva:** código limpio, base de datos vacía y una clave nueva.
2. **Traslado de la instalación existente:** conserva usuarios, proyectos,
   movimientos y demás datos del equipo anterior.

No mezcles ambos recorridos. Para una instalación nueva no debes copiar la base
de datos ni `web/.env`. Para trasladar datos debes conservar la clave de Laravel
y realizar un volcado de MySQL.

## 1. Qué se instala

En Windows solo hacen falta estas aplicaciones:

- Windows 11 de 64 bits con virtualización activada;
- WSL 2;
- Docker Desktop, que incluye Docker Engine y Docker Compose;
- Git para descargar y actualizar el código;
- PowerShell, incluido en Windows.

No instales PHP, Composer, Node.js, npm ni MySQL directamente en Windows. Las
versiones correctas ya se ejecutan dentro de los contenedores de SmartWallet.

El código se publica en <https://github.com/Vivas1998/SmartWallet>.

### Recursos orientativos

- 8 GB de memoria RAM en el equipo; al menos 4 GB disponibles para Docker.
- 4 núcleos de CPU recomendados.
- 10 GB libres para imágenes, dependencias y la base de datos inicial.
- Conexión a Internet durante el primer arranque para descargar las imágenes y
  dependencias.

## 2. Preparar Windows

### 2.1 Comprobar la versión y la virtualización

Abre PowerShell y ejecuta:

```powershell
Get-ComputerInfo -Property WindowsProductName,WindowsVersion,OsArchitecture
systeminfo | Select-String 'Hyper-V Requirements','Requisitos de Hyper-V'
```

Si Windows indica que la virtualización está desactivada, actívala en la BIOS o
UEFI antes de continuar. El nombre habitual de la opción es `Intel VT-x`,
`Intel Virtualization Technology`, `AMD-V` o `SVM Mode`.

### 2.2 Instalar o actualizar WSL 2

Abre **Terminal o PowerShell como administrador** y ejecuta:

```powershell
wsl --install
wsl --update
wsl --set-default-version 2
```

Reinicia Windows si lo solicita. Después comprueba el estado:

```powershell
wsl --status
wsl --version
```

Si `wsl --install` informa de que WSL ya está instalado, continúa con `wsl
--update`.

### 2.3 Instalar Docker Desktop

Desde una terminal con permisos de administrador:

```powershell
winget install --exact --id Docker.DockerDesktop --accept-package-agreements --accept-source-agreements
```

Después:

1. abre Docker Desktop desde el menú Inicio;
2. acepta el uso del motor WSL 2 si se solicita;
3. espera a que muestre que el motor está iniciado;
4. no actives Kubernetes, porque SmartWallet no lo necesita.

Comprueba la instalación desde una terminal normal:

```powershell
docker version
docker compose version
```

Ambos comandos deben mostrar cliente y servidor sin errores de conexión.

### 2.4 Instalar Git

Desde PowerShell:

```powershell
winget install --exact --id Git.Git --accept-package-agreements --accept-source-agreements
```

Cierra y vuelve a abrir PowerShell y comprueba la instalación:

```powershell
git --version
```

## 3. Preparar una copia limpia del código

### Opción recomendada: clonar el repositorio

Ejecuta en PowerShell:

```powershell
New-Item -ItemType Directory -Path 'C:\Aplicaciones' -Force | Out-Null
Set-Location 'C:\Aplicaciones'
git clone https://github.com/Vivas1998/SmartWallet.git
Set-Location '.\SmartWallet'
git switch main
```

El repositorio no contiene claves, bases de datos, dependencias generadas ni
datos financieros. Esos elementos se crean localmente durante la instalación.

### Alternativa: crear un ZIP limpio en el equipo de origen

Ejecuta lo siguiente en PowerShell. El comando excluye secretos, dependencias
generadas, registros y volcados de datos:

```powershell
$packageSource = 'C:\HomeLab_GPT\proyectos\2027-web-gastos'
$packageStamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$packageDirectory = "C:\Temp\smartwallet-$packageStamp"
$packageArchive = "C:\Temp\smartwallet-$packageStamp.zip"

New-Item -ItemType Directory -Path 'C:\Temp' -Force | Out-Null
New-Item -ItemType Directory -Path $packageDirectory | Out-Null

robocopy $packageSource $packageDirectory /E `
    /XD '.git' 'vendor' 'node_modules' `
    /XF '.env' 'hot' '*.log' '*.sql' '*.sql.gz'

if ($LASTEXITCODE -ge 8) {
    throw "Robocopy no pudo preparar el paquete. Código: $LASTEXITCODE"
}

$ddlSource = Join-Path $packageSource 'web\database\ddl\smartwallet.mysql.sql'
$ddlDestinationDirectory = Join-Path $packageDirectory 'web\database\ddl'
New-Item -ItemType Directory -Path $ddlDestinationDirectory -Force | Out-Null
Copy-Item -LiteralPath $ddlSource -Destination $ddlDestinationDirectory

Compress-Archive -LiteralPath $packageDirectory -DestinationPath $packageArchive
Write-Host "Paquete preparado: $packageArchive"
```

Los códigos de Robocopy entre 0 y 7 indican copia correcta. Un código igual o
superior a 8 es un error y no debe ignorarse.

La DDL canónica de SmartWallet se incorpora expresamente al paquete. Los demás
archivos SQL siguen excluidos para evitar trasladar por accidente volcados con
datos financieros.

Traslada el ZIP al nuevo equipo mediante un medio de confianza. El paquete no
incluye la base de datos ni las claves locales.

### Extraer el código en el equipo nuevo

Se recomienda una ruta local sencilla y fuera de carpetas sincronizadas por
OneDrive, por ejemplo:

```text
C:\Aplicaciones\SmartWallet
```

Extrae allí el ZIP y comprueba que la carpeta final contenga directamente:

```text
.docker\
scripts\
web\
compose.yaml
.env.example
VERSION
```

Si al extraer se crea una carpeta intermedia con fecha, mueve su contenido para
que `compose.yaml` quede en `C:\Aplicaciones\SmartWallet\compose.yaml`.

## 4. Instalación nueva, sin datos anteriores

### 4.1 Abrir el proyecto

Abre PowerShell y ejecuta:

```powershell
Set-Location 'C:\Aplicaciones\SmartWallet'
Get-Content .\VERSION
docker compose config --quiet
```

El último comando no debe mostrar errores. `VERSION` permite comprobar qué
entrega se está instalando.

### 4.2 Configurar los puertos

Crea la configuración de Docker a partir de la plantilla:

```powershell
Copy-Item .\.env.example .\.env
notepad .\.env
```

Valores predeterminados:

```dotenv
SMARTWALLET_APP_PORT=8010
SMARTWALLET_ACCEPTANCE_APP_PORT=8011
SMARTWALLET_VITE_PORT=5174
SMARTWALLET_MYSQL_PORT=3308
SMARTWALLET_MAILPIT_PORT=8026
```

Solo cambia un puerto si ya está ocupado. Para comprobarlo:

```powershell
Get-NetTCPConnection -State Listen |
    Where-Object LocalPort -In 8010,8011,5174,3308,8026 |
    Select-Object LocalAddress,LocalPort,OwningProcess
```

No crees `web/.env` manualmente en una instalación nueva. El primer arranque lo
copiará desde `web/.env.example` y generará una `APP_KEY` única.

### 4.3 Primer arranque

En una instalación completamente nueva puedes crear la base mediante las
migraciones normales o mediante el DDL consolidado. Para utilizar el DDL, hazlo
ahora, antes del primer `start.ps1`:

```powershell
.\scripts\init-database-from-ddl.ps1
```

El comando solo acepta una base `smartwallet` sin tablas, importa el esquema
completo del desarrollo actual y comprueba que estén registradas sus 19
migraciones. No crea usuarios, proyectos ni movimientos. Si la base contiene
alguna tabla, se detiene sin modificarla.

Si no ejecutas este inicializador, el primer arranque construirá el mismo esquema
aplicando las migraciones Laravel una a una.

Permite ejecutar scripts solo en esta ventana de PowerShell y arranca SmartWallet:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\scripts\start.ps1
```

El primer arranque puede tardar varios minutos. El script:

1. construye la imagen PHP 8.5;
2. descarga e inicia MySQL 8.4, Node 24 y Mailpit;
3. instala Composer y npm dentro de los volúmenes de Docker;
4. crea `web/.env` y una clave Laravel única;
5. aplica las migraciones pendientes, o reconoce el DDL ya actualizado;
6. recupera, si existieran, movimientos recurrentes vencidos;
7. espera a que los servicios estén preparados.

No cierres Docker Desktop mientras se utiliza SmartWallet.

### 4.4 Comprobar la instalación

Ejecuta:

```powershell
docker compose ps
docker compose exec -T app php artisan smartwallet:environment
```

El diagnóstico debe mostrar:

```text
Entorno: local
Conexión: mysql
Servidor: mysql
Base de datos: smartwallet
```

Los servicios `app`, `mysql`, `node` y `mailpit` deben aparecer como `healthy`;
`scheduler` debe aparecer en ejecución. `mysql_test` permanece apagado hasta que
se ejecutan pruebas.

Comprueba también las respuestas HTTP:

```powershell
(Invoke-WebRequest 'http://localhost:8010/acceder' -UseBasicParsing).StatusCode
(Invoke-WebRequest 'http://localhost:5174/resources/css/app.css' -UseBasicParsing).StatusCode
```

Ambas deben devolver `200`.

### 4.5 Crear la primera cuenta

Abre estas direcciones:

- Aplicación: <http://localhost:8010>
- Registro: <http://localhost:8010/registro>
- Correo local: <http://localhost:8026>

No se crea ninguna cuenta de demostración. Registra la primera cuenta familiar
desde la pantalla de registro. El correo debe ser único y la contraseña debe
tener al menos 12 caracteres.

Mailpit recibe los mensajes de recuperación únicamente dentro del equipo; no
envía correo a Internet.

### 4.6 Verificación completa opcional

Para validar la instalación sin tocar los datos reales:

```powershell
.\scripts\test.ps1
```

Las pruebas usan exclusivamente `smartwallet_test`, una base temporal separada que
se inicia y detiene automáticamente. Nunca ejecutes PHPUnit manualmente contra
la configuración normal de `web/.env`.

Para repetir todo el control de una entrega:

```powershell
.\scripts\release-check.ps1
```

Este proceso arranca el entorno, revisa las migraciones y el formato, compila los
recursos y ejecuta la suite completa.

## 5. Trasladar también los datos del equipo anterior

Este recorrido sustituye al apartado 4 para quien quiera conservar la
instalación existente. El volcado contiene información financiera y debe
trasladarse y conservarse de forma segura.

### 5.1 Crear el volcado en el equipo anterior

Desde la carpeta de SmartWallet, asegúrate de que el entorno esté iniciado y detén
temporalmente los procesos que podrían escribir datos:

```powershell
docker compose stop app scheduler node
```

Crea el volcado dentro del contenedor y cópialo a la carpeta actual:

```powershell
docker compose exec -T mysql sh -lc 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --triggers "$MYSQL_DATABASE" > /tmp/smartwallet-backup.sql'
docker compose cp mysql:/tmp/smartwallet-backup.sql .\smartwallet-backup.sql
docker compose exec -T mysql rm -f /tmp/smartwallet-backup.sql
```

Vuelve a iniciar la instalación original:

```powershell
docker compose start app scheduler node
```

Comprueba que el archivo tenga contenido:

```powershell
Get-Item .\smartwallet-backup.sql | Select-Object FullName,Length,LastWriteTime
```

También copia por separado `web/.env`. Este archivo contiene la `APP_KEY` de la
instalación. Consérvalo de forma privada y no lo incluyas en el ZIP general ni en
un repositorio.

Transfiere al nuevo equipo:

- el paquete limpio del código;
- `smartwallet-backup.sql`;
- el `web/.env` original.

### 5.2 Preparar el equipo nuevo

Instala WSL 2 y Docker Desktop y extrae el código como en los apartados 2 y 3.
Crea el `.env` de la raíz con los puertos deseados:

```powershell
Set-Location 'C:\Aplicaciones\SmartWallet'
Copy-Item .\.env.example .\.env
notepad .\.env
```

Coloca el `web/.env` original exactamente en:

```text
C:\Aplicaciones\SmartWallet\web\.env
```

Revisa dentro de él que `APP_URL` use el puerto elegido. Mantén intacta
`APP_KEY`. Las credenciales de MySQL deben seguir coincidiendo con las definidas
en `compose.yaml` mientras la instalación continúe siendo exclusivamente local.

### 5.3 Crear los contenedores e importar

Realiza primero un arranque para crear los servicios y el volumen de MySQL:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\scripts\start.ps1
```

Detén los servicios que acceden a la aplicación, manteniendo MySQL activo:

```powershell
docker compose stop app scheduler node
```

Copia e importa el volcado:

```powershell
docker compose cp .\smartwallet-backup.sql mysql:/tmp/smartwallet-backup.sql
docker compose exec -T mysql sh -lc 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /tmp/smartwallet-backup.sql'
docker compose exec -T mysql rm -f /tmp/smartwallet-backup.sql
```

Arranca de nuevo y limpia las cachés reconstruibles:

```powershell
docker compose up -d --wait
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan smartwallet:environment
```

Inicia sesión y comprueba proyectos, cuentas, movimientos, presupuestos e
informes antes de retirar el equipo anterior. No importes este volcado en
`smartwallet_test`.

Conserva temporalmente el volcado hasta verificar el traslado. Después trátalo
como cualquier archivo financiero sensible.

## 6. Uso diario

Desde `C:\Aplicaciones\SmartWallet`:

```powershell
# Iniciar o actualizar contenedores
.\scripts\start.ps1

# Consultar el estado
docker compose ps

# Detener SmartWallet conservando los datos
.\scripts\stop.ps1
```

Docker Desktop debe estar iniciado antes de ejecutar los scripts. La versión
local no instala un servicio de Windows ni arranca SmartWallet automáticamente al
encender el equipo.

Detener o reconstruir contenedores no elimina la información. Los datos se
guardan en el volumen `smartwallet_mysql_data`.

## 7. Actualizar SmartWallet en ese equipo

Antes de reemplazar código:

1. crea un volcado de MySQL siguiendo el apartado 5.1;
2. conserva `.env` y `web/.env`;
3. detén SmartWallet con `.\scripts\stop.ps1`;
4. sustituye los archivos de código sin sobrescribir esos dos archivos;
5. ejecuta `.\scripts\start.ps1` para construir y aplicar migraciones;
6. ejecuta `.\scripts\test.ps1` y revisa la aplicación.

No copies el volumen Docker manualmente mientras MySQL esté en ejecución.

## 8. Diagnóstico y problemas frecuentes

### Docker no responde

Abre Docker Desktop, espera a que el motor esté iniciado y ejecuta:

```powershell
docker info
wsl --status
```

Si WSL está desactualizado:

```powershell
wsl --update
wsl --shutdown
```

Vuelve a abrir Docker Desktop.

### PowerShell bloquea los scripts

Utiliza únicamente para la terminal actual:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

No es necesario reducir permanentemente la política de seguridad del sistema.

### Un puerto está ocupado

Localiza el proceso:

```powershell
Get-NetTCPConnection -State Listen |
    Where-Object LocalPort -In 8010,8011,5174,3308,8026 |
    Select-Object LocalAddress,LocalPort,OwningProcess
```

Cambia el puerto correspondiente en el `.env` de la raíz y repite
`.\scripts\start.ps1`.

### La página abre sin estilos

```powershell
docker compose ps node
docker compose logs --tail=200 node
```

`node` debe aparecer como `healthy`. Comprueba que el puerto de Vite responda y
recarga el navegador con `Ctrl + F5`:

```powershell
(Invoke-WebRequest 'http://localhost:5174/resources/css/app.css' -UseBasicParsing).StatusCode
```

### Error 500 o la aplicación no inicia

```powershell
docker compose ps -a
docker compose logs --tail=200 app mysql scheduler
docker compose exec -T app php artisan migrate:status
```

No publiques registros completos: pueden contener rutas, direcciones y datos de
diagnóstico.

### Recuperación de contraseña

Solicita la recuperación desde SmartWallet y abre <http://localhost:8026>. Al ser un
entorno local, el mensaje se entrega a Mailpit y no al buzón real del usuario.

## 9. Seguridad y alcance de esta instalación

- Los puertos se publican solo en `127.0.0.1`; SmartWallet es accesible únicamente
  desde el mismo equipo.
- No abras esos puertos en el router ni crees reglas de exposición a Internet.
- La configuración local utiliza credenciales de desarrollo y `APP_DEBUG=true`.
- No existe copia automática en la versión local 1.0.
- Un fallo del disco o eliminar el volumen puede provocar pérdida de datos.
- Para un futuro homelab serán obligatorios HTTPS, `APP_DEBUG=false`,
  credenciales nuevas, copias protegidas y restauración probada.

Consulta [Seguridad y privacidad](SEGURIDAD.md) antes de cambiar el alcance de la
instalación.

## 10. Detener o desinstalar

Para detener SmartWallet y conservar los datos:

```powershell
.\scripts\stop.ps1
```

Para retirar únicamente los contenedores y la red, conservando los volúmenes:

```powershell
docker compose down
```

La siguiente operación elimina definitivamente la base, las dependencias de los
volúmenes y todos los datos locales. Solo debe utilizarse después de crear y
verificar un volcado:

```powershell
docker compose down --volumes
```

Después de una eliminación completa puede borrarse la carpeta
`C:\Aplicaciones\SmartWallet`. Las imágenes compartidas de Docker no necesitan
eliminarse para desinstalar la aplicación.

## 11. Lista final de puesta a punto

- [ ] Windows actualizado y virtualización activada.
- [ ] WSL 2 instalado y actualizado.
- [ ] Docker Desktop iniciado; `docker version` y `docker compose version`
  funcionan.
- [ ] Código extraído en una ruta local y `VERSION` revisado.
- [ ] Puertos definidos en `.env` y libres.
- [ ] Instalación nueva con `APP_KEY` nueva, o traslado con la clave original.
- [ ] `start.ps1` finaliza sin errores.
- [ ] Servicios principales saludables y base activa `smartwallet`.
- [ ] Aplicación y CSS responden con HTTP 200.
- [ ] Primera cuenta creada, o acceso existente comprobado tras la migración.
- [ ] Proyectos, movimientos, presupuestos e informes revisados.
- [ ] Suite automática ejecutada opcionalmente contra `smartwallet_test`.
- [ ] Volcado externo conservado si se trasladaron datos reales.
