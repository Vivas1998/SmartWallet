# SmartWallet

**Nombre:** SmartWallet, definitivo
**Año objetivo:** 2026; adelantado respecto a la planificación inicial de 2027
**Versión:** 1.1.0
**Estado:** versión 1.1 finalizada para uso local
**Fecha de cierre de 1.1:** 23 de septiembre de 2026
**Inicio de la preparación:** 13 de septiembre de 2026
**Fecha máxima de la versión 1.0:** 31 de diciembre de 2026
**Presupuesto de desarrollo:** 0 €

## Objetivo

Crear una aplicación web privada, fiable y amigable para controlar gastos,
ingresos y presupuestos dentro de varios proyectos financieros independientes.
Un mismo usuario podrá separar, por ejemplo, la economía familiar de sus finanzas
personales y colaborar con otros miembros únicamente en los proyectos compartidos.

## Alcance inicial confirmado

- Todo el contenido de la aplicación exige iniciar sesión.
- Cada usuario puede crear y utilizar varios proyectos financieros independientes.
- Los proyectos pueden representar ámbitos familiares, personales u otros usos.
- Los proyectos admiten miembros y deben mantener sus datos aislados entre sí.
- Los miembros de un proyecto ven y pueden modificar toda su información
  financiera; solo los propietarios administran el proyecto y sus miembros.
- La aplicación registra gastos, ingresos, cuentas, transferencias, devoluciones,
  presupuestos mensuales compartidos y objetivos de ahorro.
- Las aportaciones a inversiones se registran como transferencias a una cuenta
  externa de solo aportaciones, sin confundirse con gastos, ingresos o valor de
  cartera.
- Cada proyecto tiene un saldo presupuestario mensual común: sus gastos reducen
  el importe disponible sin repartirlo entre los miembros.
- Incluye movimientos recurrentes, exportación CSV y varias etiquetas por
  movimiento.
- Incluye un calendario financiero selectivo con planificaciones puntuales,
  recurrencias proyectadas y movimientos manuales elegidos expresamente.
- Cada proyecto recibe categorías y subcategorías iniciales y puede adaptar su
  catálogo sin afectar a otros proyectos.
- El panel ofrece resúmenes mensuales y anuales para explicar dónde se gasta el
  dinero, conservar todos los años y comparar periodos.
- En escritorio, el resumen mensual ofrece tres indicadores inmediatos y
  diferenciados para ingresos, gastos y presupuesto disponible.
- Los proyectos se archivan y los movimientos eliminados pasan a una papelera
  recuperable.
- La interfaz será amigable y tomará TaskFlow como referencia visual, con identidad
  y colores propios.
- La información financiera personal tendrá protección reforzada.

SmartWallet es el nombre definitivo. La base 1.0 y la ampliación de calendario
de 1.1 están aprobadas e implementadas.

## Tecnologías confirmadas

- PHP 8.5 y Laravel 13 con MVC y vistas Blade.
- CSS organizado con BEM.
- JavaScript vanilla con bibliotecas de terceros permitidas y documentadas.
- MySQL 8.4 LTS como motor de base de datos.
- Vite para los recursos web.
- Docker Compose con contenedores propios e independientes de TaskFlow.

## Dependencias

- Lecciones e infraestructura común de [TaskFlow](../2026-taskflow/README.md).
- Base de datos propia sobre el patrón de [bases de datos web](../../infraestructura/2026-bases-de-datos/README.md).

## Entorno local

SmartWallet utiliza dos bases completamente separadas:

- smartwallet: base de desarrollo con volumen persistente. Aquí se conservarán los
  datos introducidos desde la aplicación.
- smartwallet_test: base exclusiva para pruebas automáticas. Solo se inicia bajo
  demanda, usa memoria temporal y se detiene al terminar.

El entorno normal se instala o inicia desde esta carpeta ejecutando
`.\scripts\start.ps1`. La aplicación quedará en <http://localhost:8010> y
Mailpit en <http://localhost:8026>. El script espera a que Laravel, Vite y MySQL
estén preparados antes de dar el arranque por finalizado.

Una instalación totalmente nueva también puede crear rápidamente la estructura
mediante `.\scripts\init-database-from-ddl.ps1`. El DDL consolidado reproduce el
esquema completo de 1.1.0 y se niega a ejecutarse sobre una base con tablas.

