# Pruebas de rendimiento y escala

Última ejecución completa: 16 de septiembre de 2026.

## Objetivo

SmartWallet se diseña para un uso doméstico habitual de dos personas y tres
proyectos, pero debe seguir siendo utilizable con hasta 100.000 movimientos en
un único proyecto. Este banco comprueba las consultas que más crecen con el
histórico sin imponer ese número como límite visible.

## Seguridad de la prueba

El banco solo puede ejecutarse cuando Laravel está en `testing` y el nombre de
la base termina en `_test`. Además, exige una base vacía antes de generar datos.
El lanzador utiliza `smartwallet_test`, alojada en memoria temporal, y detiene el
contenedor al finalizar incluso si se produce un error. Nunca consulta ni
modifica la base persistente `smartwallet`.

Desde PowerShell se ejecuta con:

```powershell
.\scripts\benchmark.ps1 -Movements 100000
```

Para una comprobación rápida durante el desarrollo se puede indicar una cifra
entre 1.000 y 100.000. La validación del objetivo final siempre se realiza con
100.000.

## Datos representativos

La prueba crea un usuario, un proyecto, tres cuentas y el catálogo inicial de
categorías. Distribuye 100.000 movimientos durante algo más de cuatro años con
esta mezcla:

- 70 % de gastos;
- 18 % de ingresos;
- 8 % de transferencias;
- 4 % de aportaciones a inversión;
- una etiqueta presente en el 10 % de los movimientos.

Después actualiza las estadísticas de MySQL para simular una base estable antes
de medir. Cada recorrido se calienta una vez y la tabla final diferencia tiempo
total, tiempo de base de datos, consulta más lenta y número de consultas.

## Resultado validado con 100.000 movimientos

Medición realizada en el entorno Docker local. Los tiempos pueden variar según
el equipo, por lo que el criterio automático es el objetivo máximo y no un valor
exacto.

| Recorrido | Resultado | Objetivo máximo |
| --- | ---: | ---: |
| Panel mensual | 3,2 ms | 1.000 ms |
| Listado mensual paginado | 5,6 ms | 1.200 ms |
| Filtro por categoría | 1,4 ms | 1.200 ms |
| Filtro por etiqueta | 3,8 ms | 1.500 ms |
| Informe anual | 128,5 ms | 2.500 ms |
| Comparación de cinco años | 487,1 ms | 8.000 ms |
| Saldos de cuentas | 84,5 ms | 1.200 ms |
| Recorrido completo para CSV | 4.513,4 ms | 30.000 ms |

Todos los recorridos cumplieron el objetivo. El recorrido CSV carga los datos y
sus relaciones en lotes reales de 500 registros; no incluye el tiempo de
descarga del archivo desde el navegador.

## Optimizaciones aplicadas

- Los índices de movimientos comienzan por proyecto y estado de papelera antes
  de los campos de filtro y fecha.
- El listado mensual usa el índice `movements_project_active_date` y estimó 928
  filas para el mes medido.
- El índice compuesto de movimiento original, estado e importe evita recorrer
  el histórico al calcular devoluciones. El listado mensual pasó de unos 411 ms
  en la referencia inicial a 5,6 ms.
- El panel calcula gastos, devoluciones e ingresos en una sola consulta; junto a
  los últimos movimientos utiliza dos consultas en vez de cuatro.
- El presupuesto agrupa gastos y devoluciones por categoría en una sola lectura.
- La papelera dispone de índices separados para su vista por proyecto y para la
  purga global de registros vencidos.

## Repetición y aceptación

El comando devuelve un código de error si procesa menos movimientos de los
esperados o si cualquier recorrido supera su objetivo. Antes de una entrega se
ejecutarán tanto este banco como la suite funcional completa. Esta prueba evita
regresiones evidentes, pero no sustituye la revisión de experiencia real en el
navegador ni una futura prueba en el hardware definitivo del homelab.
