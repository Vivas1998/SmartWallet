# Accesibilidad y adaptación de SmartWallet 1.2

Fecha de ejecución: 24 de septiembre de 2026.

## Resultado

Las interfaces incorporadas o modificadas para 1.2.0 terminan la revisión sin
defectos críticos conocidos. Se probaron el acceso, `Mis proyectos`, el panel del
proyecto, el presupuesto jerárquico, los campos personalizados y el formulario
de movimientos sobre una instalación temporal conectada exclusivamente a
`smartwallet_test`.

## Estructura y nombres accesibles

- Cada pantalla revisada contiene un único `h1` y un único contenido principal.
- No se detectaron identificadores duplicados ni referencias ARIA rotas.
- Las tarjetas presupuestarias exponen una barra con nombre accesible por
  proyecto.
- Los formularios de movimiento y campos personalizados conservan etiquetas,
  agrupaciones y nombres explícitos.
- Los cinco ejemplos iniciales indican mediante texto su tipo, alcance y número
  de valores.
- Los estados de presupuesto incluyen importes, porcentajes y etiquetas; la
  advertencia no depende exclusivamente del color.
- La navegación horizontal del proyecto mantiene un desplazamiento local en
  móvil y no ensancha el documento.

## Teclado y foco

- El primer elemento alcanzable es `Saltar al contenido principal`.
- Al activarlo mediante teclado, el foco llega a `main`.
- El enlace de salto, la marca, el selector de apariencia, el perfil, el cierre
  de sesión, las acciones y las tarjetas conservan un contorno sólido visible de
  aproximadamente 2,9 px.
- El selector de apariencia utiliza un control nativo y permite recorrer sus
  opciones sin ratón.

## Escritorio, ampliación y móvil

Se revisaron estos tamaños CSS:

| Tamaño | Resultado |
|---|---|
| 1440 × 900 px | Tarjetas equilibradas y formularios completos, sin desbordamiento global. |
| 720 × 900 px | Reflujo equivalente a una ventana de 1440 px ampliada al 200 %, sin pérdida de controles ni desbordamiento global. |
| 360 × 800 px | Acceso, panel, presupuesto, campos y movimientos utilizables; navegación de proyecto con desplazamiento local. |

En los tres tamaños, `Mis proyectos`, presupuesto, campos personalizados y
movimientos conservaron un ancho de documento igual al área visible.

## Contraste y temas

Se comprobaron muestras representativas de texto general, títulos, texto
secundario, antetítulos, botón principal y tarjeta de proyecto:

| Tema | Contraste mínimo medido | Resultado |
|---|---:|---|
| Claro | 4,70:1 | Cumple AA para texto normal. |
| Oscuro | 8,87:1 | Cumple AA para texto normal. |

El texto general obtuvo 12,19:1 en claro y 16,69:1 en oscuro. La preferencia se
mantuvo al navegar y ambos temas conservaron los mismos contenidos y acciones.

## Evidencia funcional relacionada

Con un presupuesto de 1.000,00 € y un gasto de 150,00 €, la tarjeta mostró
850,00 € disponibles y un 15 % consumido. El desglose de `Supermercado` mostró
150,00 € utilizados sobre un límite de 200,00 € y un 75 %. La información
visible, las barras accesibles y los cálculos representaron el mismo estado.

No se registraron errores ni advertencias en la consola del navegador durante
el recorrido.
