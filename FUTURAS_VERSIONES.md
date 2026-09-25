# Mejoras y futuras versiones

**Creado:** 13 de septiembre de 2026
**Estado:** lista de evolución; no implica fecha de entrega

## Versión 1.1 publicada

### Calendario financiero selectivo

- Mostrar automáticamente recurrencias y planificaciones con vencimiento.
- Mostrar otros movimientos solo cuando tengan activada la opción
  `Mostrar en el calendario`.
- Mantener los eventos realizados en su fecha prevista y mostrar por separado la
  fecha efectiva y si fueron anticipados, puntuales o tardíos.
- Separar importes reales y previstos sin contabilizar anticipadamente las
  planificaciones.
- Utilizar cuadrícula mensual en escritorio y agenda cronológica en móvil.
- Conservar avisos exclusivamente visuales; las notificaciones externas siguen
  aplazadas.

El alcance y los criterios completos están en
[Requisitos de SmartWallet 1.1](REQUISITOS_1_1.md).

Esta mejora se publicó como `1.1.0` el 23 de septiembre de 2026. Se mantiene en
este documento como historial; ya no forma parte de las funciones pendientes.

## Versión 1.2.0 publicada — Interfaz y presupuestos

- Tema oscuro completo.
- Mostrar el presupuesto restante del mes actual de cada proyecto en
  `Mis proyectos` mediante la tarjeta equilibrada aprobada, sin sumar ni mezclar
  los importes.
- Presupuestos y límites específicos para subcategorías.
- Campos personalizados configurables por proyecto.

Estos cuatro elementos se publicaron como `1.2.0` el 24 de septiembre de 2026
tras superar la validación funcional, visual y de accesibilidad definida en
[REQUISITOS_1_2.md](REQUISITOS_1_2.md). Se mantienen aquí como historial y ya no
forman parte de las funciones pendientes.

## Mejoras candidatas sin versión asignada

- Avisos de facturas y vencimientos.
- Importación CSV mediante plantilla propia y detección de duplicados.
- Categorización automática siempre modificable.
- División de gastos, cálculo de deudas y liquidaciones entre miembros.
- Módulo de inversiones con cuentas de inversión, aportaciones, valoración de
  cartera, rendimientos y separación entre capital aportado y valor actual.
- Conexión y sincronización bancaria automática.
- Fotografías de recibos y reconocimiento OCR.
- Varias monedas y conversión de divisas.
- Conciliación bancaria.
- Predicciones y proyecciones financieras.
- Informes PDF y Excel.
- Notificaciones por correo, navegador o móvil.
- Aplicación instalable y funcionamiento sin conexión.
- API, webhooks e integraciones externas.
- Rol de solo lectura y permisos personalizados.
- Aprobación de gastos.
- Funciones fiscales y gestión de suscripciones.

## Versión 2.0.0 planificada — Seguridad y despliegue

- Despliegue de SmartWallet fuera del equipo local, inicialmente orientado al
  homelab.
- Incorporación obligatoria de HTTPS, gestión de secretos, copias cifradas,
  restauración probada, monitorización y endurecimiento del servidor y del acceso
  remoto.
- Incorporación de doble factor antes de exponer datos financieros a través de
  Internet.
- La opción preferida será un código TOTP generado por una aplicación
  autenticadora o una alternativa resistente al phishing. Como opción secundaria
  podrá integrarse un proveedor externo para enviar códigos SMS, con teléfono
  verificado, límites de reintentos, códigos de un solo uso, caducidad breve y un
  método alternativo de recuperación.

`2.0.0` no forma parte del trabajo de `1.2.0`; se definirá después de estabilizar
la siguiente versión local.

## Normas de esta lista

- Una mejora no entra en una versión hasta definir requisitos y criterios de
  aceptación.
- La prioridad se decidirá con la experiencia de uso de la versión estable.
- Las mejoras no deben romper el aislamiento entre proyectos ni la exactitud del
  historial financiero.

## Historial

| Fecha | Cambio | Motivo |
|---|---|---|
| 13-09-2026 | Creación de la lista, reserva del calendario de pagos para 1.1 y registro del modo oscuro y demás funciones aplazadas. | Conservar las ideas sin ampliar silenciosamente el alcance de 1.0. |
| 13-09-2026 | Añadida la autenticación multifactor futura con TOTP preferido y SMS opcional mediante proveedor externo. | Preparar la verificación de inicios de sesión sin añadir coste ni dependencias a la versión local. |
| 22-09-2026 | Definido el calendario selectivo de 1.1, sus fuentes, estados, permisos y relación entre vencimiento y fecha efectiva. | Evitar saturar el calendario y separar previsión de contabilidad real. |
| 23-09-2026 | Publicada la versión 1.1.0 con calendario selectivo, planificaciones puntuales y resumen real frente a estimado. | Cerrar el alcance aprobado y comenzar su validación mediante uso real. |
| 23-09-2026 | Reservada la versión 1.2.0 para interfaz y presupuestos, y la versión 2.0.0 para despliegue y seguridad. | Concentrar la siguiente iteración en mejoras locales y separar el salto operativo del homelab. |
| 24-09-2026 | Publicada la versión 1.2.0 con tema oscuro, tarjetas presupuestarias, límites por subcategoría y campos personalizados. | Cerrar el alcance local validado antes de definir el despliegue 2.0.0. |
