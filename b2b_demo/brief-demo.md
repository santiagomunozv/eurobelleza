# Demo — Portal de Pedidos B2B Eurobelleza

> Especificación funcional y visual para construir el **demo estático** del portal
> de distribuidores de Eurobelleza. Este documento es la fuente de verdad para
> generar el HTML/CSS/JS. Léelo completo antes de escribir código.

---

## 1. Contexto y objetivo

Eurobelleza (laboratorio cosmético, productor de la marca **Leche pal Pelo**) va a
construir un **portal web B2B** donde sus distribuidores tomen pedidos, vean su
catálogo y precios, su presupuesto del mes, su cartera y su cupo de
crédito. El proyecto real tiene 3 fases e integra con el ERP Siesa 8.5.

**Esto que vas a construir NO es el portal real: es un demo de presentación** para
usarse en la feria **Expobelleza 2026**. Su único propósito es **vender la visión**
a los distribuidores y recoger retroalimentación. Por lo tanto:

- Es **estático**: solo HTML, CSS y JavaScript. **Sin backend, sin base de datos, sin Siesa.**
- Los datos son **ficticios** y viven en el navegador (`localStorage`).
- Es **desechable**: no es la base de código del producto final. Prioriza impacto
  visual y fluidez del recorrido por encima de arquitectura.
- Debe verse **profesional, moderno y creíble**, como un producto terminado.

### Recorrido que debe demostrar (las 3 fases insinuadas en una sola experiencia)

1. **Login** simulado del distribuidor.
2. **Catálogo** de productos con precio distribuidor (sin inventario).
3. **Creación de pedido** por producto + cantidad (carrito).
4. **Pedido valorizado**: muestra el total y **cómo suma a la cartera** y **cómo consume el cupo** del distribuidor (alerta preventiva, no bloqueante).
5. **Presupuesto del mes** con su **avance, en gráficas y en valor**.
6. **Mis pedidos** con estados e **indicador de servicio (despacho 72 h)**.
7. **Condiciones comerciales** (incluye **descuento financiero**, actualizable por mes).
8. **Módulos administrativos** separados: Listado de distribuidores, Análisis por distribuidor, Productos, Listas de precios y Condiciones vigentes.

---

## 2. Stack técnico y restricciones

- **HTML5 + CSS3 + JavaScript vanilla** (sin frameworks pesados). Permitido un router simple por hash (`#/catalogo`, `#/pedidos`, etc.) o show/hide de vistas.
- **Gráficas:** usar **Chart.js** vía CDN (`https://cdn.jsdelivr.net/npm/chart.js`). No usar otras librerías de charts.
- **Iconos:** opcional, usar un set ligero vía CDN (p. ej. Lucide o Heroicons inline SVG). Evita dependencias grandes.
- **Persistencia:** `localStorage` para carrito, pedidos creados y sesión. Incluir un botón/acción **"Reiniciar demo"** que limpie `localStorage` y recargue los datos semilla.
- **Responsive:** debe verse bien en laptop/proyector (uso principal en feria) y en móvil/tablet. Mobile-first o adaptable con breakpoints.
- **Despliegue:** sitio estático desplegable en **GitHub Pages / Vercel** o ejecutable localmente abriendo `index.html`. Usar **rutas relativas** para todos los assets.
- **Sin llamadas de red reales.** Todo se resuelve en cliente con los datos semilla.

### Estructura de archivos sugerida

```
/index.html
/css/styles.css
/js/data.js      → datos semilla (distribuidores, catálogo, pedidos)
/js/app.js       → lógica de navegación, carrito, cálculos, charts
/assets/         → logo Eurobelleza, imágenes/placeholders
```

(Un solo `index.html` autocontenido también es aceptable si se prefiere, pero
mantén los datos semilla separados y fáciles de editar.)

---

## 3. Identidad visual (Eurobelleza)

El portal usa la marca **Eurobelleza** (el productor), no la de Leche pal Pelo.
La identidad corporativa es **azul**. Define estos _design tokens_ en CSS y úsalos
en todo el portal (deben poder cambiarse en una sola línea cada uno):

