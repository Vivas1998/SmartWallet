# Requisitos de la aplicación de gastos

**Fecha de inicio:** 13 de septiembre de 2026
**Estado:** aprobado para la versión 1.0
**Versión documental:** 1.0

## 1. Visión confirmada

SmartWallet es una herramienta privada para un entorno
familiar. Permitirá
gestionar varios espacios financieros independientes llamados proyectos. Un
proyecto podrá representar la economía familiar, las finanzas personales u otro
ámbito y podrá incorporar varios miembros de la casa. El nombre o la finalidad
del proyecto no altera sus reglas: la pertenencia determina quién puede verlo.

TaskFlow 1.0 ya está finalizada y aporta una base visual y técnica. SmartWallet tendrá
código, datos, credenciales y entorno propios. Su versión 1.0 será completamente
funcional en local mediante Docker antes del 31 de diciembre de 2026 y quedará
abierta a mejoras posteriores basadas en el uso. El coste de desarrollo será de
0 €.

## 2. Usuarios y acceso

- Todo el contenido financiero exige autenticación.
- La aplicación utiliza correo y contraseña, recuperación mediante enlace
  temporal, cierre de sesión y opción de recordar la sesión. La versión 1.0 no
  exige verificar el correo electrónico.
- Habrá páginas de inicio de sesión y creación de cuenta siguiendo la experiencia
  de TaskFlow.
- El registro solicita únicamente nombre visible, correo, contraseña y
  confirmación. Puede habilitarse o cerrarse mediante la configuración del
  entorno; permanecerá habilitado durante el uso local.
- Cada usuario tiene un identificador interno estable. El correo normalizado, sin
  espacios exteriores y sin distinguir mayúsculas, tiene una restricción única
  en la base de datos y nunca se utiliza como clave primaria.
- Las contraseñas tienen un mínimo de 12 caracteres, sin imponer combinaciones
  obligatorias de mayúsculas, números o símbolos.
- Los intentos repetidos de acceso se limitan temporalmente sin bloquear la
  cuenta de forma permanente.
- La sesión normal caduca tras dos horas de inactividad. `Recordarme` mantiene la
  sesión hasta 30 días en el dispositivo.
- El enlace para restablecer la contraseña caduca a los 60 minutos y solo puede
  utilizarse una vez.
- Cambiar el correo exige introducir la contraseña actual y elegir otro correo
  único; no requiere verificación en la versión 1.0.
- Al cambiar la contraseña puede cerrarse el resto de las sesiones. El perfil
  muestra las sesiones recientes y permite revocarlas.
- El perfil utiliza el nombre y sus iniciales, sin fotografías en la versión 1.0.
- La versión 1.0 no permite eliminar definitivamente una cuenta de usuario. La
  atribución histórica se conserva aunque deje de pertenecer a proyectos.
- Mailpit se utiliza para los mensajes de recuperación de contraseña. No se
  envían correos de verificación ni avisos financieros.
- Solo las cuentas ya existentes en la aplicación pueden incorporarse a un
  proyecto; un propietario debe introducir el correo exacto y no se muestra un
  directorio general de usuarios. No se envían invitaciones pendientes a correos
  sin cuenta.
- Las personas crean su propia cuenta desde el formulario de registro y después
  pueden ser añadidas a un proyecto por uno de sus propietarios.

## 3. Proyectos, miembros y permisos

- Cualquier usuario autenticado puede crear proyectos y se convierte en su
  propietario inicial.
- Los únicos roles de la versión 1.0 son `Propietario` y `Miembro`.
- Todos los miembros pueden ver todas las cuentas, importes, presupuestos,
  movimientos e informes del proyecto.
- Todos los miembros pueden crear y modificar cualquier movimiento del proyecto,
  aunque lo haya creado otra persona.
- Tanto propietarios como miembros pueden enviar movimientos a la papelera,
  restaurarlos durante 30 días, exportar el proyecto a CSV y consultar su
  auditoría.
- Los propietarios administran el proyecto, sus miembros y sus propietarios.
- Puede haber varios propietarios. Cualquier propietario puede ascender a un
  miembro o degradar a otro propietario, salvo al creador original del proyecto.
- El creador original conserva siempre el rol de propietario y no puede ser
  degradado ni expulsado mientras exista el proyecto.
- Solo los propietarios crean, editan y archivan cuentas, presupuestos, objetivos
  de ahorro y plantillas de movimientos recurrentes.