La guía completa de primera instalación, persistencia, puertos y diagnóstico está
en [Instalación local](INSTALACION_LOCAL.md). La preparación de Windows, traslado
del código y migración opcional de datos a otra máquina están en
[Instalación en otro equipo](INSTALACION_EN_OTRO_EQUIPO.md).

Las pruebas se ejecutan con .\\scripts\\test.ps1. El lanzador y PHPUnit
comprueban que el entorno sea testing y que el nombre de la base termine en
_test. Si no se cumplen ambas condiciones, cancelan la ejecución antes de
modificar datos.

## Próximo hito

Utilizar la versión 1.1 local y registrar mejoras surgidas del uso real. El
despliegue en homelab, sus copias y su monitorización constituirán un hito
posterior. SmartWallet es el nombre definitivo del producto.

## Implementado hasta ahora

- Registro, inicio y cierre de sesión, recordatorio de sesión y recuperación de
  contraseña sin revelar si una cuenta existe.
- Correo normalizado y único, contraseña mínima de 12 caracteres y limitación de
  intentos en las rutas de acceso.
- Perfil personal con iniciales, edición del nombre, cambio de correo protegido
  por la contraseña actual y cambio de contraseña con invalidación de enlaces de
  recuperación pendientes.
- Consulta de sesiones recientes con dispositivo, dirección IP y última
  actividad, revocación individual o conjunta y protección de la sesión actual.
- Opción al cambiar la contraseña para conservar o cerrar el resto de sesiones,
  además de renovación de los accesos recordados.
- Sesiones de servidor cifradas, cookie `HttpOnly` con `SameSite=Strict`, CSP,
  cabeceras defensivas y prohibición de caché en páginas financieras.
- Limitación de intentos en acceso, registro y recuperación, incluido el envío
  final de una nueva contraseña.
- Creación y listado de proyectos aislados entre sí.
- Asistente de creación en cuatro pasos —identidad, cuenta principal,
  presupuesto inicial y revisión— que conserva los datos entre pasos y no guarda
  nada hasta la confirmación final.
- Creación conjunta y atómica del proyecto, su cuenta principal, la plantilla de
  presupuesto y el presupuesto del mes en curso, evitando datos parciales si la
  validación falla.
- Portada `Mis proyectos` con identidad, miembros, rol y última actividad por
  proyecto, además de una actividad reciente común que identifica siempre el
  proyecto y no mezcla importes.
- Selector visible dentro de cada proyecto para cambiar directamente entre los
  espacios accesibles, conservando color, icono y estado archivado.
- Alta automática del creador como propietario y de una cuenta principal con su
  saldo inicial exacto en céntimos.
- Incorporación por correo de cuentas existentes, cambios de rol y retirada de
  acceso conservando la relación histórica.
- Protección permanente del propietario creador y administración exclusiva por
  propietarios.
- Configuración de proyecto con edición de nombre, descripción, color e icono
  exclusiva para propietarios, resumen estructural y datos regionales de 1.0
  visibles en modo de consulta.
- Archivado reversible del proyecto completo: conserva datos, informes y
  exportaciones en solo lectura, bloquea cualquier modificación y permite que un
  propietario lo reactive sin perder información.
- Las recurrencias esperan mientras el proyecto está archivado y recuperan sus
  apariciones vencidas tras reactivarlo; consultar un archivo no genera nuevos
  presupuestos ni modifica avisos.
- Catálogo independiente de 103 categorías y subcategorías iniciales por proyecto.
- Creación, edición, ordenación, archivado y reactivación de categorías con color
  e icono personalizables.
- Selectores dependientes que impiden mezclar categorías de gastos e ingresos o
  crear más de dos niveles.
- Presupuesto mensual común, editable solo por propietarios, con límite total y
  límites opcionales en categorías principales.
- Plantillas de presupuesto con aplicación solo al mes elegido o al mes actual y
  los siguientes, sin arrastrar sobrantes.
- Cierre mensual no bloqueante con presupuesto inicial, gasto neto, sobrante,
  ingresos, balance y saldos reales de las cuentas al último día del mes.
- Acción opcional `Destinar sobrante`, exclusiva para propietarios, mediante una
  transferencia real hacia ahorro o inversión y con vínculo opcional a un objetivo.
- Seguimiento separado de cuánto sobró, cuánto se destinó a cada finalidad y cuánto
  continúa disponible, sin alterar el resultado histórico del presupuesto.
