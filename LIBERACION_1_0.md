# Liberación local de SmartWallet 1.0

Revisión: 19 de septiembre de 2026.

## Identidad de la entrega

- Producto: `SmartWallet`, nombre definitivo.
- Versión publicada: `1.0.0`.
- Destino: instalación local con Docker Compose.

El archivo `VERSION` es la referencia canónica. `APP_VERSION` permite mostrar el
mismo identificador desde Laravel y cambiarlo al preparar una nueva entrega.

## Puertas superadas

- alcance funcional y permisos aprobados;
- matriz de aceptación completa, sin defectos críticos conocidos;
- 95 pruebas y 664 aserciones superadas en `smartwallet_test`;
- formato PHP, compilación de producción y auditorías de dependencias correctas;
- pruebas de rendimiento con 100.000 movimientos;
- revisión de seguridad, aislamiento, accesibilidad y diseño adaptable;
- instalación, operación y limitaciones locales documentadas;
- changelog e identificador semántico creados;
- comprobación automatizada de liberación superada el 17 de septiembre de 2026.

## Resultado del ensayo automático

`scripts/release-check.ps1` completó correctamente:

- construcción y arranque limpio de la aplicación;
- 16 migraciones aplicadas en la base persistente;
- 121 archivos aceptados por Laravel Pint;
- compilación Vite de producción;
- 95 pruebas y 664 aserciones sobre `smartwallet_test`;
- respuesta HTTP correcta de la pantalla de acceso;
- aplicación, MySQL, Mailpit y Vite saludables, con el programador activo.

Vite se restauró después de la revisión visual del build de producción y todos
los servicios quedaron operativos.

## Cierre de 1.0.0

- [x] Iniciar Docker Desktop y ejecutar `.\scripts\release-check.ps1` sin
  errores.
- [x] Abrir las pantallas principales en el navegador de uso habitual, aplicar
  zoom nativo al 200 % y confirmar que no se pierde información ni funcionalidad.
- [x] Cambiar `VERSION`, `APP_VERSION` y el changelog a `1.0.0`.
- [x] Documentar la instalación nueva y el traslado de datos a otro equipo.

La aplicación se publica con `SmartWallet` como nombre definitivo.

## Recorrido manual con zoom al 200 %

Con la aplicación iniciada en <http://localhost:8010>:

1. comprobar acceso, registro y recuperación de contraseña;
2. entrar en `Mis proyectos` y en el panel de un proyecto con datos;
3. revisar movimientos, presupuestos, informe anual y comparación de periodos;
4. revisar cuentas, recurrencias, miembros, configuración y perfil;
5. aumentar el zoom nativo del navegador al 200 % en cada familia de pantalla;
6. confirmar que no aparece desplazamiento horizontal global, que los controles
   siguen visibles y que teclado, foco y menús continúan funcionando.

La navegación interna de pestañas y las tablas pueden usar desplazamiento local
cuando sea necesario; eso no se considera desbordamiento global.

## Estado de publicación

La versión local `1.0.0` queda publicada el 19 de septiembre de 2026, sin defectos
críticos conocidos. El código y la configuración de instalación se mantienen en
<https://github.com/Vivas1998/SmartWallet> y también pueden distribuirse mediante
un archivo ZIP limpio.
El despliegue en homelab, las copias automáticas y la restauración remota
pertenecen a un hito posterior.
