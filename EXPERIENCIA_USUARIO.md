# Experiencia de usuario de SmartWallet

**Fecha:** 13 de septiembre de 2026
**Estado:** base de navegación aprobada para la versión 1.0
**Nombre:** SmartWallet, definitivo

## 1. Principios

- Cada pantalla deja claro el proyecto y el periodo activos.
- Los proyectos nunca se suman ni mezclan.
- Los importes importantes se explican con texto, no solo mediante colores o
  gráficos.
- La experiencia mantiene los patrones ya aprendidos en TaskFlow y utiliza verde
  petróleo y menta como identidad propia.
- Todas las funciones se diseñan desde 360 px, con teclado, foco visible,
  contraste AA y zoom al 200 %.

## 2. Entrada y proyectos

Tras iniciar sesión se abre `Mis proyectos`. Cada tarjeta muestra identidad,
descripción, miembros, rol y última actividad, pero no importes agregados. Debajo
puede aparecer actividad reciente indicando siempre a qué proyecto pertenece.

Si aún no existe ningún proyecto se ofrece una bienvenida y un asistente con
cuatro pasos: identidad; cuenta principal y saldo inicial; presupuesto inicial;
revisión. El proyecto recibe una copia independiente del catálogo inicial de
categorías.

## 3. Navegación del proyecto

El menú incluye:

1. Resumen.
2. Movimientos.
3. Presupuestos.
4. Cuentas.
5. Objetivos de ahorro.
6. Informes.
7. Miembros.
8. Configuración.

Un selector mantiene visibles el proyecto, su color y su icono. El panel abre en
el mes actual y permite cambiar de mes y año sin perder el contexto.

## 4. Resumen mensual

En escritorio se colocan arriba o a la derecha tres indicadores separados:

- `Ingresos`: importe ingresado durante el mes.
- `Gastos`: gasto neto del mes después de devoluciones.
- `Presupuesto`: porcentaje consumido e importe todavía disponible.

El presupuesto utiliza un anillo de progreso. Los otros indicadores mantienen la
misma presencia visual sin inventar un porcentaje que no tenga una referencia
real. Los estados usan color, etiqueta, icono e importe; nunca color solamente.
Después se muestran balance, cuentas, aportaciones a inversión, gasto por
categoría y movimientos recientes.

Los valores de la sesión actual cambian inmediatamente tras cualquier operación.
Las acciones realizadas por otro miembro se sincronizan automáticamente y deben
aparecer en un máximo orientativo de 15 segundos, sin recargar la página.

## 5. Movimientos y filtros

`Añadir movimiento` permanece visible y permite elegir gasto, ingreso,
transferencia, devolución o aportación a inversión. El formulario aparece en un
panel lateral en escritorio y como página completa en móvil.

El historial usa tabla en escritorio y tarjetas en móvil. Los filtros de periodo,
cuenta, categoría, tipo, miembro, etiqueta y concepto permanecen durante la
sesión. Las acciones destructivas piden confirmación y el envío a la papelera
ofrece una opción temporal para deshacer.

## 6. Presupuestos e informes

El presupuesto global aparece antes que las categorías. Los avisos se muestran al
80 % y al 100 %, y el exceso permanece permitido y visible como saldo negativo.
Cada mes admite cambios propios; al editar se diferencia entre una excepción de
ese mes y un nuevo valor habitual para los meses futuros.

Los informes se dividen en `Mensual`, `Anual` y `Comparar`. Los gráficos siempre
tienen cifras o tablas equivalentes.

El resumen mensual compara con el mes anterior y el mismo mes del año previo. La
comparación visual admite hasta cinco periodos. Las barras comparan importes, las
líneas representan evolución y las barras horizontales ordenan categorías. Se
resumen las cinco categorías principales más `Otras`, con una tabla completa.
Seleccionar un dato abre sus movimientos filtrados.

El año en curso separa resultados reales hasta hoy de los meses futuros
planificados. Devoluciones, transferencias, ahorro e inversión se muestran como
conceptos diferenciados y no se mezclan para producir cifras engañosas.

## 7. Cierre mensual

Al finalizar un mes se ofrece un resumen que diferencia el presupuesto inicial,
el gasto neto, el presupuesto no consumido, los ingresos, el balance, los saldos
reales y el importe destinado a ahorro o inversión.

El cierre no bloquea el mes. `Destinar sobrante` crea una transferencia desde una
cuenta elegida por el usuario hacia una cuenta de ahorro o de inversión externa,
y puede vincularla a un objetivo. La transferencia no se convierte en gasto ni
modifica cuánto sobró del presupuesto. Si una corrección histórica produce una
diferencia, la aplicación avisa sin deshacer movimientos automáticamente.

Las pagas extraordinarias aparecen como ingresos. No elevan el presupuesto por
sí solas: se pueden conservar, transferir o utilizar como motivo para cambiar
manualmente uno o varios presupuestos.

## 8. Mensajes y estados

Las confirmaciones breves desaparecen por sí solas. Los errores importantes se
mantienen hasta que la persona los atiende. Los estados vacíos explican qué falta
y ofrecen una acción directa para continuar.

## 9. Estado del diseño

Los recorridos principales y sus adaptaciones para escritorio y móvil han sido
validados. El siguiente bloque corresponde al modelo de datos y la arquitectura
interna que respaldarán estas pantallas.

## 10. Validación visual

- El 14 de septiembre de 2026 se aprobó como base el primer esquema adaptable del
  resumen mensual, incluidos los indicadores circulares, la barra lateral, la
  jerarquía de tarjetas y el formulario lateral de movimientos.
- El 14 de septiembre de 2026 se aprobó también el recorrido adaptable de acceso,
  creación de cuenta y `Mis proyectos`, incluidas las tarjetas sin importes
  agregados y la actividad reciente identificada por proyecto.
- El 14 de septiembre de 2026 se aprobó el historial adaptable de movimientos,
  incluidos sus filtros, el formulario lateral, las subcategorías dependientes y
  el aviso no bloqueante de posible duplicado.
- El 14 de septiembre de 2026 se aprobó la pantalla adaptable de presupuestos,
  incluida la edición mensual o futura, los límites por categoría y el cierre con
  asignación del sobrante sin alterar el resultado histórico.
- El 14 de septiembre de 2026 se aprobó la pantalla adaptable de cuentas, con los
  saldos por tipo, el alta con saldo inicial y las transferencias internas,
  aportaciones y pagos de tarjeta sin doble contabilización.
- El 14 de septiembre de 2026 se aprobó la pantalla adaptable de objetivos, con
  progreso basado en aportaciones explícitas, cuentas vinculadas, fechas
  opcionales y avisos sin impedir superar el 100 %.
- El 14 de septiembre de 2026 se aprobó la pantalla adaptable de informes, con
  resumen mensual, año natural, comparación de hasta cinco periodos, detalle por
  categoría, separación de operaciones especiales y tablas numéricas visibles.
- El 14 de septiembre de 2026 se aprobó la pantalla adaptable de miembros, con
  alta por correo de cuentas existentes, cambios de rol, retirada de acceso,
  historial conservado y protección del propietario creador.
- El 14 de septiembre de 2026 se aprobó la pantalla adaptable de configuración,
  con ajustes regionales, papelera de 30 días, auditoría protegida, archivado en
  modo de solo lectura y reactivación del proyecto.
- La dirección queda abierta a ajustes menores durante la implementación y el
  uso real.
