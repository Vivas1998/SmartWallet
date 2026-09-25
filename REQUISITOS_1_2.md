# Requisitos de SmartWallet 1.2.0

**Inicio de definición:** 23 de septiembre de 2026
**Versión estable actual:** 1.2.0
**Estado:** publicada para uso local el 24 de septiembre de 2026

## 1. Alcance confirmado

SmartWallet 1.2.0 será una mejora funcional de la experiencia local y abarcará:

1. tema oscuro completo;
2. presupuesto restante por proyecto en `Mis proyectos`;
3. límites presupuestarios para subcategorías;
4. campos personalizados configurables por proyecto.

El despliegue en homelab y su seguridad asociada quedan fuera de este alcance y
se reservan para 2.0.0.

## 2. Tema y accesibilidad

- Cada usuario podrá elegir `Automático`, `Claro` u `Oscuro`.
- `Automático` seguirá la preferencia del dispositivo y será la opción inicial.
- La preferencia se guardará en la cuenta para mantenerse entre dispositivos.
- Habrá un control accesible en la cabecera y la configuración completa en el
  perfil.
- El tema se aplicará a toda la interfaz, incluidas las páginas de acceso y
  registro, los formularios, gráficos y el calendario.
- Antes de iniciar sesión se utilizará la preferencia del dispositivo.
- Los colores configurables de proyectos y categorías conservarán su identidad,
  pero su presentación se adaptará cuando sea necesario para mantener contraste
  AA.
- Ambos temas funcionarán desde 360 px, con teclado, foco visible y zoom al 200 %.

## 3. Presupuesto en `Mis proyectos`

- Cada proyecto activo mostrará el estado del mes natural actual.
- Se adopta la tarjeta equilibrada: presupuesto disponible destacado, barra de
  progreso y una línea secundaria con gasto neto, presupuesto total y porcentaje
  consumido.
- Los estados visuales conservarán los umbrales actuales: normal por debajo del
  80 %, advertencia desde el 80 % y peligro al alcanzar o superar el 100 %.
- Si el mes no tiene presupuesto, la tarjeta mostrará `Sin presupuesto para este
  mes`.
- Un proyecto archivado solo mostrará el presupuesto mensual si ya existe; la
  consulta nunca creará datos.
- No se sumarán ni mezclarán importes de proyectos diferentes.

## 4. Límites por subcategoría

- Los límites serán opcionales y solo podrán asignarse a subcategorías de gasto.
- El límite de la categoría principal seguirá englobando todos sus gastos.
- Un gasto con subcategoría consumirá simultáneamente el límite de la
  subcategoría y el de su categoría principal. Una devolución reducirá ambos
  consumos.
- La suma de límites secundarios no podrá superar el límite de la categoría
  principal. No será obligatorio repartirlo por completo y la diferencia se
  mostrará como importe sin asignar.
- Un gasto sin subcategoría consumirá únicamente el límite principal y aparecerá
  en el desglose como `Sin subcategoría`, sin límite independiente.
- Las subcategorías se mostrarán de forma desplegable dentro de su categoría. Se
  abrirán automáticamente las que tengan límites secundarios, advertencias o
  importes superados.
- Cada nivel utilizará los avisos visuales del 80 % y 100 %.
- Los límites secundarios se copiarán entre meses y respetarán las opciones
  `Solo este mes` y `Este mes y próximos`.
- Las subcategorías archivadas conservarán sus datos históricos en modo de solo
  lectura, pero no admitirán límites nuevos.
- Solo los propietarios modificarán los límites; los miembros podrán
  consultarlos.
- La auditoría del presupuesto incluirá todos los cambios de límites
  secundarios.

## 5. Campos personalizados

- Los campos pertenecerán exclusivamente a un proyecto. Solo sus propietarios
  podrán crearlos, ordenarlos, renombrarlos, archivarlos, reactivarlos y, cuando
  nunca hayan sido utilizados, eliminarlos definitivamente.
- Los miembros podrán rellenar y modificar valores en los movimientos.
- Los tipos iniciales serán texto corto, número decimal, fecha y sí/no. Los
  campos serán opcionales en 1.2.0.
- Cada definición indicará los tipos de movimiento a los que se aplica; de forma
  predeterminada se seleccionarán todos.
- Cada proyecto admitirá hasta diez campos activos. Los formularios los mostrarán
  en una sección desplegable `Información adicional` y el detalle del movimiento
  conservará sus valores sin añadir columnas permanentes a la lista principal.
- Las planificaciones y recurrencias admitirán los mismos campos. Sus valores se
  copiarán al movimiento real cuando se complete o genere.
- Un campo utilizado no podrá cambiar de tipo ni eliminarse. Al archivarlo dejará
  de aparecer en operaciones nuevas, pero conservará valores, exportación e
  historial y podrá reactivarse.
- Los filtros avanzados admitirán texto contenido, valor o intervalo numérico,
  fecha o intervalo y selección sí/no según el tipo del campo.
- La exportación CSV añadirá una columna por definición con el encabezado
  `Campo: Nombre`. Los números serán genéricos, no importes, y no se agregarán
  automáticamente en informes.
- Los campos no alterarán la detección de duplicados.
- La auditoría incluirá definiciones y cambios de valores. En proyectos
  archivados todo permanecerá en modo de solo lectura.
- La persistencia utilizará tablas de definiciones y valores; crear un campo no
  modificará físicamente la tabla de movimientos.

### 5.1. Ejemplos predeterminados

Los proyectos existentes y nuevos recibirán cinco campos opcionales aplicables a
gastos, sin valores iniciales:

| Campo | Tipo |
|---|---|
| Número de factura | Texto corto |
| Fecha de garantía | Fecha |
| Gasto deducible | Sí/no |
| Método de compra | Texto corto |
| Cubierto por el seguro | Sí/no |

Su finalidad será enseñar cómo puede utilizarse esta función. Como inicialmente
no tendrán valores, cada propietario podrá eliminarlos definitivamente si no le
resultan útiles. La creación de estos ejemplos nunca modificará movimientos ni
presupuestos existentes.

## 6. Criterios de aceptación

La versión 1.2.0 se considerará terminada cuando cumpla todos estos criterios:

1. La actualización desde 1.1.1 conserva íntegramente los datos existentes.
2. Los presupuestos creados antes de 1.2.0 siguen siendo válidos y comienzan sin
   límites de subcategoría.
3. Los usuarios existentes reciben la preferencia de tema `Automático`.
4. Consultar `Mis proyectos` no crea presupuestos ni otros registros de forma
   implícita.
5. Las tarjetas de proyectos evitan consultas repetidas por proyecto y mantienen
   un rendimiento estable al crecer la lista.
6. Todas las consultas, formularios, filtros y exportaciones respetan el
   aislamiento estricto entre proyectos.
7. La actualización se entrega mediante migraciones estándar de Laravel.
8. La DDL completa permite recrear directamente una instalación 1.2.0 limpia.
9. La estructura creada por la DDL coincide exactamente con la obtenida al
   ejecutar todas las migraciones.
10. Hay pruebas automatizadas para temas, tarjetas, presupuesto jerárquico,
    permisos, campos personalizados, recurrencias, filtros, CSV y auditoría.
11. Ambos temas se revisan manualmente desde 360 px, mediante teclado, con
    contraste AA y zoom al 200 %.
12. La versión declarada se mantuvo en 1.1.1 hasta completar todo el alcance y su
    validación; solo entonces se publicó 1.2.0.
