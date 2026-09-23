# Registro de cambios

Este documento sigue el formato de [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto utiliza [versionado semántico](https://semver.org/lang/es/).

## [1.1.0] — 2026-09-23

Primera evolución funcional de SmartWallet, centrada en planificar operaciones
sin mezclarlas con la contabilidad real.

### Añadido

- Calendario financiero selectivo por proyecto con cuadrícula mensual en
  escritorio y agenda cronológica en móvil.
- Planificaciones puntuales de gastos, ingresos, transferencias y aportaciones,
  con vencimiento, cancelación y conversión controlada en movimientos reales.
- Proyección de apariciones recurrentes futuras sin crearlas ni contabilizarlas
  antes de su fecha.
- Opción `Mostrar en el calendario` para movimientos manuales, desactivada por
  defecto y disponible para todos sus tipos.
- Estados previsto, hoy, vencido, realizado, cancelado y omitido, además de
  puntualidad anticipada, puntual o tardía.
- Filtros de calendario por estado, tipo, cuenta, categoría y miembro.
- Resumen mensual separado entre datos reales y estimados, con gastos e ingresos
  pendientes y presupuesto disponible previsto.

### Cambiado

- El cálculo de fechas recurrentes se centraliza en un único servicio compartido
  por el generador automático y el calendario.
- La navegación del proyecto incorpora `Calendario` entre movimientos y
  presupuesto.
- Etiquetas, auditoría y permisos admiten planificaciones puntuales manteniendo
  el aislamiento estricto entre proyectos.
- El DDL consolidado permite recrear una base 1.1.0 vacía con sus 31 tablas,
  índices, claves foráneas y las 17 migraciones registradas.

### Validado

- 111 pruebas automáticas y 860 aserciones superadas en `smartwallet_test`.
- 136 archivos PHP aceptados por Laravel Pint y compilación Vite de producción
  completada.
- Migración desde 1.0 comprobada sin incorporar automáticamente movimientos
  antiguos al calendario.
- DDL importado desde cero y comparado con el esquema Laravel: 31 tablas, 277
  columnas, 219 entradas de índices y 84 claves foráneas idénticas.
- Interfaz del calendario revisada desde 360 px, con teclado, foco visible,
  contraste AA y redistribución equivalente al zoom del 200 %.

## [1.0.0] — 2026-09-19

Primera versión completa para uso local. El alcance funcional acordado, el ensayo
final con Docker Desktop y los controles de aceptación quedan cerrados.

El producto adopta `SmartWallet` como nombre definitivo. También se renombran los
identificadores técnicos locales sin perder los datos existentes.

### Añadido

- Registro, acceso, cierre de sesión, recuperación de contraseña y gestión del
  perfil y de las sesiones activas.
- Proyectos financieros aislados con propietarios, miembros, archivado y
  propietario creador protegido.
- Cuentas, gastos, ingresos, transferencias, devoluciones y cuenta externa de
  aportaciones a inversiones.
- Categorías y subcategorías propias de cada proyecto, etiquetas y aviso de
  posibles movimientos duplicados.
- Presupuestos mensuales editables, copia automática sin arrastre, cierre de mes
  y destino del sobrante a ahorro o inversión.
- Panel mensual, resumen anual y comparación entre meses y años.
- Objetivos de ahorro, movimientos recurrentes y recuperación idempotente de
  apariciones vencidas al volver a iniciar la aplicación.
- Exportación CSV, papelera de 30 días y auditoría de operaciones sensibles.
- Entorno Docker Compose con bases persistente y de pruebas completamente
  separadas, programador de tareas y correo local mediante Mailpit.
- Guía completa para instalar la aplicación en otro equipo y para trasladar de
  forma controlada una base de datos existente.
- Repositorio oficial con instalación mediante `git clone` y exclusión explícita
  de secretos, bases de datos y artefactos generados.

### Seguridad

- Sesiones cifradas, cookie `HttpOnly` con `SameSite=Strict`, limitación de
  intentos y protección CSRF.
- CSP, HSTS en HTTPS, cabeceras defensivas y prohibición de caché en páginas
  financieras autenticadas.
- Autorización por proyecto, rutas privadas de archivos desactivadas y
  neutralización de fórmulas al exportar CSV.

### Corregido

- La política CSP permite ahora cargar desde el origen local de Vite tanto el
  JavaScript como la hoja de estilos durante el desarrollo. En producción los
  estilos continúan restringidos al propio origen.

### Validado

- 95 pruebas automáticas y 664 aserciones superadas.
- Auditorías de Composer y npm sin vulnerabilidades conocidas.
- Rendimiento comprobado con 100.000 movimientos por proyecto.
- Dieciocho pantallas revisadas desde 360 px, con teclado, foco visible,
  contraste AA y redistribución equivalente al zoom del 200 %.