- Recalculo del cierre tras correcciones, edición, papelera o restauración, con
  advertencia si el importe destinado termina superando el sobrante corregido.
- Registro manual de gastos e ingresos por todos los miembros, con cuenta,
  categoría, subcategoría, responsable y notas.
- Aviso no bloqueante de posible duplicado cuando coinciden categoría,
  subcategoría, fecha e importe.
- Saldos de cuentas derivados de sus movimientos y tratamiento específico de la
  deuda de tarjeta de crédito.
- Resumen mensual con indicadores circulares de ingresos, gastos y presupuesto
  disponible, además de últimos movimientos.
- Listado mensual con filtros por tipo, cuenta, categoría, miembro y concepto.
- Edición de cualquier movimiento por propietarios y miembros, con recálculo
  inmediato de sus apuntes y datos mensuales.
- Transferencias entre cuentas, pagos de tarjeta y aportaciones o retiradas de la
  cuenta externa de inversión sin alterar ingresos, gastos ni presupuesto.
- Devoluciones parciales vinculadas al gasto original, con control para que su
  suma nunca lo supere y cálculo de gasto neto.
- Pantalla de cuentas para crear, editar, archivar y reactivar cuentas; resumen
  separado de liquidez, ahorro, capital aportado y deuda de tarjetas.
- Papelera recuperable durante 30 días; los movimientos retirados dejan de afectar
  los cálculos y el programador los elimina definitivamente al vencer el plazo.
- Auditoría inalterable desde la interfaz para proyectos, movimientos,
  presupuestos, cuentas y miembros, con autor y valores anteriores y posteriores.
- Series recurrentes diarias, semanales, mensuales y anuales para gastos,
  ingresos, transferencias y aportaciones a inversión, con fecha final opcional.
- Generación idempotente por serie y fecha, recuperación de todas las apariciones
  vencidas al arrancar y aviso revisable con su detalle.
- Edición independiente de una aparición, edición del futuro de una serie, pausa,
  reanudación y omisión explícita del siguiente movimiento.
- Calendario financiero selectivo que proyecta recurrencias, muestra todas las
  planificaciones con vencimiento y solo incorpora otros movimientos cuando se
  activa expresamente `Mostrar en el calendario`.
- Planificaciones puntuales que no alteran cuentas, presupuestos ni informes
  hasta registrarse como realizadas, conservando vencimiento, fecha real y
  puntualidad.
- Cuadrícula mensual con detalle diario en escritorio, agenda cronológica en
  móvil, navegación entre meses y filtros por estado, tipo, cuenta, categoría y
  miembro.
- Resumen del calendario con gasto real, pendientes, ingresos previstos y
  presupuesto disponible real y estimado, sin ampliar el límite con ingresos.
- Objetivos vinculados a cuentas de ahorro o inversión, con fecha opcional,
  progreso superior al 100 % y conservación histórica al archivarlos.
- Aportaciones y retiradas mediante transferencias reales; el progreso no toma
  automáticamente todo el saldo de la cuenta y se actualiza al editar, retirar o
  restaurar el movimiento vinculado.
- Varias etiquetas por movimiento y serie recurrente, con creación disponible
  para todos los miembros y administración, archivado y fusión por propietarios.
- Filtro por etiqueta en el historial y conservación de etiquetas archivadas en
  los movimientos antiguos sin permitir utilizarlas en operaciones nuevas.
- Exportación CSV del proyecto completo o de la vista mensual filtrada, con
  formato español, UTF-8 compatible con Excel y protección frente a fórmulas.
- Exportación separada de la papelera exclusiva para propietarios; la exportación
  normal nunca incluye movimientos retirados.
- Informes independientes `Mensual`, `Anual` y `Comparar`, recalculados desde los
  movimientos activos y accesibles para todos los miembros del proyecto.
- Resumen mensual con presupuesto, gasto neto, devoluciones, ingresos, balance,
  saldo presupuestario, categorías principales y comparación automática con el
  mes anterior y el mismo mes del año previo.
- Informe anual con los doce meses, evolución de ingresos y gastos, clasificación
  por categorías y separación visible entre resultados reales y planificación
  futura del año en curso.
- Evolución y totales separados para transferencias, ahorro e inversión, evitando
  que estas operaciones se confundan con ingresos o gastos de consumo.
- Comparación visual y numérica de hasta cinco meses o años, con variación respecto
  al primer periodo, desglose por categorías y filtros por cuenta, categoría,
  miembro y etiqueta.