```css
:root {
    /* Azules corporativos (muestreados del logo real) */
    --eb-blue: #125ca3; /* primario: botones, barra superior, acentos */
    --eb-navy: #013a7d; /* profundidad: header, hovers, gráficas */
    --eb-steel: #506ba1; /* secundario: la corona del logo sobre blanco */

    /* Tints de apoyo (fondos suaves) */
    --eb-blue-50: #f4f8fc;
    --eb-blue-100: #eaf1f8;
    --eb-blue-200: #cfe0f0;

    /* Neutros */
    --eb-white: #ffffff;
    --eb-ink: #1a2230; /* texto principal */
    --eb-muted: #5b6573; /* texto secundario */
    --eb-border: #e2e8f0;

    /* Estados */
    --eb-success: #1e9e6a; /* completado / dentro de SLA */
    --eb-warning: #e8a33d; /* alerta preventiva / en riesgo */
    --eb-error: #d64545; /* mora / fuera de SLA / excede cupo */
}
```

- **Logo:** usar el logo de Eurobelleza (corona + wordmark "Eurobelleza"). En el demo,
  colócalo en la barra superior y en el login. Si no hay archivo disponible, dibuja
  una **corona simple en SVG** + el texto "Eurobelleza" con la tipografía del portal,
  respetando los azules. (El cliente entregará el PNG/SVG oficial para reemplazarlo.)
- **Tipografía:** sans moderna y legible. Usar **Poppins** (títulos) + **Inter** (texto)
  vía Google Fonts, con fallback a `system-ui, sans-serif`.
- **Tono visual:** limpio, aireado, corporativo-amable. Tarjetas con bordes suaves
  (`border-radius: 12–16px`), sombras sutiles, mucho espacio en blanco. La barra
  superior puede ir en `--eb-blue` o `--eb-navy` con el logo en blanco (como el logo
  sobre fondo azul). Fondo general `--eb-blue-50` o blanco.
- **Moneda:** todos los valores en **pesos colombianos (COP)**, formato `$1.234.567`
  (separador de miles con punto, sin decimales).

---

## 4. Productos del catálogo (reales, de Leche pal Pelo)

Sembrar el catálogo con estos productos reales. El precio mostrado es **el precio
distribuidor** y se etiqueta simplemente como **"Precio"**. **No mostrar PVP / precio
al público en ninguna parte.** El `sku` es demostrativo.

> **No se muestra inventario / stock en ninguna parte del portal.** Eurobelleza es
> el **productor** de los productos: si no hay existencias, simplemente las fabrica.
> Por lo tanto el catálogo nunca muestra "Disponible", "Agotado" ni cantidades, y
> **cualquier producto se puede pedir siempre**, sin restricción por existencias.

> Nota: estos valores se usan como precio distribuidor placeholder. El cliente
> reemplazará luego por su lista de precios mayorista real.

| SKU    | Producto                              | Línea               | Precio | Contenido |
| ------ | ------------------------------------- | ------------------- | ------ | --------- |
| EB-001 | Shampoo Tradicional                   | Tradicional         | 35.000 | 440 ML    |
| EB-002 | Dúo Deluxe                            | Tradicional         | 51.000 | Kit       |
| EB-003 | Mini Kit Tradicional                  | Combos              | 92.000 | Kit       |
| EB-004 | Shampoo Nutritivo                     | Regenerador Intenso | 35.000 | 440 ML    |
| EB-005 | Shampoo Protección Plus               | Protección Plus     | 35.000 | 440 ML    |
| EB-006 | Termoprotector Protección Plus        | Protección Plus     | 36.000 | 250 ML    |
| EB-007 | Acondicionador Rizos Perfectos        | Rizos y Ondas       | 40.000 | 440 ML    |
| EB-008 | Crema para Peinar Control Rizos       | Rizos y Ondas       | 40.000 | 440 ML    |
| EB-009 | Gelatina Slime Definidora             | Rizos y Ondas       | 48.000 | 440 ML    |
| EB-010 | Co-Wash                               | Rizos y Ondas       | 42.000 | 440 ML    |
| EB-011 | Óleo Rizos y Ondas                    | Rizos y Ondas       | 25.000 | 35 ML     |
| EB-012 | Agua de Coco (Activador de Rizos)     | Rizos y Ondas       | 27.000 | 250 ML    |
| EB-013 | Dúo Hidratación                       | Rizos y Ondas       | 51.000 | Kit       |
| EB-014 | Mini Kit Rizos y Ondas                | Combos              | 94.000 | Kit       |
| EB-015 | Shampoo + Acondicionador Niños (2en1) | Kids                | 35.000 | 440 ML    |
| EB-016 | Acondicionador para Niñas             | Kids                | 35.000 | 440 ML    |
| EB-017 | Combo Boys                            | Kids                | 68.000 | Kit       |
| EB-018 | Mascarilla WOW                        | Especial            | 92.000 | 250 ML    |
| EB-019 | Splash Capilar Sweet                  | Especial            | 26.500 | 250 ML    |
| EB-020 | Finish Chia Oil                       | Especial            | 25.000 | 35 ML     |

