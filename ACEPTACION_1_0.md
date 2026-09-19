# Matriz de aceptación de SmartWallet 1.0

Revisión: 17 de septiembre de 2026.

## Resultado general

Los criterios funcionales, técnicos y de seguridad acordados para la versión
local están cubiertos. La versión 1.0 queda finalizada, sin defectos críticos
conocidos.

| Criterio aprobado | Estado | Evidencia principal |
|---|---|---|
| Registro, acceso, cierre, recordatorio y recuperación | Cumple | Suite de autenticación y recorrido real de sesión. |
| Crear, compartir, administrar y archivar proyectos | Cumple | Pruebas de proyectos, miembros, roles y configuración. |
| Impedir acceso mediante URL o identificador a personas ajenas | Cumple | Policies, recursos anidados, pruebas automáticas y respuesta 403 real. |
| Permisos diferenciados de propietario y miembro | Cumple | Pruebas de cada área y propietario creador protegido. |
| Saldos exactos en gastos, ingresos, transferencias y devoluciones | Cumple | Pruebas de movimientos y apuntes de cuenta. |
| Presupuesto mensual reducido solo por gasto neto | Cumple | Pruebas de presupuestos, devoluciones y cierre mensual. |
| Panel e informes sin mezclar proyectos | Cumple | Informes mensual, anual, comparador y aislamiento de portada. |
| Conservar historial y comparar meses y años | Cumple | Pruebas de informes y presupuestos históricos. |
| Recurrencias idempotentes y editables | Cumple | Pruebas de generación, recuperación, pausa y edición. |
| CSV limitado al proyecto autorizado | Cumple | Pruebas de exportación, filtros, papelera y neutralización de fórmulas. |
| Papelera, restauración y auditoría | Cumple | Pruebas de ciclo de vida, purga y valores auditados. |
| Interfaz adaptable y accesible | Cumple | Auditoría de 18 pantallas y `PRUEBAS_ACCESIBILIDAD.md`. |
| Instalación limpia y aislada con Docker Compose | Cumple | Recorrido limpio documentado y bases independientes. |
| Suite automática sobre una base independiente | Cumple | 95 pruebas, 664 aserciones y guardas de `smartwallet_test`. |
| Operaciones críticas con 100.000 movimientos | Cumple | Ocho recorridos dentro de los objetivos de `PRUEBAS_RENDIMIENTO.md`. |
| Sin defectos críticos y operación local documentada | Cumple | Revisión de seguridad, instalación, arquitectura y documentos de prueba. |

## Comprobaciones de cierre

- Laravel Pint: 121 archivos correctos.
- PHPUnit: 95 pruebas y 664 aserciones superadas.
- Composer Audit: cero avisos de vulnerabilidad conocidos.
- npm Audit de producción: cero vulnerabilidades conocidas.
- Vite: compilación final de producción correcta.
- Base persistente: 16 migraciones aplicadas antes de la revisión final.
- Base de pruebas: utilizada exclusivamente para automatización y aceptación.
- Entrega identificada como `1.0.0`, con changelog y procedimiento de
  liberación reproducible.

## Cierre de liberación

La comprobación final de arranque, migraciones, formato, compilación, pruebas y
salud de los servicios se superó. La validación final de uso fue confirmada el 19
de septiembre de 2026 y la candidata se promovió a `1.0.0`.

`SmartWallet` es el nombre definitivo de la aplicación desde el 19 de septiembre
de 2026.

Las copias automáticas, el despliegue en homelab y la restauración remota siguen
fuera del alcance local aprobado.
