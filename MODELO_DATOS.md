# Modelo de datos de SmartWallet

Estado: aprobado como base para crear las migraciones.

Última actualización: 14 de septiembre de 2026.

## 1. Objetivo y principios

El modelo debe mantener separados los proyectos, reconstruir los saldos sin
doble contabilización, conservar varios años de historial, permitir
restauraciones, evitar duplicados recurrentes y registrar las acciones sensibles.

Se aplicarán estos principios:

- cada dato financiero pertenece a un único proyecto;
- los importes se guardan como céntimos enteros: `10,50 €` será `1050`;
- el usuario introduce importes positivos y el tipo determina su efecto;
- los saldos se calculan desde el saldo inicial y los apuntes de cuenta;
- archivar conserva el historial;
- solo los movimientos pasan por la papelera de 30 días.

## 2. Entidades principales

| Área | Entidad | Finalidad |
|---|---|---|
| Acceso | `users` | Cuenta personal, correo único y contraseña. |
| Acceso | `sessions` | Sesiones abiertas y opción de cerrarlas. |
| Acceso | `password_reset_tokens` | Recuperación de contraseña mediante Mailpit. |
| Proyectos | `projects` | Nombre, propietario creador, región y archivado. |
| Proyectos | `project_members` | Pertenencia, rol y fechas de alta o retirada. |
| Finanzas | `financial_accounts` | Banco, ahorro, efectivo, tarjeta o inversión externa. |
| Finanzas | `movements` | Operación que entiende el usuario. |
| Finanzas | `account_entries` | Efecto exacto del movimiento sobre cada cuenta. |
| Clasificación | `categories` | Categorías principales y subcategorías. |
| Clasificación | `tags`, `movement_tag` y `recurrence_template_tag` | Varias etiquetas por movimiento y serie recurrente. |
| Presupuesto | `budget_templates` | Presupuesto habitual vigente desde un mes. |
| Presupuesto | `budget_template_limits` | Límites habituales por categoría principal. |
| Presupuesto | `monthly_budgets` | Copia editable de un mes concreto. |
| Presupuesto | `monthly_budget_limits` | Límites por categoría de ese mes. |
| Automatización | `recurrence_templates` | Definición de una serie recurrente. |
| Automatización | `recurrence_occurrences` | Cada aparición, generada una sola vez. |
| Ahorro | `savings_goals` | Objetivo, importe, fecha y cuenta vinculada. |
| Ahorro | `goal_allocations` | Aportación o retirada vinculada a un movimiento. |
| Control | `audit_logs` | Autor, acción y valores anteriores y posteriores. |

## 3. Usuarios y proyectos

### 3.1. Usuarios

`users` contiene el identificador interno, nombre, correo original y normalizado,
contraseña cifrada, último acceso y los campos de sesión habituales de Laravel.
El correo normalizado es único. No hay verificación de correo ni eliminación de
cuentas en la versión 1.0.

### 3.2. Proyectos

`projects` guarda nombre, propietario creador, moneda `EUR`, idioma `es`, zona
`Europe/Madrid` y datos de archivado. El propietario creador nunca cambia.

`project_members` guarda usuario, proyecto, rol `owner` o `member`, quién añadió
a la persona y las fechas de incorporación o retirada. Existirá una sola relación
por usuario y proyecto. Si alguien vuelve, se reactiva esa pertenencia para
conservar la misma identidad histórica.

## 4. Cuentas, movimientos y saldos

### 4.1. Cuentas

`financial_accounts` admite:

- `checking`: cuenta corriente;
- `savings`: ahorro;
- `cash`: efectivo;
- `credit_card`: deuda de tarjeta;
- `external_investment`: capital aportado sin valorar la cartera.

Cada cuenta conserva su saldo inicial, fecha inicial, límite opcional de tarjeta,
orden y fecha de archivado. Una cuenta usada no se elimina.

### 4.2. Movimientos

`movements` admite `expense`, `income`, `transfer`, `refund` e
`investment_contribution`. Guarda proyecto, importe positivo, fecha, concepto,
categoría, subcategoría, pagador, notas, autores, recurrencia de origen y estado
de papelera.

Una subcategoría solo es válida si pertenece a la categoría seleccionada. Una
devolución se vincula al gasto original y reduce su categoría y presupuesto.

### 4.3. Apuntes de cuenta

`account_entries` es la fuente de verdad para los saldos. Cada movimiento genera
uno o dos apuntes dentro de una única operación de base de datos.

| Operación | Cuenta de origen | Cuenta de destino |
|---|---:|---:|
| Gasto desde banco | `−100,00 €` | — |
| Ingreso en banco | `+100,00 €` | — |
| Banco a ahorro | `−100,00 €` | `+100,00 €` |
| Compra con tarjeta | Deuda `+100,00 €` | — |
| Pago de tarjeta | Banco `−100,00 €` | Deuda `−100,00 €` |
| Aportación a inversión | Banco `−100,00 €` | Inversión `+100,00 €` |