- Los miembros utilizan las cuentas, consultan los presupuestos, aportan a
  objetivos y pueden modificar una aparición concreta de una recurrencia.
- Un proyecto debe conservar siempre al menos un propietario.
- Al salir o ser retirado, un miembro pierde el acceso de inmediato y se conserva
  su atribución histórica.
- Los proyectos se archivan y quedan en modo de solo lectura. No se eliminan
  definitivamente desde la interfaz de la versión 1.0.
- La pantalla inicial no mezcla ni suma importes de proyectos diferentes.
- No existe un panel administrativo global para consultar finanzas de varios
  proyectos.

## 4. Cuentas y movimientos

- Cada proyecto dispone de cuentas manuales de tipo corriente, ahorro, efectivo,
  tarjeta de crédito o inversión externa de solo aportaciones.
- Cada proyecto comienza con una `Cuenta principal` que después puede editarse.
- El saldo inicial lleva una fecha y no se considera ingreso. Los saldos se
  calculan a partir de ese valor y de los movimientos posteriores.
- Un gasto o ingreso requiere tipo, importe, fecha, concepto, categoría y cuenta.
  Las transferencias requieren cuenta de origen y destino, pero no categoría.
  Notas y persona que pagó o recibió son opcionales. Proyecto, autor y marcas de
  creación y modificación se registran automáticamente.
- Los importes se introducen como valores positivos; el tipo determina su efecto.
- Los importes admiten céntimos y se almacenan con precisión decimal, sin usar
  aproximaciones de coma flotante.
- Los movimientos manuales de la versión 1.0 representan operaciones confirmadas,
  no estados pendientes. Su fecha contable puede ser hoy o una fecha pasada; las
  operaciones futuras se cubren mediante las recurrencias.
- La persona que registra un gasto aparece como pagadora por defecto y puede
  cambiarse por cualquier miembro del proyecto.
- La versión 1.0 incluye gastos, ingresos, transferencias internas entre cuentas
  y devoluciones. Las divisiones, deudas y reembolsos entre miembros están
  aplazados.
- Una transferencia mueve saldo entre cuentas sin contar como ingreso o gasto.
- Una retirada de efectivo se registra como transferencia del banco a efectivo.
- Una compra con tarjeta de crédito se registra como gasto en la fecha de compra
  y consume entonces el presupuesto. Pagar la tarjeta es una transferencia desde
  una cuenta bancaria y no vuelve a contar como gasto.
- Se permiten saldos negativos en cuentas ordinarias y deuda pendiente en
  tarjetas, siempre con una advertencia visible y sin bloquear el movimiento.
- Si una transferencia tiene comisión, esta se registra como un gasto separado
  en `Finanzas y seguros > Comisiones`.
- La versión 1.0 ofrece una cuenta `Inversión externa — solo aportaciones`. Una
  aportación se registra como transferencia desde una cuenta ordinaria, reduce su
  saldo y aumenta el capital aportado sin convertirse en gasto o ingreso.
- Esta cuenta no representa el valor actual de una cartera ni registra activos,
  precios, rentabilidad, dividendos o ganancias y pérdidas.
- Una devolución se vincula al gasto original, devuelve saldo a la cuenta y
  reduce el consumo de su categoría y presupuesto. Puede haber devoluciones
  parciales o varias devoluciones, pero su suma no puede superar el gasto
  original.
- Estas operaciones no se introducen mediante importes negativos manuales.
- Los movimientos enviados a la papelera pueden restaurarse y muestran su fecha
  prevista de eliminación. Al cumplir 30 días se eliminan automáticamente de
  forma definitiva.
- Se audita quién crea, modifica, elimina o restaura movimientos, presupuestos,
  cuentas y miembros, incluidos los valores financieros modificados.
- Se registra quién pagó, pero la versión 1.0 no divide gastos, no calcula deudas
  ni registra liquidaciones o reembolsos entre miembros. El presupuesto del
  proyecto es común y no se divide entre sus miembros.
- Una cuenta con movimientos se archiva en lugar de eliminarse y su historial
  continúa apareciendo en informes y filtros históricos.
- Antes de guardar un movimiento categorizado, la aplicación busca otro movimiento
  activo del mismo proyecto con la misma categoría, subcategoría, fecha e importe
  exacto. Si coinciden los cuatro valores, muestra un posible duplicado y permite
  revisar el anterior o `Guardar igualmente`. El aviso es informativo, no bloquea
  y descartarlo no desactiva futuras comprobaciones.