**Líneas (categorías) para filtrar:** Tradicional · Regenerador Intenso ·
Protección Plus · Rizos y Ondas · Kids · Especial · Combos.

**Imágenes:** para mantener el demo autocontenido, usar **placeholders estilizados**
(tarjeta con el azul de marca, ícono de gotero/frasco e iniciales o nombre del
producto). Opcionalmente se pueden enlazar imágenes reales desde el CDN de
lechepalpelo.com, pero los placeholders evitan imágenes rotas en la feria.

---

## 5. Distribuidores demo (datos semilla)

Precargar **dos** distribuidores para poder alternar en el login y mostrar escenarios
distintos (uno sano, uno con alertas).

### Distribuidor 1 — escenario sano

- **Nombre:** Distribuciones Bella S.A.S.
- **NIT:** 901.234.567-8
- **Ciudad / Zona:** Medellín / Antioquia
- **Usuario:** `bella` · **Clave:** `demo123`
- **Lista de precios:** Mayorista A
- **Cupo asignado:** `$8.000.000`
- **Saldo en cartera (actual):** `$3.250.000`
- **Mora:** `$0`
- **Presupuesto del mes (meta, valor):** `$6.000.000`
- **Ejecutado del mes (pedidos del portal):** `$2.150.000` (≈ 35,8%)

### Distribuidor 2 — escenario con alertas

- **Nombre:** Salón & Estilo Cali
- **NIT:** 900.987.654-3
- **Ciudad / Zona:** Cali / Valle
- **Usuario:** `cali` · **Clave:** `demo123`
- **Lista de precios:** Mayorista B
- **Cupo asignado:** `$5.000.000`
- **Saldo en cartera (actual):** `$4.600.000` → poco cupo disponible
- **Mora:** `$320.000` → debe disparar alerta de mora
- **Presupuesto del mes (meta, valor):** `$4.000.000`
- **Ejecutado del mes (pedidos del portal):** `$3.480.000` (≈ 87%)

### Condiciones comerciales (por distribuidor, "vigente: junio 2026")

- **Descuento financiero por pronto pago:** **5%** si paga en ≤ 30 días.
- **Plazo de crédito:** 60 días.
- **Política de despacho:** **72 horas** desde el ingreso del pedido.
- Texto visible: "Condiciones vigentes para junio 2026 — actualizables mensualmente".

### Pedidos históricos (sembrar ~4–6 por distribuidor para "Mis pedidos")

Variar estados y fechas para que el indicador de servicio luzca. Ejemplo Distribuidor 1:

| # Pedido | Fecha ingreso | Valor      | Estado         |
| -------- | ------------- | ---------- | -------------- |
| PED-1042 | hoy − 1 h     | $850.000   | Registrado     |
| PED-1039 | hoy − 20 h    | $1.300.000 | Enviado al ERP |
| PED-1031 | hace 2 días   | $980.000   | Remisionado    |
| PED-1024 | hace 5 días   | $2.100.000 | Facturado      |
| PED-1018 | hace 9 días   | $640.000   | Facturado      |

---

## 6. Pantallas y comportamiento

### 6.1 Login (simulado)

- Pantalla centrada con fondo azul de marca o split (panel azul con logo + formulario blanco).
- Campos usuario y clave. Validar contra los distribuidores semilla.
- Enlace/nota "Demo — Expobelleza 2026". Mostrar credenciales de prueba en pantalla
  (ej. "Usuario: bella · Clave: demo123") para facilitar la demostración.
- Al autenticar, guarda la sesión en `localStorage` y entra al Dashboard.

### 6.2 Layout autenticado

- **Barra superior** (azul): logo Eurobelleza, nombre del distribuidor logueado,
  acceso a "Mi cuenta"/cerrar sesión, y carrito con contador de ítems.
- **Navegación** (lateral o superior): Inicio · Catálogo · Mis pedidos · Presupuesto · Condiciones comerciales.
- En sesión **admin**, navegación por módulos: Listado · Análisis · Productos · Listas de precios · Condiciones vigentes.
- Acción discreta **"Reiniciar demo"** (limpia `localStorage`).

### 6.3 Inicio / Dashboard del distribuidor

