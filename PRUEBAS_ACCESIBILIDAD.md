# Pruebas de accesibilidad y aceptación

Fecha de ejecución: 16 de septiembre de 2026.

## Resultado

El bloque termina sin defectos críticos conocidos en el alcance revisado. Las
correcciones encontradas durante la inspección se incorporaron antes de repetir
las comprobaciones y cerrar el resultado.

La revisión se realizó contra una instancia temporal en el puerto `8011`, con
`APP_ENV=testing` y la base aislada `smartwallet_test`. La base persistente
`smartwallet` no recibió los datos de aceptación. Al terminar se detuvieron tanto
la instancia temporal como MySQL de pruebas.

## Alcance revisado

Se inspeccionaron 18 pantallas:

- públicas: inicio de sesión, registro y recuperación de contraseña;
- privadas: portada de proyectos, panel del proyecto, movimientos, presupuestos,
  informe mensual, informe anual, comparación de periodos, cuentas, objetivos,
  recurrencias, categorías, etiquetas, miembros, auditoría, configuración y
  perfil.

También se ejecutaron recorridos funcionales de registro y acceso, creación de
un proyecto con presupuesto, alta de ingresos y gastos, navegación del proyecto,
selectores dependientes de categoría y subcategoría y actualización inmediata
del resumen mensual.

## Comprobaciones realizadas

### Estructura y semántica

En todas las pantallas se comprobó:

- idioma español declarado, un único contenido principal y un único encabezado
  de primer nivel;
- nombres accesibles en campos, botones y enlaces de acción;
- ausencia de identificadores duplicados y referencias ARIA rotas;
- ausencia de controles enfocables ocultos mediante `aria-hidden`;
- cabeceras presentes en las tablas;
- pestaña y opción activa identificadas mediante `aria-current`;
- barras de presupuesto y objetivos expuestas como `progressbar`, con valor y
  límites comprensibles para tecnologías de asistencia.

### Teclado y foco

- El primer tabulador de las páginas públicas y privadas muestra el enlace
  «Saltar al contenido principal».
- Al activarlo, el foco llega al elemento `main`.
- Los controles recorridos mediante teclado conservan un contorno visible de
  aproximadamente 3 px.
- El asistente de creación mueve el foco al título de cada paso y solo expone el
  panel activo.

### Adaptación y ampliación

Se revisaron anchos CSS de 360, 768, 1024 y 1440 px, además de 720 px como
equivalente de una ventana de 1440 px ampliada al 200 %. No apareció
desbordamiento horizontal global. En móvil, la navegación interna del proyecto
permite desplazamiento horizontal local; desde tableta se reparte en varias
líneas cuando es necesario.

La automatización del navegador no permitió modificar de forma fiable el zoom
nativo de su interfaz. Por ello, la condición del 200 % se reprodujo reduciendo
a la mitad el área CSS disponible, que comprueba el mismo comportamiento de
redistribución. Antes de publicar 1.0 se recomienda una comprobación humana breve
con el zoom nativo al 200 % en el navegador de uso real.

### Contraste

Se analizaron los colores calculados de las 18 pantallas conforme al objetivo AA.
La primera pasada detectó dos valores limítrofes:

- el texto secundario sobre la superficie atenuada;
- el color inicial de la categoría «Alimentación» con texto blanco.

Se ajustaron a `#58706a` y `#B25900`, respectivamente. La repetición completa no
detectó contrastes insuficientes en el alcance analizado. Una migración actualiza
únicamente las categorías iniciales que todavía conservan el color anterior, sin
sobrescribir personalizaciones del usuario.

## Validación automática complementaria

- Laravel Pint: 119 archivos correctos.
- Suite completa: 88 pruebas y 554 aserciones superadas.
- Compilación Vite de producción completada sin errores.
- La suite confirmó de nuevo el uso exclusivo de `smartwallet_test`.

## Conclusión

La interfaz cumple el bloque acordado para móvil desde 360 px, tableta,
escritorio, navegación por teclado, foco visible, contraste AA y adaptación
equivalente al 200 %. La comprobación manual de zoom nativo queda como control
final de liberación, no como defecto funcional conocido.