Así una transferencia, un pago de tarjeta o una aportación nunca se convierten
por error en un segundo gasto o ingreso.

## 5. Categorías y etiquetas

`categories` guarda proyecto, categoría superior opcional, nombre, color, icono,
posición, origen inicial y archivado.

Una categoría sin superior es principal. Una categoría con superior es una
subcategoría y no puede tener hijas, de modo que existen como máximo dos niveles.
El catálogo inicial se copia al crear el proyecto y luego cada proyecto mantiene
su propia versión.

`tags` pertenece al proyecto. `movement_tag` y `recurrence_template_tag` impiden
repetir una misma etiqueta en un movimiento o serie recurrente. Una etiqueta
archivada conserva sus relaciones históricas y una fusión las traslada a la
etiqueta de destino sin duplicarlas.

## 6. Presupuestos mensuales

`budget_templates` y `budget_template_limits` conservan cada versión del
presupuesto habitual y su primer mes de vigencia.

`monthly_budgets` contiene una única copia por proyecto y mes. Sus límites de
categorías principales se guardan en `monthly_budget_limits`.

- «Solo este mes» modifica únicamente la copia mensual.
- «Este mes y los siguientes» crea una nueva plantilla vigente desde ese mes.
- Al entrar en un mes sin presupuesto se copia la plantilla vigente.
- El sobrante no se arrastra.
- Ingresos y transferencias no amplían el presupuesto.

El cierre mensual no se congela en otra tabla. Los resúmenes históricos se
recalculan para que una devolución o restauración legítima se refleje bien.

`monthly_leftover_allocations` vincula el mes presupuestario con la transferencia
real creada desde `Destinar sobrante`. No guarda una copia del resultado mensual:
el sobrante se sigue recalculando. Solo los movimientos activos cuentan como
importe destinado; enviarlos a la papelera deja de contabilizarlos y restaurarlos
recupera el vínculo. Esto permite avisar, sin revertir dinero automáticamente, si
una corrección posterior deja un sobrante inferior al ya transferido.

## 7. Recurrencias

`recurrence_templates` guarda los datos que se copiarán, frecuencia diaria,
semanal, mensual o anual, fecha inicial, próxima fecha, final opcional y estado.

`recurrence_occurrences` tiene una restricción única por plantilla y fecha. Al
iniciar la aplicación se procesan todas las fechas vencidas; repetir el proceso
no puede crear el mismo movimiento dos veces.

## 8. Objetivos de ahorro

`savings_goals` guarda nombre, importe objetivo, fecha opcional y cuenta de ahorro
o inversión vinculada.

`goal_allocations` vincula una transferencia real como aportación o retirada. El
progreso se calcula con estas asignaciones, no con todo el saldo de la cuenta.
Puede superar el 100 % y una fecha vencida solo genera un aviso.

## 9. Papelera y auditoría

Al enviar un movimiento a la papelera:

1. se guardan la fecha de eliminación y la fecha de purga;
2. deja de contar en saldos, presupuestos e informes ordinarios;
3. se registra la acción;
4. puede restaurarse durante 30 días;
5. una tarea programada lo elimina al vencer el plazo.

`audit_logs` conserva proyecto, autor, elemento, acción, fecha y los valores
anteriores y posteriores en JSON. El historial se consulta, pero no se modifica
ni elimina desde la aplicación.

## 10. Índices y restricciones

- correo normalizado único;
- usuario y proyecto únicos en miembros;
- proyecto y mes únicos en presupuestos;
- movimiento único en asignaciones de sobrante mensual;
- plantilla y fecha únicas en apariciones recurrentes;
- etiqueta y movimiento únicos;
- índices de movimientos por proyecto, fecha, tipo, cuenta, categoría, miembro
  y estado de papelera;
- índice para el aviso por categoría, subcategoría, fecha e importe;
- claves foráneas para referencias obligatorias;
- validación de que cuentas, categorías y relaciones pertenecen al mismo
  proyecto.

## 11. Fechas, años y datos calculados

Las fechas financieras se guardan como `DATE`. Creación, modificación y auditoría
se guardan en UTC y se muestran en `Europe/Madrid`.

Cambiar de año no borra ni reinicia datos: solo crea nuevos presupuestos
mensuales. Saldos, gastos, balance, presupuesto consumido, comparaciones,
sobrante y progreso de objetivos se calculan desde sus datos de origen para
evitar cifras contradictorias.

## 12. Decisiones aprobadas

Antes de crear las migraciones se confirma:

1. guardar el dinero como céntimos enteros;
2. usar apuntes de cuenta para reconstruir saldos;
3. versionar el presupuesto habitual y crear copias mensuales editables;
4. recalcular los informes sin congelar cierres mensuales;
5. conservar en auditoría los valores anteriores y posteriores como JSON;
6. reactivar la misma pertenencia si una persona retirada vuelve al proyecto.

Las seis decisiones fueron aprobadas el 14 de septiembre de 2026. La
reincorporación exige una acción expresa de un propietario y recupera el acceso
con rol de miembro, aunque la persona hubiera sido propietaria anteriormente.
