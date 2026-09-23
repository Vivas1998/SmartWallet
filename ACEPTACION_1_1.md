# Matriz de aceptación de SmartWallet 1.1

Revisión: 23 de septiembre de 2026.

## Resultado general

El calendario financiero selectivo y las planificaciones puntuales cumplen el
alcance aprobado para la versión local. La versión 1.1 queda finalizada sin
defectos críticos conocidos y sin mezclar previsiones con la contabilidad real.

| Criterio aprobado | Estado | Evidencia principal |
|---|---|---|
| Mostrar únicamente recurrencias, vencimientos y movimientos seleccionados | Cumple | Pruebas de lectura mensual y recorrido real con un movimiento manual visible y una planificación. |
| Añadir o retirar movimientos manuales del calendario | Cumple | Formularios de todos los tipos y pruebas de creación y edición. |
| Proyectar recurrencias futuras sin contabilizarlas | Cumple | Servicio de calendario compartido y pruebas de anclas mensuales, años bisiestos y deduplicación. |
| Mantener las planificaciones fuera de saldos, presupuestos e informes | Cumple | Pruebas de gestión y resumen real frente a estimado. |
| Registrar una planificación como movimiento real sin duplicar el evento | Cumple | Pruebas de conversión, vínculo único y aviso de posible duplicado. |
| Conservar vencimiento, fecha efectiva y puntualidad | Cumple | Pruebas de realización anticipada, puntual y tardía. |
| Distinguir previsto, hoy, vencido, realizado, cancelado y omitido | Cumple | Estados con texto, forma visual y filtros combinables. |
| Separar importes reales y estimados | Cumple | Prueba automática y aceptación con 40,00 € reales, 125,50 € pendientes y 165,50 € previstos. |
| Respetar permisos, auditoría, archivado y aislamiento | Cumple | Policies, historial y pruebas con miembros, personas ajenas y proyectos archivados. |
| Ofrecer cuadrícula de escritorio y agenda móvil accesibles | Cumple | Revisión en 1440, 720 y 360 px, semántica de tabla, foco y ausencia de desbordamiento global. |
| Actualizar una instalación 1.0 sin incorporar movimientos antiguos | Cumple | Nueva columna desactivada por defecto y migración aplicada sobre la base persistente. |
| Superar suite automática y revisión manual sin errores críticos | Cumple | 111 pruebas, 860 aserciones y recorrido aislado en `smartwallet_test`. |

## Recorrido funcional de aceptación

La revisión se ejecutó en una instancia temporal del puerto `8011`, con
`APP_ENV=testing` y la base en memoria `smartwallet_test`. Se creó un proyecto de
1.000,00 € de presupuesto mensual y se comprobó lo siguiente:

- un gasto manual de 40,00 € apareció únicamente después de activar `Mostrar en
  el calendario`;
- una planificación de 125,50 € apareció automáticamente en su vencimiento;
- el resumen mostró 960,00 € disponibles reales y 834,50 € disponibles
  estimados;
- el filtro `Previsto` ocultó el movimiento realizado sin alterar el resumen
  financiero global;
- la cuadrícula y la agenda enlazaron cada evento con su origen correcto.

Los datos de este recorrido se eliminaron al detener MySQL de pruebas. La base
persistente `smartwallet` no recibió datos de aceptación.

## Comprobaciones de cierre

- `scripts/release-check.ps1`: finalizado con código de salida 0.
- Laravel Pint: 136 archivos correctos.
- PHPUnit: 111 pruebas y 860 aserciones superadas.
- Composer Audit: sin avisos de vulnerabilidades conocidas.
- npm Audit de producción: cero vulnerabilidades conocidas.
- Vite: compilación de producción correcta.
- Base persistente: 17 migraciones aplicadas.
- Aplicación, MySQL, Mailpit y Vite saludables; programador iniciado.
- Entrega identificada como `1.1.0` en la aplicación, configuración y paquetes.

## Cierre

SmartWallet `1.1.0` queda publicada para uso local el 23 de septiembre de 2026.
El despliegue en homelab, las copias automáticas, las notificaciones externas y
la sincronización con calendarios de terceros permanecen fuera de este alcance.