## 5. Categorías, etiquetas y automatización

- Cada proyecto recibe su propia copia de una plantilla amplia de categorías y
  subcategorías iniciales. Los cambios nunca afectan a otros proyectos.
- Cada proyecto puede crear categorías y subcategorías según sus necesidades.
- Solo los propietarios crean, renombran, ordenan y archivan categorías y
  subcategorías.
- La jerarquía se limita a dos niveles: categoría y subcategoría.
- Las categorías y subcategorías admiten color e icono personalizables.
- Al seleccionar una categoría en un movimiento, el selector de subcategoría
  muestra únicamente las subcategorías activas que pertenecen a ella. Si cambia
  la categoría, cualquier subcategoría que deje de ser válida se limpia antes de
  guardar.
- Cada movimiento categorizado conserva su categoría principal y, opcionalmente,
  una subcategoría compatible con su tipo. Una categoría utilizada no cambia de
  tipo y se archiva en lugar de eliminarse.
- La versión 1.0 permite varias etiquetas por movimiento, propias del proyecto.
- Cualquier miembro puede crear y utilizar etiquetas. Solo los propietarios
  pueden renombrarlas, fusionarlas o archivarlas.
- La categorización automática se aplaza.
- Los campos personalizados se aplazan; categorías, etiquetas y notas cubren la
  versión 1.0.
- La versión 1.0 incluye movimientos recurrentes diarios, semanales, mensuales y
  anuales, con final opcional y generación automática al llegar la fecha.
- Pueden ser recurrentes los gastos, ingresos, transferencias y aportaciones a
  inversión, pero no las devoluciones.
- La plantilla contiene un importe habitual. Cada aparición generada puede
  modificarlo sin cambiar necesariamente el resto de la serie.
- Una recurrencia mensual configurada para un día inexistente se genera el último
  día de ese mes y recupera el día original cuando vuelva a existir. Los fines de
  semana y festivos no desplazan automáticamente la fecha.
- Se puede editar solo una aparición o toda la serie futura y el sistema debe
  impedir ocurrencias duplicadas.
- Un propietario puede pausar o reanudar la serie y omitir únicamente su próxima
  aparición.
- Si la aplicación estaba apagada, al reanudarse genera una sola vez cada
  aparición vencida que falte. Después muestra un aviso-resumen con la cantidad y
  el detalle de las operaciones añadidas automáticamente, que quedan atribuidas
  al sistema en la auditoría.
- Una transferencia recurrente puede vincularse a un objetivo de ahorro o a la
  cuenta externa de inversión.

### 5.1. Categorías iniciales de gastos

| Categoría | Subcategorías iniciales |
|---|---|
| Vivienda | Alquiler o hipoteca; comunidad; mantenimiento y reparaciones; mobiliario y hogar; seguro del hogar |
| Suministros | Electricidad; agua; gas; Internet; telefonía; otros suministros |
| Alimentación | Supermercado; restaurantes; comida a domicilio; cafetería y aperitivos |
| Transporte | Combustible; transporte público; mantenimiento del vehículo; seguro; aparcamiento y peajes; taxi/VTC |
| Salud | Farmacia; consultas médicas; dentista; seguro médico; óptica |
| Educación | Matrículas; cursos; libros; material escolar |
| Familia | Niños; guardería; actividades; ayuda familiar |
| Mascotas | Alimentación; veterinario; medicamentos; accesorios |
| Compras personales | Ropa y calzado; cuidado personal; tecnología; regalos |
| Ocio y cultura | Cine y espectáculos; aficiones; deporte; juegos |
| Viajes | Transporte; alojamiento; comidas; actividades |
| Suscripciones | Streaming; software; almacenamiento; membresías |
| Impuestos y administración | Impuestos; tasas; gestoría; trámites |
| Finanzas y seguros | Comisiones bancarias; intereses; seguros diversos |
| Donaciones | Donaciones; asociaciones; regalos solidarios |
| Imprevistos y otros | Emergencias; reparaciones inesperadas; otros gastos |

### 5.2. Categorías iniciales de ingresos

