# Accesibilidad y adaptación del calendario de SmartWallet 1.1

Fecha de ejecución: 23 de septiembre de 2026.

## Resultado

La nueva interfaz de calendario termina la revisión sin defectos críticos
conocidos. Se probó sobre una instalación temporal conectada exclusivamente a
`smartwallet_test` y con datos representativos reales y previstos.

## Estructura y nombres accesibles

- La pantalla contiene un único `h1` y un único contenido principal.
- No se detectaron identificadores duplicados ni referencias ARIA rotas.
- Los cinco filtros tienen etiqueta y nombre accesible.
- La cuadrícula usa una tabla con `caption` y siete cabeceras de columna.
- Cada día ofrece un enlace descriptivo con fecha y cantidad de eventos.
- El resumen real y estimado se divide en regiones tituladas.
- Los estados no dependen solo del color: siempre incluyen texto como
  `Previsto`, `Vencido` o `Realizado`.
- La agenda móvil conserva concepto, importe, origen, categoría, cuenta, miembro
  y enlace de detalle.

Los únicos controles sin etiqueta detectados por la inspección automática
fueron los campos ocultos técnicos `_token` y `month`; no son interactivos ni
forman parte del recorrido de teclado.

## Teclado y foco

- El enlace `Saltar al contenido principal` está presente.
- Al activarlo mediante teclado, el foco llega a `main` y conserva un contorno
  sólido visible de aproximadamente 3 px.
- Los filtros, la navegación mensual, los días y las acciones son controles
  nativos o enlaces con nombres explícitos.

## Escritorio, ampliación y móvil

Se revisaron estos tamaños CSS:

| Tamaño | Resultado |
|---|---|
| 1440 × 900 px | Cuadrícula mensual visible, agenda oculta y sin desbordamiento global. |
| 720 × 900 px | Reflujo equivalente a una ventana de 1440 px ampliada al 200 %, agenda visible y sin desbordamiento global. |
| 360 × 800 px | Agenda cronológica visible, cuadrícula oculta y sin desbordamiento global. |

La navegación horizontal del proyecto mantiene un desplazamiento local en
móvil, sin ensanchar el documento. La adaptación de 720 px reproduce el espacio
CSS disponible al aplicar un zoom nativo del 200 %; se recomienda repetir además
una comprobación breve con el navegador habitual cuando cambie su versión.

## Contraste y lectura visual

El calendario reutiliza los tokens ya validados de SmartWallet para texto,
superficies, bordes y foco. Los nuevos colores de estado se acompañan de texto e
iconografía y el resumen diferencia visual y semánticamente los datos
contabilizados de las estimaciones. La revisión no encontró información que
dependa exclusivamente del color ni texto ilegible en el alcance comprobado.

## Evidencia funcional relacionada

Con un presupuesto de 1.000,00 €, un gasto real de 40,00 € y una planificación
pendiente de 125,50 €, la misma pantalla mostró correctamente:

- 40,00 € contabilizados;
- 125,50 € pendientes;
- 165,50 € de gasto previsto total;
- 960,00 € disponibles reales;
- 834,50 € disponibles estimados.

Esto confirmó que las cifras visibles, sus textos alternativos y la agenda
representan la misma información sin convertir la previsión en contabilidad.
