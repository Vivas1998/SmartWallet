# Registro de decisiones de la aplicación de gastos

## Estados

- **Aceptada:** decisión confirmada por el responsable del proyecto.
- **Provisional:** dirección de trabajo que necesita una confirmación posterior.
- **Pendiente:** todavía no se ha elegido una opción.
- **Sustituida:** otra decisión posterior la reemplaza conservando el historial.

## APP-001 — Aplicación autenticada con proyectos financieros

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** todo el contenido requiere inicio de sesión. Cada usuario puede
  crear varios proyectos financieros independientes y añadir miembros a los
  proyectos que quiera compartir.
- **Motivo:** permite separar ámbitos como la economía familiar y las finanzas
  personales sin mezclar datos ni colaboradores.

## APP-002 — Gastos, ingresos y presupuestos como núcleo

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** la aplicación tendrá como funciones nucleares el registro de
  gastos e ingresos y el control de presupuestos dentro de cada proyecto.
- **Motivo:** son las capacidades mínimas necesarias para ofrecer una visión útil
  de la economía de cada ámbito.

## APP-003 — Aplicación familiar privada con cuentas existentes

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** la aplicación será privada para un entorno familiar. Las personas
  crearán su cuenta en una página de registro como la de TaskFlow. Solo podrá
  añadirse a un proyecto una persona que ya tenga cuenta en la aplicación.
- **Motivo:** evita invitaciones externas y limita la colaboración al entorno
  previsto.

## AUT-001 — Acceso local sin verificación de correo

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** no verificar el correo en la versión 1.0. Mantener un identificador
  interno como clave primaria y aplicar una restricción única al correo
  normalizado. Habilitar el registro local mediante configuración, exigir
  contraseñas de al menos 12 caracteres, limitar intentos, controlar sesiones y
  conservar los usuarios sin eliminación definitiva.
- **Motivo:** simplifica el entorno familiar local sin permitir cuentas duplicadas
  ni utilizar un dato modificable como identidad interna.

## APP-004 — Visibilidad y edición completas para los miembros

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** todos los miembros ven toda la información de sus proyectos y
  pueden modificar cualquier movimiento. Los propietarios, además, administran
  el proyecto, sus miembros y los roles.
- **Motivo:** el proyecto constituye un espacio financiero compartido y no una
  suma de registros privados de cada miembro.

## APP-005 — Conservación mediante archivado y papelera

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** los proyectos se archivan y los movimientos eliminados pasan a
  una papelera desde la que pueden restaurarse durante 30 días. La interfaz
  muestra la fecha prevista y después se eliminan automáticamente.
- **Motivo:** protege el historial financiero frente a borrados accidentales.

## APP-006 — Nombre de trabajo inicial

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** sustituida por APP-007.
- **Decisión:** utilizar un nombre de trabajo hasta confirmar el nombre
  definitivo.
- **Motivo:** permite dar identidad al diseño y la documentación sin bloquear el
  avance por una decisión de marca todavía abierta.

## FIN-001 — Alcance financiero ampliado de la versión 1.0

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada salvo los reembolsos entre miembros, pendientes de ratificar.
- **Decisión:** incluir cuentas con saldo inicial, gastos, ingresos,
  transferencias internas, devoluciones, categorías por proyecto, presupuestos
  mensuales, movimientos recurrentes y objetivos de ahorro. Los reembolsos entre
  miembros siguen pendientes de ratificar.
- **Motivo:** la versión 1.0 debe ser utilizable como herramienta financiera local
  completa y quedar después a la espera de mejoras derivadas del uso.

## FIN-002 — Presupuestos mensuales sin arrastre

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** usar mes natural, permitir un límite total y límites por categoría,
  no arrastrar sobrantes, mostrar avisos visuales al 80 % y 100 % y no ampliar el
  presupuesto automáticamente con los ingresos. El presupuesto y su saldo son
  comunes a todos los miembros; cada gasto reduce el disponible global.
- **Motivo:** proporciona un comportamiento comprensible y predecible para la
  primera versión.

## DAT-001 — CSV, categorización y metadatos ampliables

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** sustituida por DAT-002.
- **Decisión:** incluir importación CSV con detección de duplicados, exportación
  CSV, categorización automática siempre modificable, etiquetas y campos
  personalizados.