| Categoría | Subcategorías iniciales |
|---|---|
| Trabajo | Nómina; paga extraordinaria; horas extra; bonificaciones |
| Prestaciones | Pensiones; desempleo; ayudas; subvenciones |
| Rendimientos | Intereses; dividendos recibidos; alquileres |
| Ingresos extraordinarios | Venta de artículos; premios; regalos recibidos |
| Otros ingresos | Otros ingresos |

Las transferencias no usan categoría, las devoluciones heredan la del gasto
original y las aportaciones a inversión se vinculan a su cuenta externa y,
opcionalmente, a un objetivo.

## 6. Presupuestos y objetivos

- Los presupuestos usan meses naturales.
- Puede existir un límite total del proyecto y límites por categoría.
- En la versión 1.0 solo las categorías principales admiten límites; los gastos de
  sus subcategorías se acumulan en el presupuesto de la categoría principal.
- El límite total es común para todos los miembros. Por ejemplo, en un presupuesto
  de 1.000 €, cada gasto confirmado reduce el saldo mensual disponible común.
- Al comenzar un mes se copian el presupuesto total y los límites por categoría
  del mes anterior, pero no su saldo sobrante. Cada mes puede editarse de forma
  independiente.
- El presupuesto no queda fijado al crear el proyecto. Un propietario puede
  modificar el total y los límites por categoría del mes seleccionado.
- La edición distingue entre un cambio excepcional de un único mes y un cambio
  del presupuesto habitual para los meses siguientes. De este modo, un aumento
  en diciembre no obliga a conservarlo en enero.
- El importe no consumido no se arrastra al mes siguiente.
- La interfaz avisa visualmente al alcanzar el 80 % y el 100 %.
- Superar el presupuesto no impide registrar gastos: el saldo aparece negativo y
  destacado en rojo.
- Los ingresos se muestran por separado y no amplían automáticamente el
  presupuesto.
- Una paga extraordinaria se registra como ingreso en `Trabajo > Paga
  extraordinaria`. Aumenta el saldo de la cuenta y el balance del mes, pero no
  modifica automáticamente el presupuesto. El usuario decide si la conserva,
  aumenta manualmente un presupuesto o la transfiere a ahorro o inversión.
- Las transferencias internas no cuentan como ingreso ni gasto del presupuesto.
- Las aportaciones a inversión reducen el balance de caja disponible, pero no el
  presupuesto de gastos de consumo. Pueden vincularse a un objetivo de ahorro.
- La versión 1.0 incluye objetivos de ahorro mediante nombre, importe objetivo,
  fecha opcional y aportaciones explícitas vinculadas a movimientos; no se toma
  automáticamente todo el saldo de una cuenta.
- Cada objetivo se vincula a una cuenta de ahorro o inversión del proyecto. Las
  aportaciones proceden de movimientos reales y una retirada se representa con
  una transferencia inversa que reduce el progreso sin convertirse en ingreso.
- Al alcanzar el 100 %, el objetivo se marca como completado, pero admite
  aportaciones adicionales y porcentajes superiores hasta que se archive.
- Superar la fecha objetivo muestra un aviso y no bloquea nuevas aportaciones.
  Un objetivo archivado conserva todo su historial.
- Los informes muestran por separado aportaciones y retiradas de ahorro, sin
  sumarlas a ingresos ni gastos.
- Al terminar el mes se ofrece un resumen de cierre sin bloquear el periodo. El
  propietario puede destinar total o parcialmente el presupuesto no consumido a
  una cuenta de ahorro o a una cuenta externa de inversión, y vincular la
  operación a un objetivo cuando corresponda.
- Destinar el sobrante crea una transferencia real desde una cuenta con saldo
  suficiente. No se registra como gasto o ingreso y no altera el resultado
  histórico del presupuesto. El cierre muestra por separado cuánto sobró y
  cuánto se destinó a cada finalidad.
- Si una corrección posterior reduce el sobrante por debajo de lo ya destinado,
  no se revierte ningún movimiento automáticamente: se muestra una advertencia y
  queda registrada la diferencia.

## 7. Panel, búsqueda e intercambio de datos

- El panel de cada proyecto muestra ingresos, gastos, balance, consumo de
  presupuestos, distribución por categorías y últimos movimientos.
- En escritorio, la parte superior o derecha del panel contiene tres indicadores
  compactos para ingresos, gastos y presupuesto del mes. El presupuesto se
  representa mediante un anillo de progreso con el importe restante; los tres
  indicadores tienen color, texto e icono propios y se recalculan tras las
  operaciones sin exigir recargar la página.
