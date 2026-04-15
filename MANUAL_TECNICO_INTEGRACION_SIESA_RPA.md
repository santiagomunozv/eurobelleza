# Manual Técnico - Integración Shopify, Laravel, S3, RPA y Siesa

## 1. Objetivo

Este documento describe la solución completa de integración entre Shopify y Siesa 8.5 implementada para Eurobelleza. El flujo cubre:

1. Recepción de pedidos desde Shopify en `eurobelleza`
2. Generación de archivos `.PE0` para Siesa
3. Publicación de archivos en Amazon S3
4. Consumo de esos archivos por el bot Windows `eurobelleza_rpa`
5. Carga de pedidos en Siesa 8.5 mediante automatización de escritorio
6. Devolución de resultados y errores a S3
7. Cierre de estados en Laravel

Este manual está orientado a soporte técnico, despliegue, mantenimiento y auditoría del proceso.

---

## 2. Componentes del sistema

### 2.1 Backend Laravel: `eurobelleza`

Responsabilidades principales:

- recibir pedidos desde Shopify
- validar configuración funcional
- generar archivos `.PE0` y `.txt`
- subir el `.PE0` a S3
- mantener trazabilidad por pedido
- interpretar resultados del RPA
- actualizar estados del pedido

Tecnología base:

- Laravel 11
- PHP 8.2+
- cola `database`
- ejecución en contenedor `eurobelleza-back`

### 2.2 Bot Windows: `eurobelleza_rpa`

Responsabilidades principales:

- leer archivos nuevos desde `pedidos/` en S3
- descargar los `.PE0`
- abrir Siesa 8.5
- iniciar sesión y navegar por teclado
- ejecutar la importación
- detectar archivos `.P99`
- subir errores a `errores/`
- generar un JSON de corrida y subirlo a `resultados/`

Tecnología base:

- Python
- `boto3`
- `pyautogui`
- `pygetwindow`
- Programador de tareas de Windows

### 2.3 Amazon S3

Bucket principal:

- `eurobelleza-siesa`

Prefijos utilizados:

- `pedidos/`
- `errores/`
- `resultados/`

### 2.4 Siesa 8.5

Sistema destino que:

- importa pedidos desde archivos `.PE0`
- genera archivos `.P99` cuando encuentra errores de validación o duplicidad

---

## 3. Flujo general de extremo a extremo

### 3.1 Flujo funcional resumido

1. Shopify envía el pedido a Laravel.
2. Laravel guarda el pedido en base de datos.
3. Si el pedido está pagado y la configuración es válida, Laravel genera el archivo plano.
4. Laravel sube el `.PE0` a `s3://eurobelleza-siesa/pedidos/`.
5. En los horarios definidos, Windows ejecuta el bot RPA.
6. El bot descarga los `.PE0` nuevos.
7. El bot abre Siesa e importa cada archivo.
8. Si Siesa genera `.P99`, el bot lo sube a `errores/`.
9. El bot sube un JSON de corrida a `resultados/`.
10. Laravel procesa `resultados/` y `errores/`.
11. Laravel deja cada pedido en `completed` o `siesa_error`.

### 3.2 Flujo por carpetas S3

#### `pedidos/`

- Laravel publica aquí los `.PE0`
- el bot Windows los descarga
- actualmente **no se eliminan automáticamente de S3**
- el bot evita reprocesarlos usando `state.json`

#### `errores/`

- Windows sube aquí los `.P99`
- Laravel los consume con `php artisan siesa:check-errors`
- luego Laravel los elimina de S3

#### `resultados/`

- Windows sube aquí un JSON por corrida
- Laravel lo consume con `php artisan siesa:check-errors`
- luego Laravel lo elimina de S3

---

## 4. Estados del pedido

Estados implementados en Laravel:

- `pending`
- `processing`
- `rpa_processing`
- `sent_to_siesa`
- `completed`
- `failed`
- `siesa_error`