- **Motivo:** reduce el trabajo de introducción y permite adaptar los movimientos
  a distintas necesidades familiares.

## DAT-002 — Alcance de datos simplificado para la versión 1.0

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** mantener la exportación CSV y las etiquetas múltiples en la
  versión 1.0. Aplazar la importación CSV y la categorización automática. Los
  campos personalizados quedan pendientes de comprender y ratificar.
- **Motivo:** prioriza el registro manual fiable y los informes sin añadir todavía
  flujos complejos de importación y clasificación.

## FIN-003 — Resúmenes mensual y anual

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada; presentación concretada por REP-001.
- **Decisión:** incluir un resumen mensual del presupuesto, los gastos y su saldo,
  además de un resumen anual para analizar cuánto y dónde se gasta.
- **Motivo:** conocer el destino del dinero es el objetivo principal del producto.

## REP-001 — Informes comparables con acceso al detalle

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** comparar cada mes con el anterior y el equivalente del año previo;
  admitir hasta cinco periodos en gráficos; utilizar barras, líneas y ranking de
  categorías; mantener tablas completas y permitir abrir desde cada dato sus
  movimientos filtrados. Separar devoluciones, transferencias, ahorro e inversión
  y distinguir resultados reales de planificación futura.
- **Motivo:** facilita detectar cambios y explicar su origen sin mezclar conceptos
  financieros diferentes.

## DAT-005 — Exportación para Excel y auditoría inalterable

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** exportar el proyecto completo o la selección filtrada en CSV con
  formato español y UTF-8 compatible con Excel. Excluir la papelera de la salida
  normal y permitir a propietarios exportarla aparte. Conservar la auditoría de
  forma indefinida, filtrable e inalterable desde la aplicación.
- **Motivo:** ofrece portabilidad práctica y trazabilidad sin mezclar registros
  activos con elementos pendientes de eliminación.

## FIN-004 — Movimientos recurrentes

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada; ampliada por FIN-013.
- **Decisión:** admitir frecuencias diaria, semanal, mensual y anual, con final
  opcional y generación automática al llegar la fecha. Permitir editar una sola
  aparición o toda la serie futura e impedir duplicados.
- **Motivo:** automatiza gastos e ingresos previsibles sin perder control sobre
  excepciones concretas.

## FIN-013 — Recurrencias recuperables y objetivos respaldados

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** permitir recurrencias de gastos, ingresos, transferencias y
  aportaciones, con importe habitual editable, pausa, reanudación y omisión de la
  siguiente aparición. Resolver días inexistentes con el último día del mes, sin
  desplazar festivos. Tras un apagado, generar todas las apariciones vencidas que
  falten una sola vez y mostrar un aviso-resumen auditable.
- **Decisión:** vincular cada objetivo de ahorro a una cuenta real del proyecto;
  calcular su progreso con aportaciones y retiradas mediante transferencias;
  permitir superar el 100 %, avisar al vencer la fecha y conservar los objetivos
  archivados en el historial.
- **Motivo:** evita huecos y duplicados tras periodos sin servicio y hace que el
  progreso del ahorro corresponda a movimientos comprobables.

## FIN-005 — Presupuesto compartido sin deudas entre miembros

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada; concreta FIN-001.
- **Decisión:** todos los miembros consumen un único presupuesto mensual del
  proyecto. Se registra quién pagó, pero no se dividen gastos, no se calculan
  deudas y no se registran reembolsos o liquidaciones entre miembros en 1.0.
- **Motivo:** el objetivo principal es conocer dónde se gasta el dinero familiar,
  no saldar cuentas personales.

## FIN-006 — Renovación mensual y exceso de presupuesto

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** copiar al nuevo mes el presupuesto total y los límites por
  categoría anteriores, permitiendo editarlos, pero sin arrastrar el sobrante. Si
  se supera un límite, aceptar el gasto y mostrar el saldo negativo en rojo.
- **Motivo:** facilita la planificación repetida sin ocultar ni bloquear gastos
  reales.

## FIN-007 — Inversión externa de solo aportaciones

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** incluir en 1.0 una cuenta `Inversión externa — solo aportaciones`.
  El dinero enviado se registra como transferencia, reduce la cuenta de origen y
  aumenta el capital aportado, pero no cuenta como gasto, ingreso ni valor actual
  de cartera. Puede vincularse a un objetivo de ahorro.
