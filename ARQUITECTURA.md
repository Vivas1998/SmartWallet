# Arquitectura de SmartWallet 1.0

Estado: implementada y validada para la candidata local 1.0.

Última actualización: 17 de septiembre de 2026.

## 1. Enfoque

SmartWallet será una aplicación web monolítica modular. Laravel resolverá las rutas,
permisos, validación, cálculos, persistencia y generación de HTML mediante Blade.
JavaScript vanilla añadirá únicamente la interacción necesaria y el CSS se
organizará con BEM.

No se crearán una API y una aplicación cliente separadas para la versión 1.0.
Esta solución reduce piezas, facilita las pruebas y sigue el patrón ya validado
en TaskFlow.

## 2. Componentes

| Componente | Tecnología | Responsabilidad |
|---|---|---|
| Navegador | HTML, CSS BEM y JavaScript modular | Interfaz adaptable y accesible. |
| Aplicación | PHP 8.5 y Laravel 13 | Rutas, permisos, reglas financieras, correo y vistas. |
| Programador | Misma imagen PHP | Recurrencias y purga de la papelera. |
| Recursos web | Node.js 24 y Vite | Compilar CSS y JavaScript durante el desarrollo. |
| Persistencia | MySQL 8.4 LTS | Datos de desarrollo de SmartWallet. |
| Pruebas | MySQL 8.4 LTS independiente | Ejecutar la suite sin tocar datos de desarrollo. |
| Correo local | Mailpit | Recuperación de contraseña durante el desarrollo. |
| Orquestación | Docker Compose | Versiones, red, volúmenes y arranque reproducible. |

## 3. Servicios de Docker Compose

Se propone utilizar seis servicios:

1. `app`: aplicación Laravel y servidor web local;
2. `scheduler`: programador de Laravel usando la misma imagen de `app`;
3. `node`: Vite durante el desarrollo;
4. `mysql`: base persistente de desarrollo;
5. `mysql_test`: base temporal y aislada, iniciada solo al ejecutar pruebas;
6. `mailpit`: buzón local para recuperación de contraseña.

`scheduler` no contiene otra copia del proyecto: monta el mismo código y ejecuta
el proceso periódico. Separarlo evita mezclar varios procesos de larga duración
dentro del contenedor web.

`app`, `node`, `mysql` y `mailpit` publican comprobaciones de salud. El
programador no empieza hasta que Laravel está preparado y el script de inicio no
da el entorno por disponible hasta que han respondido Laravel, Vite y MySQL.

No se propone un contenedor de colas en la versión 1.0. El escaso volumen de
correo y exportaciones permite ejecutarlos de forma síncrona. Podrá añadirse un
trabajador cuando exista una necesidad real.

## 4. Puertos y aislamiento

Valores iniciales propuestos, modificables desde `.env`:

| Servicio | Puerto del PC |
|---|---:|
| SmartWallet | `8010` |
| Vite | `5174` |
| MySQL de desarrollo | `3308`, limitado al PC local |
| Mailpit | `8026`, limitado al PC local |

`mysql_test` no publica ningún puerto, no usa un volumen persistente y se
configura mediante un perfil de Docker Compose para permanecer apagado durante
el uso normal. Todos los servicios utilizan una red `smartwallet` y volúmenes
propios. No se comparten red, base, credenciales ni volúmenes con TaskFlow.

La aplicación normal utiliza `DB_HOST=mysql` y la base `smartwallet`. La suite
automática utiliza `APP_ENV=testing`, `DB_HOST=mysql_test` y la base
`smartwallet_test`. Un comando de diagnóstico mostrará entorno, servidor y nombre
de base sin revelar contraseñas, y el pie de la interfaz identificará el entorno
como `Desarrollo local`.

## 5. Organización interna de Laravel

Se mantendrán las convenciones de Laravel y se agrupará la lógica por áreas:

1. identidad y sesiones;
2. proyectos, miembros y roles;
3. cuentas y apuntes;
4. categorías y etiquetas;
5. movimientos, transferencias y devoluciones;
6. presupuestos mensuales;
7. recurrencias;
8. objetivos de ahorro;
9. informes y exportación;
10. papelera y auditoría.

Cada área utilizará:

- modelos Eloquent para relaciones y persistencia;
- Form Requests para validar entradas;
- Policies para los permisos;
- acciones o servicios para operaciones financieras;
- consultas específicas para paneles e informes;
- controladores pequeños que coordinan la petición;
- vistas Blade sin consultas ni reglas contables.

No se introducirán paquetes de arquitectura adicionales mientras las
convenciones nativas de Laravel sean suficientes.

## 6. Escrituras financieras

Las operaciones sensibles se ejecutan dentro de transacciones MySQL.

Por ejemplo, una transferencia:

1. valida proyecto, cuentas, importe y permisos;
2. crea el movimiento;
3. crea el apunte de salida;
4. crea el apunte de entrada;
5. registra la auditoría;
6. confirma todo junto.

Si cualquier paso falla, no se guarda ninguno. El mismo patrón se utilizará para
pagos de tarjeta, devoluciones, aportaciones a inversión, restauraciones y
asignaciones a objetivos.

Los controladores no modificarán saldos directamente.

## 7. Aislamiento y permisos

Las rutas financieras estarán vinculadas al proyecto actual. Cada consulta
comprobará la pertenencia activa antes de recuperar datos.

Las Policies distinguirán:

- propietario creador;
- otros propietarios;
- miembros;
- personas sin acceso.

Las acciones estructurales comprobarán el rol de propietario. Los movimientos y
su papelera permitirán la gestión acordada a propietarios y miembros.

