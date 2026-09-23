# Liberación local de SmartWallet 1.1

Revisión: 23 de septiembre de 2026.

## Identidad de la entrega

- Producto: `SmartWallet`.
- Versión publicada: `1.1.0`.
- Destino: instalación local con Docker Compose.
- Actualización compatible: desde `1.0.0`, conservando los datos existentes.

El archivo `VERSION` continúa siendo la referencia canónica. La aplicación y
los paquetes frontend muestran el mismo número de versión.

## Contenido publicado

- calendario financiero selectivo por proyecto;
- cuadrícula mensual en escritorio y agenda cronológica en móvil;
- planificaciones puntuales con vencimiento, cancelación y realización;
- proyección no contable de las recurrencias futuras;
- opción manual `Mostrar en el calendario`;
- estados, puntualidad y filtros combinables;
- resumen mensual real frente a estimado.

## Resultado de la puerta automática

`scripts/release-check.ps1` finalizó correctamente el 23 de septiembre de 2026:

- construyó e inició el entorno Docker Compose;
- aplicó las 17 migraciones en la base persistente;
- validó 136 archivos con Laravel Pint;
- compiló los recursos de producción con Vite;
- superó 111 pruebas y 860 aserciones en `smartwallet_test`;
- confirmó la respuesta HTTP de la pantalla de acceso;
- dejó aplicación, MySQL, Mailpit y Vite saludables, con el programador activo;
- detuvo la base temporal al terminar.

Composer Audit no encontró avisos de vulnerabilidades conocidas y npm Audit de
producción informó cero vulnerabilidades.

## Aceptación manual aislada

La instancia temporal `http://localhost:8011` utilizó `APP_ENV=testing` y
`smartwallet_test`. El recorrido confirmó alta y consulta de una planificación,
visibilidad manual de un gasto, resumen real frente a estimado y filtros. La
interfaz se revisó en 1440, 720 y 360 px, además del salto al contenido y el foco
visible. La evidencia completa está en:

- [Matriz de aceptación de SmartWallet 1.1](ACEPTACION_1_1.md)
- [Accesibilidad y adaptación del calendario](PRUEBAS_ACCESIBILIDAD_1_1.md)

## Actualización desde 1.0

Desde la carpeta del proyecto:

```powershell
.\scripts\start.ps1
```

El arranque aplica la migración pendiente antes de servir la aplicación. Los
movimientos existentes conservan `Mostrar en el calendario` desactivado; no se
añaden eventos de forma masiva. Las recurrencias ya existentes se proyectan en
el calendario sin crear movimientos futuros anticipadamente.

Para repetir toda la comprobación de entrega:

```powershell
.\scripts\release-check.ps1
```

## Lista de cierre

- [x] Alcance y decisiones de 1.1 documentados.
- [x] Migración compatible aplicada sobre la base persistente.
- [x] Suite, formato, build y salud HTTP superados.
- [x] Auditorías de dependencias sin vulnerabilidades conocidas.
- [x] Aceptación funcional sobre una base temporal aislada.
- [x] Revisión responsive, semántica y de teclado completada.
- [x] Versión, changelog y guías de instalación actualizados.

## Estado de publicación

La versión local `1.1.0` queda publicada el 23 de septiembre de 2026, sin
defectos críticos conocidos. Los servicios habituales permanecen disponibles en
<http://localhost:8010> y <http://localhost:8026>. No se ha creado todavía un
commit ni se ha enviado esta entrega al repositorio remoto.
