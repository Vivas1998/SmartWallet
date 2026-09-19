# Sistema de diseño de SmartWallet

**Fecha:** 13 de septiembre de 2026
**Estado:** dirección visual y resumen mensual aprobados; resto de pantallas pendiente
**Nombre:** SmartWallet, definitivo

## 1. Relación con TaskFlow

SmartWallet reutiliza patrones aprendidos en TaskFlow, no su identidad. Mantiene la
barra lateral de 264 px en escritorio, la cabecera contextual, el menú móvil por
debajo de 900 px y la disposición en una columna a 520 px. La interfaz se diseña
desde 360 px y admite teclado, foco visible, zoom al 200 % y movimiento reducido.

## 2. Paleta inicial

| Uso | Referencia inicial |
|---|---|
| Fondo general | Verde grisáceo muy claro `#F2F7F5` |
| Superficie | Blanco `#FFFFFF` |
| Texto principal | Verde carbón `#14261F` |
| Texto secundario | Verde gris `#607269` |
| Borde | Verde gris claro `#D8E5E0` |
| Principal | Verde petróleo `#0F766E` |
| Principal intenso | Verde petróleo oscuro `#115E59` |
| Principal suave | Menta clara `#DDF5ED` |
| Barra lateral | Verde petróleo nocturno `#102D2A` |
| Ingresos | Esmeralda `#168A68` |
| Gastos | Coral `#C94F5D` |
| Advertencia | Ámbar `#A26400` |
| Exceso o error | Rojo `#B42335` |
| Transferencias | Azul `#2F6FDB` |
| Ahorro | Menta `#2FB58D` |
| Inversión | Violeta `#7358C7` |
| Devoluciones | Turquesa `#168A9C` |

Estos valores son tokens de partida. Antes de implementarlos de forma definitiva
se comprobará contraste AA en cada combinación y se ajustarán tonos sin cambiar
su significado.

## 3. Geometría y tipografía

- Radios de 8 px para controles, 14 px para tarjetas y 20 px para superficies
  destacadas.
- Sombras suaves y bordes visibles; ninguna información depende de una sombra.
- Pila tipográfica del sistema con aspecto cercano a Inter y sin descargas
  externas.
- Iconos lineales incluidos en el proyecto y acompañados por etiquetas en todas
  las acciones importantes.

## 4. Indicadores financieros

El escritorio coloca en una fila superior tres tarjetas con indicadores
circulares:

1. Ingresos del mes.
2. Gastos netos y variación frente al mes anterior.
3. Presupuesto consumido y saldo restante.

Solo Presupuesto utiliza el anillo como porcentaje de progreso. Ingresos y Gastos
mantienen la forma circular para la lectura rápida, pero no inventan objetivos o
porcentajes. Al 80 % el presupuesto usa ámbar y al superarse usa rojo.

## 5. Privacidad y movimiento

El encabezado incorpora un control para ocultar importes. La elección se guarda
en el navegador y sustituye cantidades por una representación neutra sin alterar
los datos. Las transiciones son breves y desaparecen cuando el dispositivo
solicita movimiento reducido.

## 6. Marca y acceso

La marca de SmartWallet utiliza una `S` centrada dentro de un cuadrado. En
escritorio, acceso y registro conservan
la composición dividida de TaskFlow; en móvil se reduce al formulario y la marca.

Mensaje principal:

> Tus cuentas claras. Tus decisiones, con perspectiva.

## 7. Pantallas que deben validarse

- Inicio de sesión y registro.
- Mis proyectos.
- Resumen mensual.
- Movimientos y formulario.
- Presupuestos.
- Informes.
- Cuentas y objetivos.
- Miembros y configuración.