- **Motivo:** mantiene correctos el flujo de caja y el análisis de consumo sin
  introducir todavía activos, precios, rentabilidad o fiscalidad de inversiones.

## FIN-008 — Historial continuo y comparativas temporales

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** no eliminar ni reiniciar información al cambiar de mes o año.
  Conservar saldos, recurrencias, objetivos e inversiones; crear presupuestos
  mensuales independientes y permitir comparar meses y años dentro del proyecto.
- **Motivo:** SmartWallet debe explicar la evolución financiera a largo plazo sin
  perder el detalle histórico.

## CAT-001 — Catálogo inicial ampliable por proyecto

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** entregar una mayoría de categorías y subcategorías desde el inicio
  y copiar el catálogo a cada proyecto. Cada proyecto puede adaptarlo y crear
  elementos propios sin afectar a los demás. Se adopta el catálogo definido en
  `REQUISITOS.md` para gastos e ingresos.
- **Motivo:** reduce el trabajo inicial sin impedir que los ámbitos familiares o
  personales tengan clasificaciones distintas.

## CAT-002 — Jerarquía, administración y presupuesto de categorías

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** limitar la jerarquía a categoría y subcategoría. Solo los
  propietarios administran el catálogo; ambos niveles admiten color e icono. En
  1.0 los presupuestos solo se asignan a categorías principales y acumulan el
  gasto de sus subcategorías.
- **Motivo:** ofrece detalle suficiente sin complicar la navegación ni los
  presupuestos de la primera versión.

## PER-001 — Propietarios estructurales y miembros colaboradores

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** ambos roles ven todos los datos del proyecto, gestionan cualquier
  movimiento, usan etiquetas, exportan CSV, consultan auditoría y operan la
  papelera. Solo los propietarios administran proyecto, miembros, roles, cuentas,
  categorías, presupuestos, objetivos, plantillas recurrentes y el catálogo de
  etiquetas. Los miembros pueden crear etiquetas, aportar a objetivos y modificar
  una aparición concreta de una recurrencia.
- **Motivo:** separa la administración estructural de la colaboración cotidiana
  sin convertir al miembro en un usuario de solo lectura.

## PER-002 — Propietario creador protegido

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** admitir varios propietarios y permitir que cualquiera de ellos
  ascienda miembros o degrade a otros propietarios. La persona que creó el
  proyecto conserva siempre el rol de propietario y no puede ser degradada ni
  expulsada mientras exista el proyecto.
- **Motivo:** permite compartir la administración sin que otro propietario pueda
  retirar el control al creador original.

## DAT-004 — Aviso de posible movimiento duplicado

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** considerar posible duplicado otro movimiento activo del mismo
  proyecto cuando coincidan exactamente categoría, subcategoría, fecha e importe.
  Mostrar el movimiento encontrado y permitir revisarlo o guardar igualmente. La
  aceptación de un caso no desactiva avisos posteriores.
- **Motivo:** detecta errores probables sin impedir compras legítimas repetidas el
  mismo día.

## FIN-012 — Reglas detalladas de cuentas y movimientos

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** adoptar cuentas corrientes, ahorro, efectivo, tarjeta de crédito e
  inversión externa; saldos iniciales no tratados como ingresos; movimientos
  confirmados; gastos de tarjeta en la compra; pagos y retiradas como
  transferencias; comisiones como gastos separados; devoluciones parciales
  vinculadas; saldos negativos advertidos y cuentas usadas archivables.
- **Motivo:** mantiene saldos y presupuestos coherentes sin introducir
  conciliación bancaria ni estados pendientes en la versión 1.0.

## DAT-003 — Sin campos personalizados en la versión 1.0

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** aplazar los campos personalizados y utilizar categorías,
  etiquetas y notas en la versión 1.0.
- **Motivo:** evita complejidad innecesaria en formularios, filtros y exportación.

## UX-001 — Continuidad visual con TaskFlow e identidad verde

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** reutilizar como referencia la estructura y los patrones de
  interfaz de TaskFlow, con una identidad propia en verde petróleo y menta. La
  versión 1.0 usará tema claro; el modo oscuro se aplaza.