- Las operaciones realizadas en la sesión actual actualizan el panel de
  inmediato. Los cambios de otros miembros se consultan automáticamente para
  aparecer en un máximo orientativo de 15 segundos, sin necesidad de recargar.
- El resumen mensual muestra presupuesto inicial, gastos, saldo disponible,
  ingresos y desglose por categorías.
- Cuando existen datos, el mes se compara automáticamente con el anterior y con
  el mismo mes del año anterior.
- El resumen anual agrega los meses del año y permite comparar gasto mensual,
  categorías, ingresos y balance sin mezclar proyectos.
- La comparación visual admite hasta cinco meses o años simultáneos; las tablas y
  exportaciones mantienen todo el detalle sin ese límite visual.
- Se utilizan barras para comparar periodos, líneas para mostrar evolución y
  barras horizontales para categorías. Se destacan las cinco categorías con más
  gasto y se agrupa el resto como `Otras`, manteniendo una tabla completa.
- Seleccionar un periodo o categoría en un gráfico abre el listado de movimientos
  con los filtros correspondientes.
- El gasto neto es la cifra principal y las devoluciones se muestran también por
  separado. Las transferencias disponen de su propia sección y nunca se suman a
  ingresos o gastos.
- Los resúmenes muestran por separado el capital aportado a inversiones durante
  el mes y el año; nunca lo presentan como valor actual de cartera.
- Las aportaciones y retiradas de ahorro e inversión tienen evolución mensual y
  anual separada.
- En el año en curso, los resultados reales llegan únicamente hasta la fecha
  actual; los meses futuros se identifican como planificación.
- Se puede filtrar por fechas, cuenta, categoría, tipo y miembro, además de buscar
  por concepto.
- La introducción manual y la exportación CSV están incluidas.
- La exportación CSV permite descargar el proyecto completo o únicamente el
  resultado de los filtros actuales.
- El CSV usa fechas `DD/MM/AAAA`, coma decimal, punto y coma como separador y
  codificación UTF-8 compatible con Excel. Separa categoría y subcategoría e
  incluye proyecto, tipo, cuenta, pagador, concepto, importe, fecha, etiquetas y
  notas.
- La exportación normal excluye la papelera. Los propietarios pueden descargar
  por separado los movimientos que permanezcan en ella.
- La importación CSV, la conexión bancaria y la conciliación bancaria quedan
  aplazadas.

## 8. Cambio de mes, año e historial

- El cambio de mes o año no elimina, reinicia ni sobrescribe movimientos.
- El nuevo mes copia el presupuesto total y los límites por categoría del mes
  anterior, pero comienza con todo su importe disponible y sin arrastrar sobrantes
  o excesos.
- Los saldos de cuentas, movimientos recurrentes, objetivos de ahorro y capital
  aportado a inversiones continúan entre años.
- Los registros se conservan indefinidamente mientras no se envíen a la papelera;
  únicamente estos últimos se eliminan al cumplir 30 días.
- Puede compararse cualquier selección de meses, el mismo mes de varios años y
  años naturales completos.
- Las comparativas incluyen presupuesto, gastos, ingresos, aportaciones a
  inversión, balance, saldo presupuestario, variación y desglose por categorías.
- Los filtros de comparación incluyen cuenta, categoría, miembro y etiqueta y
  nunca mezclan proyectos.
- Si se corrige un movimiento histórico, los resúmenes se recalculan y la
  auditoría conserva el cambio.
- Los proyectos archivados mantienen informes y exportaciones en modo de solo
  lectura.
- La auditoría conserva indefinidamente autor, fecha, acción y valores anteriores
  y nuevos. Ningún usuario puede modificarla o eliminarla desde la aplicación.
- El historial de auditoría puede filtrarse por miembro, acción, tipo de elemento
  y fechas.

## 9. Experiencia de usuario

- La interfaz toma de TaskFlow su estructura general, navegación, formularios,
  estados vacíos, accesibilidad y adaptación a distintos tamaños.
- Se reutilizan componentes revisados en un proyecto y repositorio independientes,
  sin compartir base, credenciales ni volúmenes.