- Acceso desde periodos y categorías al historial de movimientos correspondiente,
  incluido el filtrado por intervalos exactos de fechas para años completos.
- Interfaz BEM adaptable desde 360 px y recursos servidos correctamente desde el
  entorno Docker.
- Auditoría de 18 pantallas públicas y privadas en móvil, tableta y escritorio,
  sin desbordamientos globales ni errores estructurales de accesibilidad.
- Navegación completa por teclado con foco visible y enlace para saltar al
  contenido principal, además de estado activo accesible en menús e informes.
- Indicadores de progreso de presupuestos y objetivos con semántica accesible y
  paleta corregida para mantener contraste AA.
- Redistribución validada a 360, 768, 1024 y 1440 px, junto a la equivalencia de
  una ampliación al 200 % mediante un área CSS de 720 px.
- Inicio y parada asistidos desde PowerShell, con comprobaciones de salud para
  Laravel, Vite y MySQL y conservación explícita del volumen de desarrollo.
- Instalación limpia documentada, sin usuarios ficticios, con alta inicial desde
  la aplicación y explicación de puertos, correo local y diagnóstico.
- Recorrido limpio verificado desde una copia sin configuración ni dependencias:
  clave propia, 17 migraciones, cero usuarios iniciales y persistencia correcta
  después de detener y volver a iniciar todos los servicios.
- Separación documentada y verificable entre la base persistente `smartwallet` y la
  base temporal `smartwallet_test`.
- Rutas privadas de almacenamiento desactivadas mientras 1.0 no admita archivos,
  reduciendo la superficie expuesta.
- Revisión de permisos, recursos anidados, CSRF, salida HTML, SQL dinámico,
  secretos y exportación CSV, sin defectos críticos conocidos.
- Composer Audit y npm Audit sin vulnerabilidades conocidas en las dependencias
  bloqueadas.
- Banco de rendimiento reproducible y protegido para generar 100.000 movimientos
  únicamente en `smartwallet_test`, con objetivos máximos por recorrido.
- Índices específicos para históricos activos, filtros, devoluciones y papelera;
  el listado mensual medido bajó de unos 411 ms a 5,6 ms.
- Panel mensual consolidado en dos consultas y gasto neto por categoría del
  presupuesto resuelto en una única lectura agrupada.
- Ocho recorridos de escala validados, incluidos informe anual, comparación de
  cinco años, saldos y recorrido completo para exportación CSV.
- Generador de datos de desarrollo compatible con el correo normalizado y con una
  contraseña de demostración que respeta la longitud mínima.
- Migraciones aplicadas en la base persistente de desarrollo.
- 111 pruebas automáticas, con 860 comprobaciones, ejecutadas exclusivamente contra
  `smartwallet_test`.

## Documentación

- [Requisitos aprobados](REQUISITOS.md)
- [Requisitos de la versión 1.1](REQUISITOS_1_1.md)
- [Experiencia de usuario](EXPERIENCIA_USUARIO.md)
- [Sistema de diseño](SISTEMA_DISENO.md)
- [Modelo de datos propuesto](MODELO_DATOS.md)
- [Arquitectura propuesta](ARQUITECTURA.md)
- [Instalación local](INSTALACION_LOCAL.md)
- [Instalación en otro equipo](INSTALACION_EN_OTRO_EQUIPO.md)
- [Pruebas de rendimiento y escala](PRUEBAS_RENDIMIENTO.md)
- [Pruebas de accesibilidad y aceptación](PRUEBAS_ACCESIBILIDAD.md)
- [Accesibilidad y adaptación del calendario 1.1](PRUEBAS_ACCESIBILIDAD_1_1.md)
- [Seguridad y privacidad](SEGURIDAD.md)
- [Matriz de aceptación de 1.0](ACEPTACION_1_0.md)
- [Preparación de la liberación 1.0](LIBERACION_1_0.md)
- [Matriz de aceptación de 1.1](ACEPTACION_1_1.md)
- [Preparación de la liberación 1.1](LIBERACION_1_1.md)
- [Registro de cambios](CHANGELOG.md)
- [Registro de decisiones](DECISIONES.md)
- [Mejoras y futuras versiones](FUTURAS_VERSIONES.md)

Esta ficha se actualizará al cerrar hitos, tras cambios relevantes, cuando se
solicite y antes de la finalización del proyecto.