- **Motivo:** mantiene una experiencia familiar entre aplicaciones del homelab
  sin confundir ambos productos.

## UX-002 — Navegación por proyectos y recorridos adaptables

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** abrir en `Mis proyectos`; no mezclar importes; utilizar un selector
  de proyecto y las secciones Resumen, Movimientos, Presupuestos, Cuentas,
  Objetivos, Informes, Miembros y Configuración. Los formularios y listados se
  adaptan a escritorio y móvil, y los informes ofrecen una alternativa numérica a
  cada gráfico.
- **Motivo:** reduce errores de contexto, conserva la claridad en pantallas
  pequeñas y permite entender los datos sin depender de una visualización.

## UX-003 — Lectura inmediata del estado mensual

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** mostrar en la parte superior o derecha del panel de escritorio tres
  indicadores diferenciados para ingresos, gastos y presupuesto. El indicador de
  presupuesto muestra progreso y saldo restante. Todos se actualizan después de
  crear, modificar, restaurar o retirar un movimiento, con texto e iconos además
  del color.
- **Motivo:** permite comprobar rápidamente cómo avanza el mes sin confundir
  ingresos, consumo y límite presupuestario.

## FIN-009 — Presupuesto habitual con excepciones mensuales

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada; precisa FIN-006.
- **Decisión:** el presupuesto se puede editar en cualquier mes. Al modificarlo,
  el propietario elige entre aplicar el cambio solo al mes seleccionado o cambiar
  el valor habitual desde ese mes en adelante. Un cambio excepcional, como el de
  Navidad, no se copia automáticamente a enero.
- **Motivo:** evita repetir la configuración normal sin convertir una necesidad
  estacional en el nuevo presupuesto permanente.

## FIN-010 — Pagas extraordinarias sin ampliación automática

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** registrar las pagas extraordinarias como ingresos de trabajo. El
  importe aumenta la cuenta y el balance del mes, pero no el presupuesto. El
  usuario puede conservarlo, transferirlo o modificar expresamente uno o varios
  presupuestos.
- **Motivo:** evita que recibir más dinero autorice gasto adicional de manera
  involuntaria y mantiene separados ingresos y planificación.

## FIN-011 — Asignación del sobrante mediante transferencias

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** ofrecer un resumen al terminar el mes, sin bloquearlo, y permitir
  destinar parte o todo el presupuesto no consumido a ahorro o inversión. La
  asignación crea una transferencia desde una cuenta, puede vincularse a un
  objetivo y no altera ingresos, gastos ni el sobrante histórico.
- **Motivo:** permite actuar sobre el ahorro planificado sin falsear el consumo ni
  confundir el presupuesto con el saldo bancario.

## UX-004 — Actualización automática entre miembros

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** reflejar inmediatamente las operaciones de la sesión actual y
  consultar automáticamente las de otros miembros para mostrarlas en un máximo
  orientativo de 15 segundos, sin recargar manualmente.
- **Motivo:** ofrece una sensación de información actualizada para el uso familiar
  sin añadir la complejidad de comunicación permanente en tiempo real.

## UX-005 — Sistema visual financiero derivado de TaskFlow

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** conservar la estructura, medidas, adaptación, radios y densidad de
  TaskFlow con una identidad verde petróleo y menta. Situar tres indicadores en
  la parte superior, utilizar una paleta semántica acompañada por iconos y texto,
  incorporar ocultación local de importes y mantener animaciones accesibles.
- **Motivo:** aprovecha una interfaz conocida, diferencia SmartWallet y permite leer
  el estado mensual de un vistazo sin depender únicamente del color.

## UX-006 — Resumen mensual como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar el primer esquema adaptable del resumen mensual como base
  para las demás pantallas, manteniendo abierta la posibilidad de pequeños
  ajustes durante su revisión y el uso real.
- **Motivo:** la composición propuesta encaja con la dirección esperada y permite
  avanzar sin considerar todavía inmutable cada detalle visual.

## UX-007 — Acceso, registro y selección de proyecto como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base el esquema adaptable de acceso, creación de
  cuenta y `Mis proyectos`, con la misma identidad visual del resumen mensual.
  Las tarjetas de proyecto muestran contexto y actividad, pero no mezclan ni
  anticipan importes financieros en la versión 1.0.
