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

Si Siesa acepta el pedido, el pedido queda como:

- `Completado`

Si Siesa rechaza el pedido, queda como:

- `Error Siesa`

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

Siesa lo procesó sin error.

### Fallido

El sistema no pudo preparar el archivo correctamente antes de enviarlo.

### Error Siesa

Siesa rechazó el pedido y devolvió un error.

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

---

## 7. Qué hacer si parece que no se procesó nada

Si ves pedidos en `Enviado a Siesa` y no avanzan, revisar:

1. que el PC Windows esté encendido
2. que la sesión del usuario correcto esté abierta
3. que la tarea programada siga habilitada
4. que el bot no haya quedado bloqueado por una ventana inesperada
5. que Siesa abra correctamente en ese equipo

Si hace falta, se puede ejecutar el bot manualmente.

---

## 8. Cómo ejecutar el bot manualmente

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

## 9. Qué NO debe hacer el usuario

- no borrar manualmente `state.json`
- no mover ni renombrar carpetas del bot
- no cambiar rutas de Siesa sin avisar al equipo técnico
- no modificar la tarea programada sin revisar la configuración
- no cerrar la sesión del usuario si se espera que el bot corra automáticamente

---

## 10. Dónde mirar si algo falla

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

---

## 11. Cuándo contactar soporte técnico

Contactar soporte cuando ocurra cualquiera de estos casos:

- el bot ya no abre Siesa
- la tarea programada deja de correr
- aparecen muchos pedidos en `Error Siesa`
- los pedidos se quedan mucho tiempo en `Enviado a Siesa`
- el PC cambia de usuario, contraseña, escritorio o ruta de instalación
- cambian rutas del disco `U:`

---

## 12. Resumen operativo simple

1. Shopify envía pedidos
2. El sistema web los deja listos
3. El bot corre a horas definidas
4. El bot carga pedidos en Siesa
5. El sistema web marca cada pedido como:
- `Completado`
- o `Error Siesa`

En condiciones normales, el usuario solo revisa resultados y atiende excepciones.
