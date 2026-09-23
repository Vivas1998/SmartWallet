# Requisitos de SmartWallet 1.1

**Fecha de definición:** 22 de septiembre de 2026
**Estado:** implementado y publicado como `1.1.0` el 23 de septiembre de 2026
**Objetivo:** incorporar un calendario financiero selectivo sin mezclar
planificación con contabilidad real.

## 1. Alcance

La versión 1.1 añadirá una sección `Calendario` dentro de cada proyecto. No será
un listado alternativo de todos los movimientos: mostrará únicamente operaciones
con relevancia temporal explícita.

El calendario combinará tres orígenes:

1. apariciones previstas y procesadas de series recurrentes;
2. planificaciones puntuales que tengan fecha de vencimiento;
3. movimientos manuales cuya opción `Mostrar en el calendario` esté activada.

No existirá una opción para cargar indiscriminadamente todos los movimientos.

## 2. Reglas de visibilidad

- Todo movimiento recurrente se mostrará automáticamente, sin casilla opcional.
- Toda planificación con vencimiento se mostrará automáticamente.
- Un movimiento manual normal permanecerá fuera del calendario por defecto.
- Los formularios de alta y edición de movimientos manuales incluirán la casilla
  `Mostrar en el calendario`.
- La casilla podrá modificarse posteriormente y estará disponible para gastos,
  ingresos, transferencias, aportaciones, devoluciones y pagos de tarjeta.
- Los movimientos existentes al instalar 1.1 conservarán el valor desactivado.
- Un movimiento vinculado a una recurrencia o planificación no se mostrará como
  un segundo evento independiente.

## 3. Planificaciones puntuales

Una planificación representa una operación futura todavía no contabilizada.
Admitirá gastos, ingresos, transferencias y aportaciones a ahorro o inversión.
No se planificarán devoluciones independientes porque deben continuar vinculadas
a un gasto real.

Cada planificación podrá guardar:

- tipo, concepto e importe previsto;
- fecha de vencimiento;
- categoría y subcategoría cuando correspondan;
- cuentas de origen y destino cuando correspondan;
- pagador, etiquetas y notas;
- estado y movimiento real vinculado;
- autoría de creación y última modificación.

Mientras esté pendiente no modificará cuentas, presupuesto consumido, informes
reales ni objetivos de ahorro.

La acción `Registrar como realizado` abrirá el formulario de movimiento con los
datos completados. El usuario podrá corregir importe, fecha, cuentas, categoría o
notas antes de confirmar. La validación normal de movimientos, incluido el aviso
de posible duplicado, seguirá aplicándose.

## 4. Fechas y puntualidad

El evento de una planificación permanecerá colocado en su fecha de vencimiento,
aunque el movimiento real se registre otro día.

Después de realizarlo mostrará la fecha efectiva y uno de estos resultados:

- `Anticipado`, si se realizó antes del vencimiento;
- `Puntual`, si ambas fechas coinciden;
- `Tardío`, si se realizó después del vencimiento.

Un elemento pendiente cuya fecha ya haya pasado se mostrará como `Vencido` hasta
que se realice o cancele.

## 5. Integración con recurrencias

- Las próximas apariciones se calcularán para el mes consultado sin crear
  movimientos futuros en la base de datos.
- Al llegar la fecha, el proceso automático existente creará el movimiento y el
  mismo evento pasará a estado `Realizado`.
- Las fechas omitidas se conservarán como `Omitido`.
- Las series pausadas no producirán previsiones activas durante la pausa.
- Las apariciones ya realizadas seguirán visibles aunque la serie termine.
- La fecha programada seguirá siendo la posición del evento y cualquier fecha
  efectiva distinta se mostrará en su detalle.
- El cálculo de fechas se extraerá a un servicio compartido por el calendario y
  el generador automático para evitar reglas duplicadas.

## 6. Estados

Los eventos podrán presentarse como:

- `Previsto`;
- `Hoy`;
- `Vencido`;
- `Realizado`;
- `Cancelado`;
- `Omitido`.

Las cancelaciones y omisiones se conservarán para mantener el historial y no
afectarán a saldos ni presupuestos.

## 7. Resumen y presupuesto

El calendario mostrará por separado:

- gastos realizados seleccionados o vinculados;
- gastos pendientes;
- gasto previsto total;
- ingresos previstos;
- presupuesto disponible real;
- presupuesto disponible estimado al finalizar el mes.

Las fórmulas serán:

```text
Disponible real = presupuesto - gastos realizados
Disponible estimado = presupuesto - gastos realizados - gastos pendientes
```

Los ingresos previstos no ampliarán automáticamente el presupuesto. Las cifras
estimadas se identificarán claramente y no se mezclarán con los informes reales.

## 8. Interfaz

- La navegación del proyecto incorporará `Calendario` entre `Movimientos` y
  `Presupuesto`.
- En escritorio se utilizará una cuadrícula mensual con un panel de detalle para
  el día seleccionado.
- En móvil se utilizará una agenda cronológica por días.
- Se podrá navegar entre meses y volver al mes actual.
- Los filtros incluirán estado, tipo, cuenta, categoría y miembro.
- Cada evento enlazará a su planificación, serie recurrente o movimiento real.
- La interfaz mantendrá navegación por teclado, foco visible, contraste AA,
  compatibilidad desde 360 px y zoom al 200 %.

## 9. Permisos y auditoría

- Todos los miembros activos podrán consultar el calendario.
- Propietarios y miembros podrán crear, editar, cancelar y completar
  planificaciones puntuales.
- Solo los propietarios administrarán las series recurrentes.
- Los proyectos archivados mostrarán el calendario en modo de solo lectura.
- La creación, edición, cancelación y realización de planificaciones quedará
  registrada en el historial del proyecto.
- Se mantendrá el aislamiento estricto entre proyectos.

## 10. Fuera del alcance de 1.1

- Correos, SMS y notificaciones del navegador o del sistema.
- Sincronización con calendarios externos.
- Conexión bancaria.
- Predicciones basadas en datos históricos.
- Conversión automática de una planificación vencida en movimiento real.

Los avisos de 1.1 serán exclusivamente visuales dentro de SmartWallet.

## 11. Criterios de aceptación

La versión 1.1 se considerará funcionalmente completa cuando:

1. solo aparezcan recurrencias, vencimientos y movimientos seleccionados;
2. un movimiento manual pueda añadirse y retirarse del calendario al crearlo o
   editarlo;
3. las recurrencias futuras se proyecten sin contabilizarlas anticipadamente;
4. una planificación pendiente no altere saldos, presupuestos ni informes;
5. `Registrar como realizado` cree un movimiento válido y evite eventos
   duplicados;
6. el evento conserve el vencimiento y muestre fecha efectiva y puntualidad;
7. vencidos, cancelados y omitidos tengan estados inequívocos;
8. el resumen separe importes reales y estimados;
9. permisos, auditoría, archivado y aislamiento entre proyectos se respeten;
10. la cuadrícula de escritorio y la agenda móvil sean accesibles;
11. la instalación actualice los datos existentes sin incorporarlos
    automáticamente al calendario;
12. la suite automática y la revisión manual no detecten errores críticos.

## 12. Bloques de implementación

- [x] Migraciones, modelos, permisos y auditoría.
- [x] Servicio común de cálculo de recurrencias y lectura mensual del calendario.
- [x] Gestión de planificaciones puntuales y conversión a movimiento real.
- [x] Casilla de visibilidad en los movimientos manuales.
- [x] Interfaz mensual de escritorio, agenda móvil, filtros y estados.
- [x] Resumen real frente a estimado.
- [x] Pruebas funcionales, accesibilidad, actualización documental y liberación
  de `1.1.0`.