- **Motivo:** el recorrido aprobado mantiene continuidad con TaskFlow, separa con
  claridad la autenticación de la información financiera y permite elegir el
  proyecto antes de acceder a sus datos.

## UX-008 — Movimientos y aviso de duplicado como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de movimientos, con sus
  filtros, formulario lateral, selector dependiente de subcategoría y aviso no
  bloqueante cuando coincidan categoría, subcategoría, fecha e importe.
- **Motivo:** el recorrido aprobado permite registrar y localizar operaciones con
  rapidez, evitando falsos bloqueos cuando dos compras legítimas son iguales.

## UX-009 — Presupuesto mensual y cierre como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de presupuestos, con
  límite global, consumo por categorías principales, elección entre excepción
  mensual y nuevo valor habitual, y cierre que permite destinar el sobrante
  mediante una transferencia real.
- **Motivo:** la pantalla aprobada mantiene separados presupuesto, gasto, saldo de
  las cuentas y ahorro, y hace visible el efecto de cada cambio antes de aplicarlo.

## UX-010 — Cuentas y transferencias como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de cuentas, con resumen
  separado de liquidez, ahorro, capital aportado y deuda de tarjetas; creación de
  cuentas con saldo inicial fechado y transferencias que actualizan ambos saldos
  sin modificar ingresos, gastos ni presupuesto.
- **Motivo:** el recorrido aprobado explica las diferencias entre tipos de cuenta
  y evita contabilizar dos veces pagos de tarjeta, ahorro o inversión.

## UX-011 — Objetivos de ahorro como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de objetivos, con el
  progreso respaldado por aportaciones explícitas, fecha opcional, vinculación a
  una cuenta de ahorro o inversión y posibilidad de superar el 100 %.
- **Motivo:** el recorrido aprobado diferencia con claridad un objetivo del saldo
  total de una cuenta y permite seguir aportaciones y retiradas sin atribuirle
  dinero automáticamente.

## UX-012 — Informes y comparaciones como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de informes, separada en
  vistas mensual, anual y comparativa. Mantener la comparación automática con el
  mes anterior y el mismo mes del año previo, la selección de hasta cinco meses
  o años, el detalle por categorías y las tablas numéricas equivalentes.
- **Motivo:** la pantalla aprobada permite entender la evolución y el destino del
  dinero sin mezclar proyectos ni confundir resultados reales con planificación.

## UX-013 — Miembros y roles como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de miembros, con alta por
  el correo exacto de una cuenta existente, incorporación inicial como miembro,
  cambios posteriores de rol y retirada inmediata del acceso conservando la
  atribución histórica. El propietario creador mantiene un rol protegido.
- **Motivo:** el recorrido aprobado hace visibles los permisos y evita tanto las
  invitaciones a personas sin cuenta como la pérdida accidental del propietario
  creador o del historial financiero.

## UX-014 — Configuración, papelera y auditoría como referencia visual

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar como base la pantalla adaptable de configuración, con
  datos regionales fijos para la versión 1.0, papelera con restauración y fecha
  de eliminación a los 30 días, auditoría filtrable e inalterable y archivado
  reversible del proyecto en modo de solo lectura.
- **Motivo:** el recorrido aprobado reúne el mantenimiento sensible del proyecto,
  explica las consecuencias de cada acción y evita la eliminación definitiva de
  proyectos durante la primera versión.

## DAT-006 — Representación financiera y trazabilidad

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** guardar importes como céntimos enteros; reconstruir saldos con
  apuntes de cuenta; versionar el presupuesto habitual y crear copias mensuales;
  recalcular informes sin congelar cierres; conservar valores anteriores y
  posteriores en auditoría; y reactivar una pertenencia anterior siempre con rol
  inicial de miembro.
- **Motivo:** evita redondeos y doble contabilización, mantiene coherencia entre
  saldos e informes y conserva un historial continuo sin recuperar permisos
  elevados accidentalmente.

## TEC-001 — Laravel, BEM y JavaScript vanilla

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada; ampliada por TEC-004.
- **Decisión:** usar Laravel para la aplicación, CSS con metodología BEM y
  JavaScript vanilla para la interacción del navegador. Se permiten bibliotecas
  de terceros y cada incorporación se documentará con su finalidad.