### Significado de cada estado

#### `pending`

Pedido recibido pero aún no listo para envío a Siesa. Puede deberse a:

- pedido no pagado
- configuración faltante
- pedido reiniciado para reproceso

#### `processing`

Laravel está procesando internamente el pedido y generando el archivo.

#### `sent_to_siesa`

El `.PE0` ya fue generado y subido a S3, pero todavía no ha sido resuelto por el RPA.

#### `rpa_processing`

Laravel ya recibió evidencia de que el archivo fue tomado en una corrida RPA.

#### `completed`

El pedido fue reportado por el RPA como procesado sin error.

#### `failed`

Falló la generación o envío del archivo desde Laravel antes de pasar al RPA.

#### `siesa_error`

Siesa devolvió error para ese pedido.

---

## 5. Backend Laravel

### 5.1 Entrada principal desde Shopify

Archivo principal:

- `app/Http/Controllers/API/ShopifyWebhookController.php`

Ruta API:

- `POST /api/webhooks/shopify/orders/create`

El webhook:

- valida firma HMAC
- crea el pedido
- lo deja en `pending`
- valida configuración si `financial_status = paid`
- despacha el job si todo está correcto

### 5.2 Job de procesamiento

Archivos principales:

- `app/Jobs/ProcessShopifyOrder.php`
- `app/Services/ShopifyOrderProcessor.php`

Responsabilidades:

- mover el pedido a `processing`
- generar el archivo plano
- guardar copia local
- subir `.PE0` a S3 en `pedidos/`
- dejar el pedido en `sent_to_siesa`

### 5.3 Generación del archivo plano

Archivo principal:

- `app/Services/Siesa/SiesaFlatFileGenerator.php`

Responsabilidades:

- construir el contenido del archivo Siesa
- leer configuración general
- resolver método de pago
- resolver bodega
- agregar líneas por producto y envío

### 5.4 Validación previa

Archivo principal:

- `app/Services/OrderConfigurationValidator.php`

Validaciones obligatorias:

- configuración general Siesa
- mapping de método de pago
- mapping de bodega/ubicación

### 5.5 Consumo del resultado RPA

Archivos principales:

- `app/Console/Commands/CheckSiesaErrors.php`
- `app/Services/Siesa/SiesaRunResultProcessor.php`

Responsabilidades:

- leer `resultados/`
- interpretar archivos intentados, exitosos y con error
- actualizar estados de pedidos
- consumir `.P99` legados si existen
- eliminar de S3 los JSON y `.P99` ya procesados

---

## 6. Programación automática en Laravel

Archivo:

- `app/Console/Kernel.php`

### Tareas programadas

#### `shopify:sync-orders`

- horario: `02:00 AM`
- función: recuperar pedidos faltantes desde Shopify

#### `orders:refresh --non-completed --days=30 --reprocess`

- horario: `03:00 AM`
- función: refrescar pedidos no completados y reprocesarlos si ahora tienen configuración válida

#### `siesa:check-errors`

- horario: cada `30 minutos`
- función: cerrar resultados que subió el RPA y consumir errores

### Requisito operativo

Para que esto funcione en producción se necesitan **dos capas**:

1. **Supervisor** para mantener vivos los workers de cola
2. **cron** del sistema para ejecutar `php artisan schedule:run` cada minuto

Ejemplo típico de cron:

```bash
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Cola y workers

La generación y subida de archivos `.PE0` depende de la cola de Laravel. Si la cola no corre:

- el pedido puede quedarse en `pending`
- no se generará el archivo para Siesa

Se recomienda operar los workers mediante Supervisor.

Validación típica:

```bash
sudo supervisorctl status
```

---

## 8. Configuración S3 en Laravel

Archivo:

- `config/filesystems.php`

Discos definidos:

- `siesa_pedidos`
- `siesa_errores`
- `siesa_resultados`

Variables esperadas:

- `SIESA_S3_KEY`
- `SIESA_S3_SECRET`
- `SIESA_S3_REGION`
- `SIESA_S3_BUCKET`

Permisos requeridos para Laravel:

- `PutObject` sobre `pedidos/*`
- `GetObject`, `DeleteObject` sobre `errores/*`
- `GetObject`, `DeleteObject` sobre `resultados/*`
- `ListBucket` sobre el bucket

---

## 9. Bot Windows `eurobelleza_rpa`

### 9.1 Archivo principal

- `bot.py`

### 9.2 Configuración

Archivo:

- `config.py`

Variables más importantes:

- `SIESA_SHORTCUT_PATH`
- `SIESA_WORKING_DIR`
- `SIESA_PEDIDOS_PATH`
- `SIESA_P99_PATH`
- `SIESA_WINDOW_TITLE`
- `SIESA_USER`
- `SIESA_PASSWORD`
- `BOT_WORKDIR`
- `DOWNLOADS_DIR`
- `ARCHIVE_DIR`
- `LOGS_DIR`
- `STATE_FILE`
- `LOCK_FILE`
- `S3_PEDIDOS_PREFIX`
- `S3_ERRORES_PREFIX`
- `S3_RESULTADOS_PREFIX`
- `DELETE_SOURCE_OBJECTS`

### 9.3 Función del `BOT_WORKDIR`

Esta carpeta local contiene:

- `downloads`: staging temporal de `.PE0`
- `archive`: histórico local de archivos y resultados
- `logs`: logs por corrida
- `state.json`: memoria local de objetos S3 ya procesados
- `run.lock`: lock para impedir doble instancia

### 9.4 Importancia de `state.json`

Actualmente `pedidos/` no se borra de S3. Por eso el bot usa `state.json` para recordar qué objetos ya fueron procesados.

Riesgo operativo:

- si se borra `state.json`, el bot volverá a considerar como nuevos los archivos que sigan en `pedidos/`

Recomendación:

- no borrar `state.json`
- incluirlo como parte del entorno operativo del bot

### 9.5 Lock de ejecución

El bot crea `run.lock` al iniciar.

Comportamiento:

- si hay otra instancia viva, aborta
- si encuentra un lock huérfano, lo limpia automáticamente

### 9.6 Flujo interno del bot

1. valida rutas locales y de Siesa
2. adquiere lock
3. consulta `pedidos/` en S3
4. filtra objetos ya procesados según `state.json`
5. descarga nuevos `.PE0` a `downloads`
6. abre Siesa
7. inicia sesión
8. navega por teclado hasta el menú de importación
9. procesa archivo por archivo
10. detecta `.P99` nuevos
11. sube errores a `errores/`
12. registra resultado de corrida
13. sube JSON a `resultados/`
14. cierra Siesa

### 9.7 Salida de una corrida

Por cada corrida el bot sube un JSON como este:

```json
{
  "run_id": "20260406_190000",
  "started_at": "2026-04-06T19:00:01-05:00",
  "finished_at": "2026-04-06T19:12:33-05:00",
  "machine_name": "PC-SIESA-01",
  "files_detected": ["00003663.PE0"],
  "files_attempted": ["00003663.PE0"],
  "files_without_error": ["00003663.PE0"],
  "files_with_error": [],
  "fatal_error": null
}
```

---

## 10. Configuración del equipo Windows

### 10.1 Requisitos mínimos

- acceso a la unidad `U:`
- acceso al acceso directo de Siesa
- Python instalado
- sesión del usuario disponible
- conectividad a S3

### 10.2 Tarea programada

La ejecución se realiza por Task Scheduler de Windows.

Horarios definidos:

- `6:30 AM`
- `1:00 PM`
- `7:00 PM`

Configuración recomendada:

- ejecutar solo cuando el usuario haya iniciado sesión
- ejecutar con privilegios altos
- no iniciar una nueva instancia si la anterior sigue corriendo

### 10.3 Recomendación importante

El Programador de tareas debe abrirse como administrador para crear o editar correctamente la tarea.

---

## 11. Políticas S3 requeridas

### 11.1 Laravel

Debe poder:

- escribir `pedidos/`
- leer y borrar `errores/`
- leer y borrar `resultados/`
- listar el bucket

### 11.2 Windows

Debe poder:

- leer `pedidos/`
- escribir `errores/`
- escribir `resultados/`

Actualmente:

- `DELETE_SOURCE_OBJECTS = False`
- por lo tanto Windows **no necesita** borrar `pedidos/`

---

## 12. Operación normal diaria

### 12.1 Durante el día

- Shopify sigue enviando pedidos
- Laravel genera y sube `.PE0`
- los pedidos quedan en `sent_to_siesa`

### 12.2 En cada corrida Windows

- se toman los pedidos nuevos
- se cargan a Siesa
- se generan archivos de resultado

### 12.3 Cada 30 minutos en Laravel

`siesa:check-errors`:

- consume `resultados/`
- consume `errores/`
- marca pedidos como `completed` o `siesa_error`

---

## 13. Comandos útiles

### 13.1 Revisar resultados RPA manualmente

```bash
php artisan siesa:check-errors
```

### 13.2 Reprocesar pedidos pendientes en lotes

```bash
php artisan orders:reprocess --status=pending --limit=50 --validate
```

### 13.3 Refrescar pedidos concretos desde Shopify y reencolar

```bash
php artisan orders:refresh --ids=123 --ids=124 --reprocess
```

### 13.4 Revisar Supervisor

```bash
sudo supervisorctl status
```

---

## 14. Recuperación y troubleshooting

### 14.1 El bot no encuentra la ventana

Revisar:

- `SIESA_WINDOW_TITLE`
- sesión correcta del usuario
- que Siesa realmente se abrió

### 14.2 El bot vuelve a tomar archivos viejos

Revisar:

- que `state.json` exista
- que no haya sido eliminado o reemplazado

### 14.3 Laravel no cambia estados

Revisar:

- que `siesa:check-errors` esté corriendo
- que `resultados/` y `errores/` tengan permisos correctos
- que el cron de `schedule:run` exista

### 14.4 Los `.PE0` no se generan

Revisar:

- estado de Supervisor
- cola de Laravel
- logs del pedido
- configuración general, bodegas y métodos de pago

### 14.5 La tarea de Windows no se deja guardar

Revisar:

- abrir Task Scheduler como administrador
- usar la cuenta correcta
- configurar la tarea para ejecutar solo con la sesión iniciada

---

## 15. Recomendaciones de mantenimiento

- no borrar `state.json`
- no modificar rutas o secuencias de teclado sin pruebas controladas
- mantener respaldos de `config.py`
- validar S3 después de cualquier cambio de credenciales
- probar manualmente el bot después de cualquier cambio en Siesa o en el escritorio del equipo
- documentar cualquier cambio en los horarios de corrida

---

## 16. Archivos clave del proyecto

### En `eurobelleza`

- `app/Http/Controllers/API/ShopifyWebhookController.php`
- `app/Jobs/ProcessShopifyOrder.php`
- `app/Services/ShopifyOrderProcessor.php`
- `app/Services/Siesa/SiesaFlatFileGenerator.php`
- `app/Services/OrderConfigurationValidator.php`
- `app/Console/Kernel.php`
- `app/Console/Commands/CheckSiesaErrors.php`
- `app/Services/Siesa/SiesaRunResultProcessor.php`
- `config/filesystems.php`

### En `eurobelleza_rpa`

- `config.py`
- `bot.py`
- `requirements.txt`
- `Bot.spec`

---

## 17. Estado actual del diseño

La solución ya no marca pedidos como exitosos por “silencio” o timeout. El cierre de estados depende de evidencia real subida por el RPA en `resultados/`, lo cual vuelve el proceso más confiable y trazable.