Tarjetas resumen (KPIs) + gráficas:

- **Presupuesto del mes:** gráfica (dona o barra de progreso) con **valor ejecutado vs meta** y el **% de avance**. Mostrar también en texto: `$2.150.000 / $6.000.000 (36%)`.
- **Cupo:** asignado, **disponible** (`asignado − cartera`), usado. Barra de uso.
- **Cartera:** saldo actual y mora (resaltar mora en rojo si > 0).
- **Facturado esta semana** y **Facturado este mes** (por distribuidor y por corte de fechas), con conteo de pedidos del período.
- **Indicador de servicio:** % de pedidos despachados dentro de 72 h (valor demostrativo, p. ej. 92%).
- **Pedidos recientes:** mini-lista con estado.

### 6.4 Catálogo

- Encabezado con buscador por nombre.
- **Filtro por línea** (chips o select) y, opcional, por precio.
- **Grid de tarjetas**: imagen/placeholder, nombre, línea, **Precio** (distribuidor),
  selector de cantidad y botón **"Agregar"**. **No mostrar existencias** (cualquier
  producto se puede pedir siempre).
- Al agregar, actualizar el contador del carrito (sin recargar) y dar feedback (toast).

### 6.5 Carrito → Pedido valorizado ⭐ (pantalla clave)

Esta es la pantalla estrella para la feria. Dos columnas:

**Columna izquierda — líneas del pedido:**

- Cada línea: producto, precio unitario, cantidad editable (+/−), subtotal de línea, quitar.
- Total de unidades y **Total del pedido (valorizado)**.

**Columna derecha — panel de impacto financiero** (lo que pidió el gerente):
Mostrar, en vivo, cómo el pedido actual impacta al distribuidor:

- **Valor de este pedido:** `$X`
- **Cartera:** `actual $Y` → **`después del pedido $Y + $X`**
- **Cupo disponible:** `actual ($cupo − $cartera)` → **`restante después del pedido`**
- **Descuento financiero aplicable** (condiciones comerciales): "Si paga en ≤ 30 días: −5% → ahorro $… (referencia)". Es informativo; no descuenta el total del pedido.
- **Política de despacho:** "Despacho estimado dentro de 72 h una vez confirmado."

**Alertas preventivas (NO bloqueantes):**