- **Motivo:** elección expresa del responsable y continuidad con conocimientos y
  patrones ya utilizados en TaskFlow.

## TEC-002 — MySQL independiente

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada; ampliada por TEC-004.
- **Decisión:** utilizar MySQL y una base, credenciales y permisos exclusivos para
  esta aplicación y para cada entorno.
- **Motivo:** elección expresa del responsable. Mantiene el requisito común de
  aislamiento aunque no adopta la propuesta anterior de PostgreSQL para la
  infraestructura web.

## TEC-003 — Entorno con Docker

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** ejecutar el proyecto mediante Docker Compose con contenedores
  propios e independientes de TaskFlow para la aplicación, MySQL 8.4 LTS y
  Mailpit, además de una base de pruebas aislada.
- **Motivo:** elección expresa del responsable y necesidad de un entorno
  reproducible.

## OPS-001 — Versión 1.0 completamente funcional en local

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** completar, probar y documentar la versión 1.0 en un entorno local
  con Docker, como TaskFlow. El acceso remoto y la publicación en Internet quedan
  fuera de esta entrega.
- **Motivo:** permite utilizar el producto y obtener mejoras reales sin introducir
  todavía infraestructura pública.

## TEC-004 — Versiones y arquitectura equivalentes a TaskFlow

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar PHP 8.5, Laravel 13, MVC, Blade, MySQL 8.4 LTS y Vite;
  reutilizar componentes revisados de TaskFlow dentro de un proyecto y repositorio
  independientes.
- **Motivo:** reduce el tiempo de aprendizaje y aprovecha una base ya aceptada sin
  mezclar datos ni ciclos de vida.

## TEC-005 — Arquitectura local y base de pruebas bajo demanda

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** utilizar una aplicación Laravel monolítica modular y seis
  servicios de Docker Compose: aplicación, programador, Vite, MySQL, MySQL de
  pruebas y Mailpit. La base de pruebas será temporal, independiente y se
  iniciará únicamente al ejecutar la suite automática.
- **Motivo:** conserva la sencillez del uso diario y evita que las operaciones
  destructivas de las pruebas puedan modificar los datos de desarrollo.

## SEG-001 — Acceso técnico y auditoría

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** no impedir mediante cifrado integral que el administrador del PC o
  de MySQL acceda técnicamente a los datos. No habrá panel global de finanzas. Se
  auditarán autores, acciones y valores modificados en operaciones sensibles.
- **Motivo:** prioriza simplicidad local manteniendo aislamiento entre proyectos y
  trazabilidad de cambios.

## OPS-002 — Sin copias cifradas durante la versión local

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** no realizar copias automáticas ni cifradas para la versión 1.0
  local y aceptar el riesgo de depender del volumen Docker. Las copias protegidas
  y la restauración operativa serán obligatorias al desplegar en el homelab.
- **Motivo:** el despliegue local se considera una etapa anterior a la operación
  permanente.

## OPS-003 — Nombres de los entornos de trabajo

- **Fecha:** 14 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** llamar `Alpha` a los prototipos interactivos utilizados para
  validar la experiencia y `Desarrollo local` a la aplicación Laravel real
  ejecutada con Docker. El futuro despliegue en el homelab será un entorno
  independiente.
- **Motivo:** evita confundir comportamientos simulados durante el diseño con la
  lógica, persistencia y pruebas de la aplicación real.

## PLA-001 — Fecha, coste y escala objetivo

- **Fecha:** 13 de septiembre de 2026.
- **Estado:** aceptada.
- **Decisión:** completar la versión 1.0 antes del 31 de diciembre de 2026 con
  coste de desarrollo de 0 €. Diseñar para dos usuarios y tres proyectos, y probar
  hasta 100.000 movimientos por proyecto.
- **Motivo:** refleja el uso familiar esperado y mantiene margen suficiente para
  el historial a largo plazo.

## SEG-002 — Endurecimiento de la versión local

- **Fecha:** 17 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** cifrar las sesiones de servidor, utilizar `SameSite=Strict`,
  aplicar CSP y cabeceras defensivas, impedir la caché de páginas financieras,
  limitar todos los recorridos de recuperación y desactivar las rutas de archivos
  no utilizadas. El entorno y la base técnicos solo se muestran fuera de
  producción.