- Tendrá identidad propia basada en verde petróleo y menta.
- La versión 1.0 tendrá únicamente tema claro.
- El modo oscuro figura como mejora futura.
- En escritorio mantiene una barra lateral oscura de 264 px y cabecera superior.
  Por debajo de 900 px la navegación pasa a un panel móvil y a 520 px los grupos
  principales se presentan en una columna, siguiendo las referencias de
  TaskFlow.
- Los tres indicadores financieros ocupan una fila superior de tarjetas. Ingresos
  muestra el total; Gastos añade la variación frente al mes anterior; Presupuesto
  muestra porcentaje consumido y saldo restante.
- Ingresos usa verde esmeralda, gastos coral, presupuesto verde petróleo con
  avisos ámbar y rojo, transferencias azul, ahorro menta, inversión violeta y
  devoluciones turquesa. Todo significado se acompaña de texto e icono.
- Las tarjetas conservan radios y sombras suaves equivalentes a TaskFlow. La
  tipografía utiliza la pila del sistema con apariencia similar a Inter y los
  iconos lineales se incluyen localmente.
- La marca definitiva utiliza una `S` dentro de un cuadrado. Las pantallas de
  acceso mantienen la
  composición dividida de TaskFlow y el mensaje `Tus cuentas claras. Tus
  decisiones, con perspectiva.`
- Un control con forma de ojo oculta temporalmente todos los importes y recuerda
  la preferencia solo en el navegador actual.
- Las tablas usan una densidad cómoda. Las animaciones son breves y respetan la
  preferencia de movimiento reducido. Los estados vacíos emplean iconos y formas
  geométricas, explicación breve y una acción directa.
- Deben diseñarse patrones nuevos para importes, movimientos, presupuestos,
  gráficos, comparativas e importación.
- Se aplican las mismas referencias de TaskFlow: uso desde 360 px hasta escritorio,
  navegación por teclado, foco visible, contraste AA y zoom al 200 %.
- Después de iniciar sesión se muestra `Mis proyectos`, con tarjetas que incluyen
  nombre, icono, color, descripción, miembros, rol y última actividad, pero no
  suman ni mezclan sus importes.
- La actividad reciente general identifica proyecto, acción, autor y fecha.
- Dentro de un proyecto hay acceso a Resumen, Movimientos, Presupuestos, Cuentas,
  Objetivos de ahorro, Informes, Miembros y Configuración, además de un selector
  visible para cambiar de proyecto.
- El panel abre en el mes actual. Su orden prioriza presupuesto restante, gastos,
  ingresos, balance, saldos de cuentas, aportaciones a inversión, categorías y
  últimos movimientos.
- El botón `Añadir movimiento` permanece visible. El formulario usa un panel
  lateral en escritorio y una página completa en móvil, y permite elegir gasto,
  ingreso, transferencia, devolución o aportación a inversión.
- Los movimientos se presentan como tabla en escritorio y tarjetas en móvil. Los
  filtros se conservan durante la sesión e incluyen periodo, cuenta, categoría,
  tipo, miembro, etiquetas y búsqueda por concepto.
- Los informes se organizan en `Mensual`, `Anual` y `Comparar`; todo gráfico tiene
  cifras o una tabla equivalente.
- La creación de un proyecto guía por identidad, cuenta principal y saldo,
  presupuesto inicial y revisión. Al terminar copia el catálogo inicial de
  categorías y permite añadir miembros existentes.
- Las operaciones delicadas piden confirmación. Enviar un movimiento a la
  papelera ofrece deshacer durante unos segundos.
- Las confirmaciones breves desaparecen solas; los errores que requieren atención
  permanecen visibles. Una cuenta sin proyectos recibe una bienvenida y una
  acción para crear el primero.

## 10. Restricciones tecnológicas confirmadas

- PHP 8.5 y Laravel 13 con arquitectura MVC y vistas Blade.
- CSS plano con metodología BEM.
- JavaScript vanilla como base; se permiten bibliotecas de terceros, que se
  elegirán y documentarán según la necesidad.
- MySQL 8.4 LTS con bases, credenciales y permisos independientes de TaskFlow.
- Vite para compilar los recursos web.
- Docker Compose con contenedores propios para la aplicación, MySQL y Mailpit. La
  base de pruebas también debe quedar aislada de los datos de uso.

### 10.1. Entornos de trabajo

- `Alpha` designa los prototipos interactivos usados en la conversación para
  validar recorridos, contenidos y diseño. No representan todavía la aplicación
  Laravel ni tienen base de datos o persistencia real.