- Si `cartera + pedido > cupo`: banner ámbar/rojo "Este pedido supera tu cupo disponible en $…". El pedido **igual se puede confirmar** (el control vinculante real lo hace Siesa; aquí es solo preventivo).
- Si el distribuidor tiene **mora > 0**: banner "Tienes una mora de $… pendiente." (preventivo, no bloquea).
- Botón **"Confirmar pedido"** → crea un pedido en estado **Registrado**, lo guarda en `localStorage`, vacía el carrito, muestra confirmación con el número (PED-####) y redirige a "Mis pedidos".

### 6.6 Mis pedidos

- Tabla/lista de pedidos del distribuidor con: número, fecha de ingreso, valor, **estado** (badge de color) e **indicador de servicio 72 h**.
- **Estados** (ver §7) con colores.
- **Indicador de servicio por pedido:** calcular horas transcurridas desde el ingreso:
    - Si está **Facturado y despachado** dentro de 72 h → "A tiempo" (verde).
    - Si no está facturado y despachado y van < 72 h → "En término — quedan Xh" (azul/ámbar).
    - Si van > 72 h sin cierre → "Fuera de SLA" (rojo).
- Clic en un pedido → **detalle**: líneas con foto y cantidades, valor, estado, línea de tiempo de estados y observaciones administrativas (si existen).
- Botones de descarga de reportes: **Excel** y **PDF**.

### 6.9 Administración (módulos separados)

- **Listado de distribuidores**: tabla con distribuidor, lista, presupuesto, ejecutado, % cumplimiento, cupo, cartera, mora, uso cupo, estado y acciones.
- **Análisis por distribuidor**: selector de distribuidor, KPIs (presupuesto, ejecutado, facturado semana/mes), gráficas (meta vs ejecutado y ejecución por línea) y pedidos del distribuidor seleccionado.
- **Pedidos en análisis (admin)**: edición por pedido para ajustar estado, marcar despacho **completo/parcial** y registrar observaciones.
- **Productos**: listado de productos con foto, estado (activo/inactivo) y acceso a detalle.
- **Detalle de producto**: tabla de precios por lista de precios y acción de activar/desactivar producto.
- **Listas de precios**: mantenimiento de listas y precio por producto por lista.
- **Condiciones vigentes**: mantenimiento de mes vigente, descuento, plazo y horas de despacho.

### 6.7 Presupuesto (gráficas + valor)

- **Gráfica principal:** ejecución del mes — **meta vs ejecutado (valor)**. Barra o dona con % de avance bien visible.
- **Gráfica secundaria:** ejecución por **línea/categoría** (barras), usando los pedidos del distribuidor para repartir el ejecutado por categoría (valores demostrativos).
- Tarjetas con: meta del mes, ejecutado, faltante para la meta, % de avance.
- Texto: "Presupuesto de junio 2026 — actualizable mensualmente por el administrador."

### 6.8 Condiciones comerciales

- Panel con: lista de precios asignada, **descuento financiero** (5% pronto pago ≤30 días), plazo de crédito (60 días), política de despacho (72 h), cupo asignado.
- Nota visible: "Condiciones vigentes para junio 2026 — actualizables cada mes."

---

## 7. Estados del pedido (colores)

| Estado                 | Color (token)  | Significado                           |
| ---------------------- | -------------- | ------------------------------------- |
| Registrado             | `--eb-steel`   | Pedido tomado en el portal.           |
| Recepcionado Siesa     | `--eb-blue`    | Pedido recibido por Siesa (simulado). |
| En alistamiento        | `--eb-warning` | Pedido en preparación logística.      |
| Facturado y despachado | `--eb-success` | Pedido cerrado y despachado.          |

(En el demo los estados de los pedidos históricos vienen sembrados. Los pedidos
nuevos creados por el usuario nacen en "Registrado". Opcional: un botón oculto/demo
para "avanzar estado" de un pedido y mostrar la transición, si se quiere lucir el flujo.)

---

## 8. Reglas de cálculo

- **Total del pedido** = Σ (precio_unitario × cantidad) de cada línea.
- **Cupo disponible** = `cupo_asignado − saldo_cartera`.
- **Cartera después del pedido** = `saldo_cartera + total_pedido`.
- **Cupo restante después del pedido** = `cupo_asignado − (saldo_cartera + total_pedido)` (puede ser negativo → dispara alerta).
- **% avance presupuesto** = `ejecutado_mes / meta_mes × 100` (redondear a entero).
- **Descuento financiero (referencia)** = `total_pedido × 5%` (informativo, no altera el total).
- **Indicador de servicio (72 h)**: comparar `ahora − fecha_ingreso` contra 72 h según el estado (ver §6.6).
- **Regla de inicio SLA (72 h):**
    - Si el pedido entra **antes de las 9:00 a.m.**, el conteo inicia el mismo día (00:00).
    - Si el pedido entra **desde las 9:00 a.m. en adelante**, el conteo inicia al día siguiente (00:00).
- Formato moneda COP: `$` + miles con punto, sin decimales (ej. `$2.150.000`).

---

## 9. Lo que el demo NO hace (alcance)

- No se integra con Siesa 8.5 ni con ningún sistema real.
- No tiene autenticación real ni usuarios persistentes entre dispositivos.
- No persiste entre navegadores/dispositivos distintos (solo `localStorage` local).
- Es ilustrativo y desechable; no es la base de código del producto final.

---

## 10. Criterios de aceptación

- [ ] Identidad **azul de Eurobelleza** aplicada consistentemente (tokens del §3).
- [ ] Login simulado funcional con los dos distribuidores.
- [ ] Catálogo con productos reales, filtro por línea, precio distribuidor **sin PVP** y **sin inventario/existencias**.
- [ ] Carrito + **pedido valorizado** con panel de impacto en **cartera y cupo** y **alertas preventivas no bloqueantes**.
- [ ] **Presupuesto del mes en gráfica + valor** con % de avance.
- [ ] "Mis pedidos" con estados de colores, **indicador de servicio 72 h con regla 9:00 a.m.** y detalle con línea de tiempo.
- [ ] Exportación de pedidos en **Excel y PDF**.
- [ ] Condiciones comerciales con **descuento financiero** y nota de actualización mensual.
- [ ] Módulos admin separados: **Listado** y **Análisis** en rutas independientes.
- [ ] Módulo admin de **Productos** con detalle, tabla de precios por lista y estado activo/inactivo.
- [ ] Responsive (laptop/proyector y móvil) y desplegable como sitio estático.
- [ ] Botón "Reiniciar demo" que restablece los datos semilla.