- **Motivo:** reducir exposición accidental y superficie de ataque sin ampliar el
  alcance funcional ni exigir infraestructura externa en la versión local.

## Decisiones pendientes inmediatas

No quedan decisiones de marca pendientes para la versión 1.0.

## REL-001 — Identificación de la primera candidata

- **Fecha:** 17 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** identificar el código funcionalmente cerrado como `1.0.0-rc.1` y
  reservar `1.0.0` para después del ensayo final de arranque y la comprobación
  humana con zoom nativo al 200 %. El nombre de trabajo no impedirá liberar la
  versión.
- **Motivo:** distinguir con claridad una candidata validada de una entrega final
  todavía pendiente de dos controles operativos.

## REL-002 — Publicación local de 1.0.0

- **Fecha:** 19 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** promover `1.0.0-rc.1` a `1.0.0` y distribuir la entrega mediante
  el repositorio Git oficial o un archivo ZIP limpio.
- **Motivo:** el alcance, la aceptación, la seguridad, las pruebas y la puesta en
  marcha están cerrados, y existe una guía completa para instalar la aplicación
  en otro equipo o trasladar sus datos.

## APP-007 — SmartWallet como nombre definitivo

- **Fecha:** 19 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** adoptar `SmartWallet` como nombre definitivo y aplicarlo a la
  interfaz, configuración, comandos, recursos Docker, bases de datos,
  exportaciones y documentación.
- **Motivo:** cerrar la identidad del producto antes de distribuir la versión 1.0
  en otros equipos.

## REL-003 — Repositorio oficial de SmartWallet

- **Fecha:** 19 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** publicar el código y la configuración reproducible en
  <https://github.com/Vivas1998/SmartWallet>, manteniendo fuera del repositorio
  claves, bases de datos, registros, dependencias y datos financieros.
- **Motivo:** permitir instalaciones y actualizaciones trazables en otros equipos
  sin distribuir información local o sensible.

## CAL-001 — Calendario financiero selectivo para 1.1

- **Fecha:** 22 de septiembre de 2026.
- **Estado:** aceptada e implementada en `1.1.0`.
- **Decisión:** el calendario mostrará todas las recurrencias y planificaciones
  con vencimiento, pero solo incluirá los demás movimientos cuando tengan
  activada la opción `Mostrar en el calendario`. Las planificaciones no afectarán
  a la contabilidad hasta convertirse en movimientos reales y nunca se
  duplicarán ambos registros en la vista.
- **Decisión complementaria:** un evento realizado conservará su fecha de
  vencimiento y mostrará la fecha efectiva junto con su condición de anticipado,
  puntual o tardío.
- **Decisión complementaria:** el disponible real utilizará todo el gasto neto
  contabilizado del mes, aunque parte de sus movimientos no se muestre en el
  calendario. El disponible estimado restará además los gastos pendientes del
  calendario; los ingresos previstos se mostrarán aparte y no ampliarán el
  presupuesto. Los filtros afectarán a la agenda, no a este resumen global.
- **Motivo:** mantener un calendario útil y legible, preservar la diferencia entre
  previsión y realidad y permitir analizar el cumplimiento de vencimientos.

## REL-004 — Publicación local de 1.1.0

- **Fecha:** 23 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** publicar como `1.1.0` el calendario financiero selectivo, las
  planificaciones puntuales, su integración con recurrencias y el resumen real
  frente a estimado, manteniendo el destino local con Docker Compose.
- **Motivo:** el alcance aprobado para 1.1 está implementado, documentado y
  validado sin defectos críticos conocidos.

## REL-005 — Criterio de versionado y publicación de 1.1.1

- **Fecha:** 23 de septiembre de 2026.
- **Estado:** aceptada e implementada.
- **Decisión:** utilizar `x.0.0` para versiones grandes y estables, `x.x.0` para
  mejoras funcionales y `x.x.x` para pequeños errores, archivos auxiliares y
  ajustes sin funciones nuevas. Publicar la incorporación correctiva de la DDL
  como `1.1.1`, conservando `1.1.0` como la entrega del calendario.
- **Motivo:** diferenciar claramente una ampliación funcional de un ajuste de
  distribución o mantenimiento perteneciente a la misma línea de versión.
