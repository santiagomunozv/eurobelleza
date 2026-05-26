# Manual de Usuario - Operación diaria de la integración con Siesa

## 1. ¿Qué hace esta solución?

Este sistema permite que los pedidos de Shopify lleguen a Siesa 8.5 de forma automática.

El proceso tiene dos partes:

1. El sistema web `eurobelleza` prepara los pedidos y los deja listos.
2. Un bot en Windows abre Siesa y carga esos pedidos en los horarios programados.

El objetivo es evitar que el usuario tenga que descargar archivos manualmente, copiarlos a carpetas o navegar en Siesa cada vez que entra un pedido.

---

## 2. Qué ocurre automáticamente

### Paso 1. El pedido entra desde Shopify

Cuando un pedido llega desde Shopify y cumple las condiciones necesarias, el sistema lo prepara para Siesa.

### Paso 2. El pedido queda esperando la siguiente corrida

El sistema deja el pedido listo para que el bot de Windows lo cargue en Siesa.

### Paso 3. El bot de Windows se ejecuta solo

En el equipo configurado, el bot corre automáticamente en estos horarios:

- `6:30 AM`
- `1:00 PM`
- `7:00 PM`

### Paso 4. El bot abre Siesa

El bot:

- abre Siesa
- inicia sesión
- entra al menú de importación
- procesa los archivos pendientes

### Paso 5. El sistema registra el resultado

Si Siesa acepta el pedido y consume el archivo, el pedido queda como:

- `Completado`

Si Siesa acepta el pedido pero genera advertencias, también queda como:

- `Completado`

En ese caso el mensaje de advertencia queda visible en el pedido.

Si Siesa rechaza el pedido, queda como:

- `Error Siesa`

Si el bot no puede confirmar que Siesa consumió el archivo, el pedido puede quedar como:

- `Procesando en RPA`

Ese caso requiere revisión.

---

## 3. Qué debe revisar el usuario

En operación normal, el usuario solo debería revisar:

- que el equipo Windows esté encendido
- que tenga sesión iniciada con el usuario correcto
- que el Programador de tareas esté habilitado
- que Siesa no esté bloqueado por otra ventana inesperada

En el sistema web, el usuario puede revisar:

- pedidos pendientes
- pedidos completados
- pedidos con error
- logs del pedido

---

## 4. Estados del pedido en palabras simples

### Pendiente

El pedido existe, pero todavía no ha terminado su proceso.

### Procesando

El sistema está preparando internamente el archivo del pedido.

### Enviado a Siesa

El archivo ya quedó listo y está esperando la siguiente corrida del bot.

### Procesando en RPA

El bot ya tomó ese pedido en una corrida.

### Completado

Siesa consumió el archivo del pedido.

Puede tener advertencias informativas. Si las tiene, aparecerán en el mensaje del pedido.

### Fallido

El sistema no pudo preparar el archivo correctamente antes de enviarlo.

### Error Siesa

Siesa rechazó el pedido y devolvió un error.

### Vencido

El pedido no se enviará a Siesa porque Shopify sigue indicando que el pago está pendiente, vencido o no confirmado después del periodo de revisión.

---

## 5. Qué hacer cada día

### Revisión recomendada por la mañana

Revisar en el panel:

- cuántos pedidos quedaron completados
- si hay pedidos con `Error Siesa`
- si hay pedidos pendientes acumulados

### Revisión recomendada al final del día

Revisar:

- que la corrida de la tarde haya procesado pedidos
- que no haya acumulación anormal en `Enviado a Siesa`
- que no haya acumulación anormal en `Procesando en RPA`

---

## 6. Qué hacer si un pedido sale con error

Si un pedido queda en `Error Siesa`:

1. entrar al detalle del pedido en el panel
2. revisar el mensaje de error
3. corregir lo que corresponda:
- configuración
- método de pago
- bodega
- datos del pedido
- duplicidad en Siesa

4. volver a reprocesarlo cuando ya esté corregido

Si un pedido queda `Completado` con mensaje de advertencia:

1. revisar el mensaje del pedido
2. validar si la advertencia requiere acción interna
3. no reprocesar automáticamente si el pedido ya existe correctamente en Siesa

Ejemplo común:

```text
ITEM LIQUIDADO CON OTRA LISTA PRECIO
```

Ese tipo de advertencia no necesariamente bloquea el pedido. El criterio principal es que Siesa haya consumido el archivo.

---

## 7. Qué hacer si un pedido queda en Procesando en RPA

`Procesando en RPA` puede significar que el bot tomó el archivo, pero no pudo confirmar que Siesa lo importó correctamente.

Casos comunes:

- el archivo siguió en la carpeta `trm`
- Siesa no generó un `.P99`
- Siesa generó un `.P99` sin detalle reconocible
- Siesa generó solo advertencias, pero no consumió el archivo
- la pantalla de Siesa quedó en un punto inesperado

Qué hacer:

1. revisar el log del pedido en el sistema web
2. revisar si hay mensaje de `rpa_run_unresolved_result`
3. revisar el log de la corrida en el equipo Windows
4. confirmar manualmente en Siesa si el pedido existe
5. si el pedido no existe y la causa ya fue corregida, reprocesarlo manualmente desde la pantalla

No asumir que `Procesando en RPA` significa completado.

---

## 8. Qué hacer si parece que no se procesó nada

Si ves pedidos en `Enviado a Siesa` y no avanzan, revisar:

1. que el PC Windows esté encendido
2. que la sesión del usuario correcto esté abierta
3. que la tarea programada siga habilitada
4. que el bot no haya quedado bloqueado por una ventana inesperada
5. que Siesa abra correctamente en ese equipo

Si hace falta, se puede ejecutar el bot manualmente.

---

## 9. Cómo ejecutar el bot manualmente

Solo para soporte o prueba controlada.

En el equipo Windows:

1. abrir PowerShell
2. ir a la carpeta del bot
3. ejecutar:

```powershell
python bot.py
```

Eso lanzará una corrida completa.

---

## 10. Qué NO debe hacer el usuario

- no borrar manualmente `state.json`
- no mover ni renombrar carpetas del bot
- no cambiar rutas de Siesa sin avisar al equipo técnico
- no modificar la tarea programada sin revisar la configuración
- no cerrar la sesión del usuario si se espera que el bot corra automáticamente
- no borrar manualmente archivos de S3 si no se está haciendo una depuración técnica controlada

---

## 11. Dónde mirar si algo falla

### En el sistema web

Revisar:

- listado de pedidos
- estado del pedido
- logs del pedido

### En el equipo Windows

Revisar:

- carpeta de logs del bot
- si Siesa abrió correctamente
- si la tarea del Programador de tareas se ejecutó

Ubicación habitual de trabajo del bot:

- `D:\Escritorioo\eurobelleza_rpa`

Carpetas importantes:

- `downloads`
- `archive`
- `logs`

### En Siesa / unidad U:

Carpetas importantes:

- `trm`: carpeta donde Siesa recibe los `.PE0`
- `prt`: carpeta donde Siesa genera o actualiza los `.P99`

Regla operativa importante:

- si Siesa consume correctamente un `.PE0`, el archivo desaparece de `trm`
- si Siesa no lo consume, el archivo puede quedarse en `trm` y puede generar o modificar un `.P99`

---

## 12. Cuándo contactar soporte técnico

Contactar soporte cuando ocurra cualquiera de estos casos:

- el bot ya no abre Siesa
- la tarea programada deja de correr
- aparecen muchos pedidos en `Error Siesa`
- los pedidos se quedan mucho tiempo en `Enviado a Siesa`
- los pedidos se quedan mucho tiempo en `Procesando en RPA`
- el PC cambia de usuario, contraseña, escritorio o ruta de instalación
- cambian rutas del disco `U:`
- hay muchos pedidos `Completado` con advertencias no esperadas

---

## 13. Resumen operativo simple

1. Shopify envía pedidos
2. El sistema web los deja listos
3. El bot corre a horas definidas
4. El bot carga pedidos en Siesa
5. El bot valida si Siesa consumió el archivo desde `trm`
6. El sistema web marca cada pedido como:
- `Completado`
- o `Error Siesa`
- o `Procesando en RPA` si no pudo confirmar el resultado

En condiciones normales, el usuario revisa resultados, atiende errores y valida manualmente los casos que queden en `Procesando en RPA`.
