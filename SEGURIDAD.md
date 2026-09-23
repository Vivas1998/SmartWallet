# Seguridad y privacidad de SmartWallet 1.1

Última revisión: 23 de septiembre de 2026.

## Resultado

La versión 1.1 no presenta defectos críticos conocidos dentro de su alcance
local. La revisión incluyó código, configuración, rutas, permisos, sesiones,
dependencias y recorridos reales contra la base aislada `smartwallet_test`.

Composer y npm no comunicaron vulnerabilidades conocidas en las versiones
bloqueadas. La suite completa superó 111 pruebas y 860 aserciones.

## Modelo de riesgo aprobado

La versión 1.0 protege principalmente frente a:

- personas sin cuenta o sin sesión;
- usuarios que intenten acceder a proyectos ajenos mediante una URL o un
  identificador conocido;
- miembros que intenten realizar acciones reservadas a propietarios;
- peticiones falsificadas, inyección de contenido y exportaciones CSV maliciosas;
- exposición accidental de datos financieros en caché o dentro del entorno de
  pruebas.

Por decisión expresa, quedan fuera de este modelo el administrador con control
total del PC o de MySQL, el cifrado integral por proyecto y las copias automáticas
durante la etapa local. La pérdida del volumen Docker puede implicar la pérdida
total de los datos.

## Controles aplicados

### Identidad y sesiones

- Correo normalizado y único en la base de datos.
- Contraseñas de al menos 12 caracteres, almacenadas mediante el hash de Laravel.
- Respuesta genérica al solicitar recuperación para no revelar si la cuenta
  existe.
- Enlaces de recuperación temporales y de un solo uso.
- Limitación de intentos en registro, acceso, solicitud y aplicación del
  restablecimiento de contraseña.
- Regeneración del identificador de sesión al acceder y al cambiar datos
  sensibles; invalidación al cerrar sesión.
- Sesiones de servidor cifradas, cookie `HttpOnly` y `SameSite=Strict`, caducidad
  normal de dos horas y revocación desde el perfil.
- Cambio de correo protegido por la contraseña actual y opción de cerrar las
  demás sesiones al cambiar la contraseña.

### Autorización y privacidad entre proyectos

- Todas las rutas financieras exigen autenticación.
- Una Policy central valida la pertenencia activa y distingue propietario y
  miembro.
- Cada controlador comprueba que movimientos, planificaciones, cuentas,
  categorías, miembros, objetivos, recurrencias, etiquetas y avisos pertenecen
  al proyecto de la URL.
- Las consultas de paneles, informes, auditoría y exportaciones parten siempre
  del proyecto autorizado.
- El propietario creador no puede ser degradado ni retirado.
- No existe un panel global para consultar las finanzas de todos los proyectos.
- El nombre técnico del entorno y de la base solo aparece fuera de producción.

### Navegador y datos de salida

- Laravel protege los formularios mediante tokens CSRF.
- Blade escapa el contenido introducido por usuarios; no se encontraron salidas
  HTML sin escapar ni usos de `eval`, `innerHTML` o equivalentes.
- La política CSP limita orígenes, formularios, marcos, objetos, imágenes,
  scripts y conexiones. En desarrollo permite únicamente el origen exacto de
  Vite leído desde su archivo `hot`.
- Se envían `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin`, política de permisos restrictiva y aislamiento
  del contexto de apertura.
- Las respuestas autenticadas usan `no-store` para evitar conservar información
  financiera en cachés compartidas. HTTPS activa HSTS.
- La exportación CSV antepone una comilla a celdas que podrían interpretarse como
  fórmulas.
- Se desactivaron las rutas de lectura y escritura del almacenamiento privado
  porque 1.0 no incorpora archivos ni recibos.

### Entorno local

- Aplicación, Vite, MySQL y Mailpit publican puertos únicamente en
  `127.0.0.1`.
- `mysql_test` no publica puertos, usa memoria temporal y solo se inicia bajo
  demanda.
- La suite cancela la ejecución si el entorno no es `testing` o la base no
  termina en `_test`.
- `.env`, volcados SQL y registros quedan excluidos del repositorio.

## Evidencia de aceptación manual

La revisión real en `http://localhost:8011`, conectada exclusivamente a
`smartwallet_test`, confirmó:

- inicio y cierre de sesión correctos;
- creación de un proyecto privado sin errores bajo la CSP;
- carga de estilos y JavaScript desde los orígenes permitidos;
- rechazo con HTTP 419 de una escritura sin token CSRF;
- respuesta 403 al acceder con otra cuenta a un proyecto ajeno;
- respuesta 404 en la ruta de almacenamiento eliminada;
- presencia efectiva de CSP, protección contra marcos, `nosniff` y política de
  referente.

Todos los datos empleados fueron temporales y no se escribieron en `smartwallet`.

## Condiciones antes de desplegar en el homelab

La configuración actual está diseñada para desarrollo local y no debe exponerse
directamente a Internet. El despliegue futuro deberá completar, como mínimo:

1. utilizar `APP_ENV=production`, `APP_DEBUG=false` y recursos Vite compilados;
2. publicar solo mediante un proxy HTTPS confiable y activar
   `SESSION_SECURE_COOKIE=true`;
3. generar credenciales de MySQL exclusivas y robustas, custodiar `APP_KEY` y
   retirar los valores de desarrollo;
4. no publicar MySQL, Mailpit ni Vite fuera de la red Docker;
5. decidir si se cierra el registro con `REGISTRATION_ENABLED=false` tras crear
   las cuentas familiares;
6. implantar copias cifradas, retención, restauración probada, monitorización y
   alertas;
7. ejecutar Composer Audit, npm Audit, la suite y la compilación antes de cada
   actualización;
8. exigir MFA antes de cualquier exposición de datos financieros a Internet.

Estas condiciones pertenecen al futuro hito de homelab y no bloquean la versión
1.1 limitada al PC local.
