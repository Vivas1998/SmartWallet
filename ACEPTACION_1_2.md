# Matriz de aceptación de SmartWallet 1.2

Revisión: 24 de septiembre de 2026.

## Resultado general

El tema configurable, las tarjetas presupuestarias de proyectos, los límites por
subcategoría y los campos personalizados cumplen el alcance aprobado para la
versión local. La implementación de 1.2.0 queda validada sin defectos críticos
conocidos y preparada para el bloque independiente de publicación.

| Criterio aprobado | Estado | Evidencia principal |
|---|---|---|
| Actualizar desde 1.1.1 sin perder datos | Cumple | Las dos migraciones nuevas se aplicaron sobre la base persistente y las 19 migraciones figuran ejecutadas. |
| Mantener válidos los presupuestos anteriores | Cumple | Los límites secundarios son opcionales y las pruebas parten también de presupuestos sin subcategorías configuradas. |
| Iniciar usuarios existentes en tema automático | Cumple | Migración con valor predeterminado, pruebas de perfil y recorrido real en los tres modos. |
| No crear presupuestos al consultar `Mis proyectos` | Cumple | Prueba de tarjeta activa y archivada sin escrituras implícitas. |
| Mantener estable el número de consultas de las tarjetas | Cumple | Prueba automática con varios proyectos y un número fijo de consultas. |
| Respetar aislamiento, permisos y proyectos archivados | Cumple | Pruebas de propietarios, miembros, personas ajenas y relaciones pertenecientes a otros proyectos. |
| Entregar cambios mediante migraciones de Laravel | Cumple | Migraciones de preferencia de tema y tablas de campos personalizados aplicadas de forma estándar. |
| Recrear una instalación limpia mediante la DDL | Cumple | DDL consolidada con 33 tablas y las 19 migraciones registradas. |
| Igualar exactamente DDL y migraciones | Cumple | Comparación de tablas, columnas, índices y claves foráneas con resultados idénticos. |
| Cubrir las funciones nuevas mediante pruebas automáticas | Cumple | 124 pruebas y 985 aserciones superadas. |
| Funcionar desde 360 px, con teclado, contraste AA y zoom al 200 % | Cumple | Revisión manual en 360, 720 y 1440 px y documento específico de accesibilidad. |
| Conservar 1.1.1 hasta terminar toda la validación | Cumple | El número se cambió a 1.2.0 únicamente después de cerrar la aceptación. |

## Recorrido funcional de aceptación

La revisión se ejecutó en una instancia temporal del puerto `8011`, con
`APP_ENV=testing` y la base efímera `smartwallet_test`. Se creó el proyecto
`Casa 1.2`, una cuenta principal de 2.500,00 € y un presupuesto mensual de
1.000,00 €. Se comprobó lo siguiente:

- el proyecto recibió los cinco campos de ejemplo aprobados, vacíos y
  eliminables;
- se creó el campo numérico `Consumo kWh`, aplicable solamente a gastos;
- un gasto de 150,00 € guardó correctamente los seis valores adicionales y los
  mostró en su detalle;
- los filtros combinados por número de factura y rango numérico devolvieron el
  movimiento esperado;
- cambiar la categoría limitó inmediatamente las subcategorías disponibles a
  las que pertenecían a esa categoría;
- un límite de 500,00 € en `Alimentación` y de 200,00 € en `Supermercado`
  mostró respectivamente el consumo principal, un 75 % en la subcategoría y
  300,00 € sin asignar;
- la tarjeta del proyecto actualizó el disponible a 850,00 €, el gasto a
  150,00 € y el consumo al 15 %;
- las preferencias clara y oscura se conservaron al navegar.

Los datos del recorrido pertenecían únicamente a `smartwallet_test`. El
contenedor de aceptación se verificó y eliminó al terminar; la base persistente
`smartwallet` no recibió esos datos.

## Comprobaciones de cierre

- `scripts/release-check.ps1`: finalizado con código de salida 0 antes y después
  de actualizar coordinadamente la entrega a 1.2.0.
- Laravel Pint: 148 archivos correctos.
- PHPUnit: 124 pruebas y 985 aserciones superadas.
- Composer Audit: sin avisos de vulnerabilidades conocidas.
- npm Audit de producción: cero vulnerabilidades conocidas.
- Vite: compilación de producción correcta.
- Base persistente: 19 migraciones aplicadas.
- DDL y migraciones: 33 tablas y estructuras de columnas, índices y claves
  foráneas equivalentes.
- Aplicación, MySQL, Mailpit y Vite saludables; programador iniciado.
- Navegador: sin errores ni advertencias de consola durante la aceptación.

## Cierre

El alcance funcional, la validación y la publicación local de SmartWallet 1.2.0
quedan completos el 24 de septiembre de 2026. La preparación de la entrega se
documenta en [Liberación local de SmartWallet 1.2](LIBERACION_1_2.md).