- `Desarrollo local` designa la aplicación real ejecutada con Docker Compose en
  el equipo local. En este entorno se implementan y prueban la lógica, la base de
  datos y la persistencia.
- El despliegue posterior en el homelab será un entorno separado y se definirá
  cuando la versión local esté preparada.

## 11. Funciones excluidas de la versión 1.0

- Conexión y sincronización automática con bancos.
- Importación CSV y detección de duplicados.
- Categorización automática.
- Fotografías de recibos y OCR.
- Varias monedas y conversión de divisas.
- Conciliación bancaria.
- Predicciones financieras.
- Informes PDF o Excel.
- Notificaciones por correo, navegador o móvil.
- Instalación como PWA y funcionamiento sin conexión.
- API, webhooks e integraciones externas.
- Doble factor mientras la aplicación continúe siendo local.
- Rol de solo lectura y permisos personalizados.
- Aprobación de gastos.
- División de gastos, cálculo de deudas y reembolsos entre miembros.
- Campos personalizados.
- Funciones fiscales, gestión de suscripciones y avisos de facturas.

El calendario de pagos se reserva para la versión 1.1 y el resto se mantiene en
[FUTURAS_VERSIONES.md](FUTURAS_VERSIONES.md).

## 12. Criterios de aceptación de la versión 1.0

- Existen registro sin verificación de correo, inicio y cierre de sesión, opción
  de recordar la sesión y recuperación de contraseña.
- Un usuario puede crear proyectos, añadir usuarios ya registrados, administrar
  propietarios y miembros y archivar un proyecto.
- Las pruebas demuestran que una persona ajena no puede consultar ni modificar un
  proyecto, aunque conozca una URL o identificador.
- Los miembros pueden gestionar todos los movimientos y utilizar cuentas,
  categorías, presupuestos, objetivos y etiquetas; solo los propietarios
  administran la estructura del proyecto.
- Las cuentas reflejan correctamente saldos iniciales, gastos, ingresos,
  transferencias y devoluciones sin doble contabilización.
- Los gastos reducen el presupuesto mensual global y sus límites por categoría;
  los ingresos y transferencias no lo incrementan.
- El panel y los resúmenes mensual y anual explican cuánto y dónde se gasta sin
  sumar datos de distintos proyectos.
- El cambio de año conserva saldos e historial y permite comparar meses y años
  sin alterar los presupuestos cerrados.
- Los movimientos recurrentes se generan sin duplicados y admiten cambios en una
  aparición o en toda la serie futura.
- La exportación CSV contiene únicamente los datos autorizados del proyecto.
- La papelera, restauración y auditoría conservan autor, acción y valores
  financieros modificados según las reglas acordadas.
- La interfaz funciona desde 360 px hasta escritorio, por teclado, con foco
  visible, contraste AA y zoom al 200 %.
- La instalación limpia mediante Docker Compose crea un entorno funcional sin
  compartir base, credenciales ni volúmenes con TaskFlow.
- La suite automática cubre autenticación, permisos, aislamiento, cálculos
  financieros y recorridos esenciales con una base de pruebas independiente.
- Las operaciones habituales funcionan con un conjunto representativo y las
  consultas principales se prueban hasta 100.000 movimientos por proyecto.
- No quedan defectos críticos conocidos y la instalación, uso y recuperación del
  entorno local están documentados.

## 13. Decisión no bloqueante

`SmartWallet` es el nombre definitivo de la aplicación desde el 19 de septiembre
de 2026.

La versión 1.0 no tendrá copias automáticas ni cifradas. Se acepta expresamente el
riesgo de depender del volumen Docker hasta el despliegue futuro en el homelab.

## 14. Condición de salida del descubrimiento

El alcance podrá considerarse acordado cuando:

- se resuelvan las decisiones del apartado anterior;
- se distingan claramente funciones incluidas y aplazadas;
- los roles y permisos no admitan interpretaciones contradictorias;
- los cálculos financieros y los presupuestos tengan reglas explícitas;
- exista un criterio de aceptación comprobable para cada recorrido esencial;
- las decisiones de privacidad y conservación sean compatibles con datos reales;
- el responsable apruebe este documento antes de diseñar el modelo de datos.

## 15. Historial