Se utilizarán identificadores internos, claves foráneas y validaciones del
proyecto para impedir relacionar una cuenta, categoría o movimiento con datos de
otro proyecto. Las pruebas intentarán expresamente acceder mediante
identificadores y direcciones conocidas de otros proyectos.

No existirá un panel de administrador global para consultar finanzas.

## 8. Recurrencias y tareas periódicas

El programador ejecutará al menos:

- generación de apariciones recurrentes vencidas;
- eliminación definitiva de movimientos con 30 días en la papelera;
- tareas de mantenimiento que se incorporen antes de cerrar la versión.

Además, al iniciar `app` se ejecutará una recuperación de apariciones vencidas.
La restricción única por serie y fecha hace que `app` y `scheduler` puedan
coincidir sin generar duplicados.

La recuperación mostrará en la aplicación un resumen de los movimientos creados
mientras estuvo apagada.

## 9. Interfaz

Blade renderizará la navegación y el contenido inicial. JavaScript vanilla se
dividirá por componentes y se limitará a:

- menús y paneles laterales;
- formularios dependientes;
- actualización visual inmediata de importes;
- filtros y confirmaciones;
- avisos de duplicados;
- gráficos y comparaciones.

Cada bloque de CSS tendrá un nombre BEM propio. Los colores, espaciados, radios y
medidas aprobados vivirán en variables del sistema de diseño.

La actualización entre dos personas empezará con refresco periódico ligero de
los datos visibles. No se añadirá WebSocket en la versión 1.0 salvo que las
pruebas demuestren que es necesario.

## 10. Autenticación y seguridad local

Laravel gestionará sesiones, recuperación de contraseña y protección CSRF.

Se aplican:

- correo normalizado y único;
- contraseña mínima de 12 caracteres;
- limitación de intentos de acceso;
- caducidad por inactividad;
- sesiones recordadas durante el plazo acordado;
- cierre de sesiones desde la cuenta;
- sesiones cifradas y cookies `HttpOnly` con `SameSite=Strict`;
- validación y autorización en el servidor para todas las acciones;
- secretos fuera del repositorio mediante `.env`;
- CSP, protección contra marcos, `nosniff`, política de referente y permisos del
  navegador restrictivos;
- `no-store` en las respuestas autenticadas y HSTS cuando la petición utiliza
  HTTPS;
- almacenamiento privado sin rutas HTTP mientras no exista una función de
  archivos.

La ausencia de verificación de correo no elimina la restricción de correo único.
Mailpit solo simula la entrega local de recuperación de contraseña.
El resultado completo y las condiciones de despliegue futuro se conservan en
`SEGURIDAD.md`.

## 11. Auditoría

Las acciones de dominio crearán la auditoría dentro de la misma transacción que
el cambio. Así no puede existir una modificación financiera confirmada sin su
registro correspondiente.

No se confiará únicamente en observadores genéricos de Eloquent, porque algunas
operaciones necesitan guardar el motivo, el usuario y los valores financieros de
forma explícita.

Contraseñas, tokens y datos de sesión quedan excluidos de la auditoría.

## 12. Pruebas

La suite automática utilizará `mysql_test` e incluirá:

- pruebas unitarias de cálculos y reglas;
- pruebas funcionales de formularios y recorridos;
- pruebas de Policies y aislamiento entre proyectos;
- pruebas de transacciones y saldos;
- pruebas de presupuesto, devolución y papelera;
- pruebas de recurrencia idempotente;
- pruebas de auditoría;
- pruebas de exportación CSV;
- un conjunto de rendimiento con hasta 100.000 movimientos.

El banco de escala se ejecuta mediante `scripts/benchmark.ps1`, exclusivamente
sobre `smartwallet_test`. Genera un histórico representativo, actualiza las
estadísticas del optimizador y comprueba panel, listados y filtros, informes,
saldos y recorrido CSV contra objetivos máximos documentados en
`PRUEBAS_RENDIMIENTO.md`.

La interfaz se revisó en 18 pantallas desde 360 px, en tableta y escritorio, con
teclado, foco visible, contraste AA y un área CSS equivalente al zoom del 200 %.
El resultado, las correcciones aplicadas y la comprobación manual de zoom nativo
recomendada antes de liberar 1.0 se documentan en
`PRUEBAS_ACCESIBILIDAD.md`. No se aceptará la versión con errores críticos
conocidos.

## 13. Estructura prevista

```text
2027-web-gastos/
├── .docker/
│   └── php/
├── web/
│   ├── app/
│   ├── database/
│   ├── resources/
│   │   ├── css/
│   │   ├── js/
│   │   └── views/
│   ├── routes/
│   └── tests/
├── compose.yaml
├── .env.example
└── documentación del proyecto
```

## 14. Decisiones aprobadas

El 14 de septiembre de 2026 se aprobaron:

1. aplicación monolítica modular con Blade;
2. seis servicios Docker separados;
3. `scheduler` independiente y sin contenedor de colas en la versión 1.0;
4. operaciones financieras y auditoría dentro de transacciones MySQL;
5. permisos mediante rutas vinculadas al proyecto y Policies;
6. recuperación de recurrencias al arrancar y ejecución periódica posterior;
7. actualización entre miembros mediante refresco periódico ligero, sin
   WebSockets inicialmente;
8. puertos locales propuestos `8010`, `5174`, `3308` y `8026`.

La base `mysql_test` se conserva por seguridad, pero solo se inicia bajo demanda
durante las pruebas y se destruye al terminar. Nunca contiene datos reales ni de
desarrollo.
