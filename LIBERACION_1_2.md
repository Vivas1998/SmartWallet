# Liberación local de SmartWallet 1.2

Revisión: 24 de septiembre de 2026.

## Identidad de la entrega

- Producto: `SmartWallet`.
- Versión publicada: `1.2.0`.
- Destino: instalación local con Docker Compose.
- Actualización compatible: desde `1.1.1`, conservando los datos existentes.

El archivo `VERSION` continúa siendo la referencia canónica. La configuración
de Laravel y los paquetes frontend muestran el mismo número de versión.

## Contenido publicado

- temas automático, claro y oscuro guardados por usuario;
- presupuesto disponible del mes actual en la tarjeta de cada proyecto;
- límites opcionales y progreso independiente por subcategoría;
- campos personalizados de texto, número, fecha y sí/no por proyecto;
- valores adicionales en movimientos, planificaciones y recurrencias;
- filtros, exportación CSV y auditoría de los campos personalizados;
- DDL consolidada capaz de recrear directamente el esquema completo de 1.2.0.

## Resultado de la puerta automática

`scripts/release-check.ps1` finalizó correctamente el 24 de septiembre de 2026:

- construyó e inició el entorno Docker Compose;
- confirmó las 19 migraciones en la base persistente;
- validó 148 archivos con Laravel Pint;
- compiló los recursos de producción con Vite;
- superó 124 pruebas y 985 aserciones en `smartwallet_test`;
- confirmó la respuesta HTTP de la pantalla de acceso;
- dejó aplicación, MySQL, Mailpit y Vite saludables, con el programador activo;
- detuvo la base temporal al terminar.

Composer Audit no encontró avisos de vulnerabilidades conocidas y npm Audit de
producción informó cero vulnerabilidades.

## Aceptación manual aislada

La instancia temporal `http://localhost:8011` utilizó `APP_ENV=testing` y
`smartwallet_test`. El recorrido confirmó temas, tarjeta de proyecto,
presupuesto jerárquico, valores personalizados, filtros, exportación y
aislamiento. La interfaz se revisó en 1440, 720 y 360 px, además del salto al
contenido, el foco visible y muestras de contraste en ambos temas. La evidencia
completa está en:

- [Matriz de aceptación de SmartWallet 1.2](ACEPTACION_1_2.md)
- [Accesibilidad y adaptación de SmartWallet 1.2](PRUEBAS_ACCESIBILIDAD_1_2.md)

## Actualización desde 1.1.1

Desde la carpeta del proyecto:

```powershell
.\scripts\start.ps1
```

El arranque aplica dos migraciones nuevas. Los usuarios existentes comienzan en
modo `Automático`; los presupuestos anteriores continúan sin límites secundarios
hasta que un propietario los configure; y cada proyecto recibe los cinco campos
de ejemplo vacíos. No se modifican movimientos ni importes existentes.

Para repetir toda la comprobación de entrega:

```powershell
.\scripts\release-check.ps1
```

## Lista de cierre

- [x] Alcance y decisiones de 1.2 documentados.
- [x] Migraciones compatibles aplicadas sobre la base persistente.
- [x] DDL completa comparada con el esquema creado por migraciones.
- [x] Suite, formato, build y salud HTTP superados.
- [x] Auditorías de dependencias sin vulnerabilidades conocidas.
- [x] Aceptación funcional sobre una base temporal aislada.
- [x] Revisión responsive, semántica, de teclado y contraste completada.
- [x] Versión, changelog y guías de instalación actualizados.

## Estado de publicación

La versión local `1.2.0` queda publicada el 24 de septiembre de 2026, sin
defectos críticos conocidos. Los servicios habituales permanecen disponibles en
<http://localhost:8010> y <http://localhost:8026>. No se ha creado todavía un
commit ni se ha enviado esta entrega al repositorio remoto.