| Fecha | Cambio | Motivo |
|---|---|---|
| 13-09-2026 | Creación del borrador con la visión y las restricciones confirmadas. | Inicio anticipado de la preparación y primera ronda de descubrimiento. |
| 13-09-2026 | Incorporadas las respuestas funcionales y la selección de funciones para 1.0 y futuras versiones. | Consolidar el alcance y reducir la segunda ronda a las ambigüedades restantes. |
| 13-09-2026 | Confirmado el nombre de trabajo inicial, stack exacto, entorno local, auditoría, recurrencia, objetivos, etiquetas, escala y fecha máxima; CSV y categorización se aplazan. | Incorporar la segunda ronda y centrar el siguiente paso en las reglas financieras. |
| 19-09-2026 | Confirmado `SmartWallet` como nombre definitivo y renombrados la interfaz, la configuración, Docker, las bases y la documentación. | Mantener esta identidad en futuras versiones. |
| 13-09-2026 | Cerrados presupuesto compartido, resúmenes, papelera, ausencia de copias locales y exclusión de deudas, reembolsos y campos personalizados. | Completar la tercera ronda de descubrimiento y dejar abierta solo la representación de inversiones. |
| 13-09-2026 | Aprobada la cuenta externa de solo aportaciones para reflejar inversiones sin gestionar la cartera. | Separar consumo, flujo de caja y capital aportado sin duplicar ingresos. |
| 13-09-2026 | Confirmada la conservación entre años, las comparativas históricas y un catálogo inicial ampliable por proyecto. | Evitar pérdidas al cambiar de periodo y preparar informes y presupuestos jerárquicos. |
| 13-09-2026 | Aprobados el catálogo inicial, dos niveles, administración por propietarios, colores e iconos y presupuestos solo en categorías principales. | Cerrar la clasificación inicial y mantener sencilla la versión 1.0. |
| 13-09-2026 | Aprobada la matriz de permisos y cerrado el hito de requisitos de la versión 1.0. | Disponer de una base funcional verificable para comenzar UX, arquitectura y datos. |
| 13-09-2026 | Aprobada la navegación, el comportamiento adaptable y la composición general de las pantallas. | Convertir los recorridos confirmados en esquemas visuales y componentes. |
| 13-09-2026 | Incorporados tres indicadores inmediatos de ingresos, gastos y presupuesto y la edición mensual con excepciones. | Facilitar la lectura del mes y permitir presupuestos estacionales, como Navidad. |
| 13-09-2026 | Confirmados el tratamiento de pagas extraordinarias, la asignación del sobrante mensual y la actualización entre miembros. | Separar presupuesto, flujo de caja y transferencias manteniendo el panel actualizado. |
| 13-09-2026 | Aprobadas las reglas detalladas de cuentas, tarjetas, devoluciones, exportación y movimientos confirmados. | Cerrar el comportamiento contable cotidiano antes de diseñar los datos. |
| 13-09-2026 | Protegido el propietario creador y definido el aviso descartable de duplicados por categoría, subcategoría, fecha e importe. | Compartir la administración sin retirar el control original y reducir altas accidentales. |
| 13-09-2026 | Cerradas las reglas de recurrencias y objetivos, incluida la recuperación completa tras un apagado con aviso-resumen. | Automatizar operaciones sin duplicados y mantener el ahorro respaldado por movimientos reales. |
| 13-09-2026 | Aprobados informes comparables, acceso al detalle, CSV para Excel y auditoría inalterable. | Completar la consulta y salida segura de la información financiera. |
| 13-09-2026 | Aprobado el acceso local sin verificación de correo, con correo único, sesiones controlables y sin eliminación de usuarios. | Cerrar la autenticación de 1.0 manteniendo una evolución segura hacia el homelab. |
| 13-09-2026 | Aprobados la identidad visual, los indicadores superiores, las medidas de TaskFlow y la ocultación local de importes. | Disponer de un sistema coherente antes de dibujar y validar las pantallas. |
| 14-09-2026 | Denominados `Alpha` los prototipos de validación y `Desarrollo local` la aplicación Laravel real; aclarada además la dependencia entre categoría y subcategoría. | Diferenciar una simulación visual de la lógica definitiva y evitar combinaciones de clasificación inválidas. |
| 14-09-2026 | Aprobadas las seis decisiones del modelo de datos: céntimos, apuntes, presupuestos versionados, informes recalculados, auditoría completa y reincorporación como miembro. | Cerrar la base de persistencia antes de diseñar migraciones y servicios financieros. |
