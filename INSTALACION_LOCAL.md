# Instalación local de SmartWallet

Esta guía prepara SmartWallet para uso local con Docker. El procedimiento no crea
usuarios de demostración ni carga datos ficticios: la primera cuenta se registra
desde la propia aplicación.

Para preparar Windows desde cero, trasladar el código o migrar los datos desde
otra máquina, consulta [Instalación en otro equipo](INSTALACION_EN_OTRO_EQUIPO.md).

## Requisitos

- Windows 11 con Docker Desktop iniciado.
- Docker Compose v2, incluido en Docker Desktop.
- Puertos locales 8010, 5174, 3308 y 8026 disponibles, o puertos alternativos
  configurados en el archivo `.env` de la raíz.

No es necesario instalar PHP, Composer, Node.js ni MySQL en Windows.

## Primera puesta en marcha

Desde PowerShell, situado en la carpeta `2027-web-gastos`, ejecuta:

```powershell
.\scripts\start.ps1
```

El script realiza estas tareas:

1. Comprueba que Docker Compose está disponible.
2. Crea el `.env` de Docker desde `.env.example` si todavía no existe.
3. Construye la imagen PHP e inicia aplicación, programador, Vite, MySQL y
   Mailpit.
4. Espera hasta que MySQL, Laravel y Vite respondan correctamente.
5. Muestra las direcciones de la aplicación y del correo local.

En el primer arranque, el contenedor de la aplicación también crea `web/.env`,
genera una clave Laravel única y aplica todas las migraciones. La descarga inicial
de dependencias puede tardar varios minutos.

Como alternativa para recrear rápidamente una base completamente vacía, el
repositorio incluye el DDL consolidado del desarrollo actual. Debe usarse antes
del primer arranque y nunca sobre una base que ya contenga tablas:

```powershell
.\scripts\init-database-from-ddl.ps1
.\scripts\start.ps1
```

El inicializador arranca únicamente MySQL, se niega a continuar si encuentra una
base utilizada, importa `web/database/ddl/smartwallet.mysql.sql` y verifica las
19 migraciones. El DDL contiene toda la estructura, índices, claves foráneas e
historial técnico de migraciones, pero no usuarios ni datos financieros.

Después abre [http://localhost:8010/registro](http://localhost:8010/registro) y
crea la primera cuenta familiar. La aplicación no exige verificar el correo en la
versión local actual, pero el correo debe ser único.

## Servicios y puertos

| Servicio | Uso | Dirección predeterminada |
| --- | --- | --- |
| `app` | Laravel y SmartWallet | `http://localhost:8010` |
| `node` | Recursos CSS y JavaScript con Vite | `http://localhost:5174` |
| `mysql` | Base persistente de desarrollo | `127.0.0.1:3308` |
| `mailpit` | Correos locales de recuperación | `http://localhost:8026` |
| `scheduler` | Recurrencias y limpieza de papelera | Sin puerto público |
| `mysql_test` | Base temporal de pruebas | Sin puerto público; apagada normalmente |

Los puertos se pueden modificar en el `.env` de la raíz antes de iniciar el
entorno. Por ejemplo:

```dotenv
SMARTWALLET_APP_PORT=8010
SMARTWALLET_VITE_PORT=5174
SMARTWALLET_MYSQL_PORT=3308
SMARTWALLET_MAILPIT_PORT=8026
```

El entorno comunica automáticamente el puerto elegido a Laravel y Vite, también
cuando se utiliza uno distinto del predeterminado.

## Arrancar, detener y consultar el estado

Para arrancar o actualizar el entorno:

```powershell
.\scripts\start.ps1
```

Para detenerlo conservando toda la información:

```powershell
.\scripts\stop.ps1
```

Para consultar el estado de los contenedores:

```powershell
docker compose ps -a
```

Detener o recrear contenedores no borra la información. MySQL guarda los datos
reales en el volumen Docker `smartwallet_mysql_data`.

## Qué base de datos utiliza cada operación

SmartWallet mantiene dos bases deliberadamente separadas:

| Contexto | Servidor | Base | Persistencia |
| --- | --- | --- | --- |
| Aplicación local | `mysql` | `smartwallet` | Volumen `smartwallet_mysql_data` |
| Pruebas automáticas | `mysql_test` | `smartwallet_test` | Memoria temporal `tmpfs` |

La aplicación normal toma su configuración de `web/.env`. El lanzador de pruebas
sobrescribe expresamente el servidor y el nombre de la base, comprueba que el
entorno sea `testing` y exige que el nombre termine en `_test`.

Las pruebas se ejecutan únicamente con:

```powershell
.\scripts\test.ps1
```

`mysql_test` se inicia bajo demanda y se detiene siempre al finalizar, incluso si
una prueba falla. No contiene ni puede modificar los datos de `smartwallet`.

El banco de rendimiento utiliza las mismas protecciones y genera sus datos de
forma temporal. Para validar el objetivo máximo de 100.000 movimientos:

```powershell
.\scripts\benchmark.ps1 -Movements 100000
```

Sus recorridos, límites y último resultado validado están descritos en
[Pruebas de rendimiento y escala](PRUEBAS_RENDIMIENTO.md).

## Comprobación de una entrega

Antes de promover una candidata a versión final, ejecuta:

```powershell
.\scripts\release-check.ps1
```

El script comprueba que el identificador de versión sea coherente, valida Docker
Compose, arranca el entorno, revisa las migraciones y el formato PHP, compila los
recursos, ejecuta toda la suite en `smartwallet_test` y confirma que la pantalla de
acceso responda. No sustituye la revisión humana con zoom nativo al 200 % indicada
en [Liberación local de SmartWallet 1.2](LIBERACION_1_2.md).

## Correo local

Los mensajes de recuperación de contraseña se entregan a Mailpit y nunca salen a
Internet. Abre [http://localhost:8026](http://localhost:8026) para consultarlos.

## Diagnóstico básico

Si un servicio no llega a estar preparado:

```powershell
docker compose ps -a
docker compose logs app mysql node
```

Problemas habituales:

- **Docker no responde:** inicia Docker Desktop y repite `start.ps1`.
- **Puerto ocupado:** cambia únicamente el puerto correspondiente en `.env`.
- **La aplicación no muestra estilos:** comprueba que `node` figure como
  `healthy` y revisa `docker compose logs node`.
- **MySQL tarda en arrancar:** espera a que figure como `healthy`; el script de
  inicio ya realiza esta espera automáticamente.

## Conservación y eliminación de datos

La versión 1.2 local no realiza copias de seguridad automáticas. Mientras no se
elimine el volumen `smartwallet_mysql_data`, detener, reconstruir o sustituir un
contenedor conserva los datos.

`docker compose down` retira los contenedores y la red, pero conserva los
volúmenes. En cambio, añadir `--volumes` o `-v` elimina también la base de datos y
los datos no se pueden recuperar. Esa variante no debe utilizarse como operación
de mantenimiento habitual.

Las copias cifradas y la restauración probada se incorporarán cuando SmartWallet se
despliegue en el homelab.

## Seguridad del entorno local

Los puertos publicados por el entorno normal se limitan a `127.0.0.1`. Los
valores de MySQL incluidos son credenciales de desarrollo y no deben reutilizarse
en el homelab ni en un equipo accesible desde Internet.

La configuración local mantiene `APP_DEBUG=true` para diagnosticar el desarrollo.
Antes de cualquier despliegue se deben aplicar todas las condiciones descritas en
[Seguridad y privacidad](SEGURIDAD.md), especialmente HTTPS, modo producción,
cookies seguras, credenciales propias, copias cifradas y restauración probada.
