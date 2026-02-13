# Manual de Arquitectura de Código

## Tabla de Contenidos
1. [Principios Fundamentales](#1-principios-fundamentales)
2. [Arquitectura MVC](#2-arquitectura-mvc)
3. [Controladores](#3-controladores)
4. [Servicios](#4-servicios)
5. [Repositorios](#5-repositorios)
6. [Inyección de Dependencias](#6-inyección-de-dependencias)
7. [Manejo de Requests](#7-manejo-de-requests)
8. [Validaciones](#8-validaciones)
9. [Manejo de Condicionales](#9-manejo-de-condicionales)
10. [Definición de Variables](#10-definición-de-variables)
11. [Manejo de Retornos](#11-manejo-de-retornos)
12. [Separación de Funciones](#12-separación-de-funciones)

---

## 1. Principios Fundamentales

### Separación de Responsabilidades
Cada componente debe tener una única responsabilidad clara:

- **Controlador**: Orquesta la petición HTTP
- **Servicio**: Contiene lógica de negocio
- **Repositorio**: Maneja acceso a datos
- **Modelo**: Representa entidades de datos

### Principio DRY (Don't Repeat Yourself)
Si escribes el mismo código más de dos veces, créa una función o servicio reutilizable.

---

## 2. Arquitectura MVC


┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CONTROLADOR   │────│    SERVICIO     │────│   REPOSITORIO   │
│   (Orquesta)    │    │ (Lógica Negocio)│    │ (Acceso Datos)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     REQUEST     │    │     MODELOS     │    │   BASE DATOS    │
│   VALIDACIÓN    │    │   (Entidades)   │    │   (MySQL, etc)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘


---

## 3. Controladores

### QUÉ DEBE TENER UN CONTROLADOR:
- Recibir requests (con validación automática de Laravel)
- Inyectar servicios en los métodos (no en constructor)
- Llamar servicios para lógica compleja
- **Operaciones CRUD simples** (find, delete, updates básicos)
- **Manejo de transacciones** cuando hay múltiples entidades
- **Manejo centralizado de excepciones** con try-catch
- **Uso de ENUMs** en lugar de strings hardcodeados
- Retornar respuestas HTTP

### QUÉ NO DEBE TENER UN CONTROLADOR:
- Lógica de negocio compleja
- Consultas complejas a base de datos
- Cálculos de negocio
- Validaciones de negocio complejas (solo usar Request classes)
- Llamadas a funciones privadas dentro del mismo controlador
- Funciones auxiliares o helpers

### EXCEPCIÓN: Operaciones Simples SÍ se pueden hacer en controladores:
- **Eliminaciones directas** con validaciones básicas
- **Actualizaciones de estado** usando ENUMs
- **Búsquedas simples** para verificar existencia
- **Operaciones que NO requieren lógica de negocio**

### REGLAS IMPORTANTES PARA LARAVEL:
1. **No usar constructores** para inyección de dependencias
2. **Inyectar dependencias directamente** en los métodos
3. **Las validaciones se hacen automáticamente** con Request classes
4. **Un controlador = un recurso HTTP** (productos, usuarios, etc.)
5. **No crear métodos auxiliares** dentro del controlador
6. **Usar transacciones** cuando se involucran múltiples entidades
7. **`DB::beginTransaction()` SIEMPRE fuera del try-catch**
8. **Manejar excepciones centralizadamente** - usar `internalErrorResponse()`
9. **Un solo catch por método** - capturar `Exception` genérica
10. **USAR ENUMs** en lugar de strings hardcodeados
11. **Operaciones CRUD simples** SÍ se pueden hacer directamente en el controlador

### ✅ EJEMPLO CORRECTO:


use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function store(CreateProductoRequest $request, ProductoService $productoService): JsonResponse
    {
        DB::beginTransaction();

        try {
            $producto = $productoService->crear($request->validated());

            DB::commit();

            return response()->json([
                'mensaje' => 'Producto creado exitosamente',
                'data' => $producto
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->internalErrorResponse($e);
        }
    }

    public function index(Request $request, ProductoService $productoService): JsonResponse
    {
        try {
            $filtros = $request->only(['categoria', 'estado', 'precio_min', 'precio_max']);
            $productos = $productoService->obtenerProductos($filtros);

            return response()->json([
                'data' => $productos
            ]);

        } catch (Exception $e) {
            return $this->internalErrorResponse($e);
        }
    }

    public function update(int $id, UpdateProductoRequest $request, ProductoService $productoService): JsonResponse
    {
        DB::beginTransaction();

        try {
            $producto = $productoService->actualizar($id, $request->validated());

            DB::commit();

            return response()->json([
                'mensaje' => 'Producto actualizado exitosamente',
                'data' => $producto
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->internalErrorResponse($e);
        }
    }

    public function destroy(int $id, ProductoService $productoService): JsonResponse
    {
        DB::beginTransaction();

        try {
            $productoService->eliminar($id);

            DB::commit();

            return response()->json([
                'mensaje' => 'Producto eliminado exitosamente'
            ], 204);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->internalErrorResponse($e);
        }
    }
}


### ✅ EJEMPLO CORRECTO - Operación Simple en Controlador:


class RegistroProduccionController extends Controller
{
    public function destroy(int $id): JsonResponse
    {
        try {
            $registroProduccion = RegistroProduccion::query()->findOrFail($id);

            // Validación simple de negocio
            $registroProduccionProducto = RegistroProduccionProductoRepository::findRegistroProduccionProductoById($registroProduccion->id);

            if ($registroProduccionProducto) {
                throw new RuntimeException('No se puede anular el registro de producción. Tiene información asociada.');
            }

            // ✅ CORRECTO: Usar ENUM en lugar de string
            $registroProduccion->estado = EstadoRegistroProduccionEnum::ANULADO;
            $registroProduccion->save();

            return response([], Response::HTTP_CREATED);

        } catch (Exception $e) {
            return $this->internalErrorResponse($e);
        }
    }

    public function aprobar(int $id): JsonResponse
    {
        try {
            $pedido = Pedido::findOrFail($id);

            // Validación simple
            if ($pedido->estado === PedidoEstadosEnum::FINALIZADO) {
                throw new DomainException('No se puede aprobar un pedido finalizado');
            }

            // ✅ CORRECTO: Usar ENUM
            $pedido->estado = PedidoEstadosEnum::APROBADO;
            $pedido->fecha_aprobacion = now();
            $pedido->save();

            return response()->json([
                'mensaje' => 'Pedido aprobado exitosamente'
            ], 200);

        } catch (Exception $e) {
            return $this->internalErrorResponse($e);
        }
    }
}



### ❌ EJEMPLO INCORRECTO:


class ProductoController extends Controller
{
    // MALO: Constructor para inyección de dependencias
    public function __construct(private ProductoService $productoService) {}

    public function store(Request $request): JsonResponse
    {
        // MALO: Validación manual en controlador
        if (!$request->has('nombre')) {
            return response()->json(['error' => 'Nombre requerido'], 400);
        }

        // MALO: Lógica de negocio en controlador
        if ($request->precio < 0) {
            throw new Exception('Precio no puede ser negativo');
        }

        // MALO: Query directo en controlador
        $producto = DB::table('productos')->insert([
            'nombre' => $request->nombre,
            'precio' => $request->precio * 1.21, // MALO: Cálculo de impuestos aquí
            'estado' => 'ACTIVO', // MALO: String hardcodeado
            'created_at' => now()
        ]);

        return response()->json($producto);
    }

    // MALO: Función auxiliar dentro del controlador
    private function validarProducto(array $datos): bool
    {
        return isset($datos['nombre']) && !empty($datos['nombre']);
    }

    // MALO: Llamada a función auxiliar del mismo controlador
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->validarProducto($request->all())) {
            return response()->json(['error' => 'Datos inválidos'], 400);
        }

        // MALO: String hardcodeado para estado
        DB::table('productos')
            ->where('id', $id)
            ->update(['estado' => 'PENDIENTE']); // ← MAL: String hardcodeado

        return response()->json(['mensaje' => 'Actualizado']);
    }
}


---

## 3.2. Uso de ENUMs

### ¿Por qué usar ENUMs?

Los ENUMs centralizan valores constantes y evitan errores de tipeo, mejoran el autocompletado del IDE y facilitan el refactoring.

---

### ❌ INCORRECTO - Strings hardcodeados:


// MALO: Strings dispersos por todo el código
$pedido->estado = 'PENDIENTE';
$pedido->estado = 'APROBADO';
$pedido->estado = 'RECHAZADO';
$pedido->estado = 'FINALIZADO';

// MALO: Propenso a errores de tipeo
$pedido->estado = 'APROVADO'; // ← Error de tipeo
$pedido->estado = 'pendiente'; // ← Inconsistencia mayúsculas

// MALO: Validaciones manuales
if ($pedido->estado === 'APROBADO' || $pedido->estado === 'FINALIZADO') {
    // lógica...
}


---

### ✅ CORRECTO - Usando ENUMs:

**Definición del ENUM:**


enum PedidoEstadosEnum: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case FINALIZADO = 'FINALIZADO';
    case ANULADO = 'ANULADO';

    public static function getValoresPermitidos(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function puedeSerAprobado(): bool
    {
        return $this === self::PENDIENTE;
    }

    public function puedeSerAnulado(): bool
    {
        return in_array($this, [self::PENDIENTE, self::APROBADO]);
    }
}


**Uso en el código:**


// Asignar estados usando ENUM
$pedido->estado = PedidoEstadosEnum::PENDIENTE;
$pedido->estado = PedidoEstadosEnum::APROBADO;

// Validaciones con métodos del ENUM
if ($pedido->estado->puedeSerAprobado()) {
    $pedido->estado = PedidoEstadosEnum::APROBADO;
}


**En migraciones:**


$table->enum('estado', PedidoEstadosEnum::getValoresPermitidos())
      ->default(PedidoEstadosEnum::PENDIENTE->value);


**En modelos:**


class Pedido extends Model
{
    protected $casts = [
        'estado' => PedidoEstadosEnum::class,
    ];
}


### Ejemplos de ENUMs comunes:


enum EstadoUsuarioEnum: string
{
    case ACTIVO = 'ACTIVO';
    case INACTIVO = 'INACTIVO';
    case SUSPENDIDO = 'SUSPENDIDO';
    case ELIMINADO = 'ELIMINADO';
}

enum TipoDocumentoEnum: string
{
    case CEDULA = 'CC';
    case PASAPORTE = 'PA';
    case CEDULA_EXTRANJERIA = 'CE';
    case NIT = 'NIT';
}

enum PrioridadEnum: string
{
    case BAJA = 'BAJA';
    case MEDIA = 'MEDIA';
    case ALTA = 'ALTA';
    case CRITICA = 'CRITICA';
}


---

## 3.1. Manejo de Transacciones y Excepciones en Controladores

### Cuándo usar Transacciones:

**USAR transacciones cuando:**
- Se crean/actualizan múltiples entidades relacionadas
- Una operación falla y debe revertir cambios anteriores
- Se requiere consistencia de datos
- Hay dependencias entre operaciones

**Ejemplos de operaciones que requieren transacciones:**
- Crear pedido + items + actualizar stock + crear factura
- Transferir dinero entre cuentas (debitar una, acreditar otra)
- Registrar usuario + crear perfil + enviar email de bienvenida
- Actualizar producto + registrar movimiento de inventario

### ✅ PATRÓN CORRECTO para múltiples entidades:


public function crearPedido(CreatePedidoRequest $request, PedidoService $pedidoService): JsonResponse
{
    DB::beginTransaction();

    try {
        // Esta operación involucra: pedido + items + stock + factura
        $pedido = $pedidoService->crearPedidoCompleto($request->validated());

        DB::commit();

        return response()->json([
            'mensaje' => 'Pedido creado exitosamente',
            'data' => $pedido
        ], 201);

    } catch (Exception $e) {
        DB::rollBack();
        return $this->internalErrorResponse($e);
    }
}


**La función `internalErrorResponse()` se encarga de:**
- Identificar el tipo de excepción recibida
- Determinar el código HTTP apropiado
- Formatear la respuesta JSON consistentemente
- Loggear errores cuando sea necesario

---

### Ejemplo de implementación de `internalErrorResponse()`:


// En tu BaseController o Controller principal
protected function internalErrorResponse(Exception $e): JsonResponse
{
    return match (true) {
        $e instanceof ModelNotFoundException => response()->json([
            'error' => 'Recurso no encontrado',
            'mensaje' => 'El recurso solicitado no existe'
        ], 404),

        $e instanceof DomainException => response()->json([
            'error' => 'Error de lógica de negocio',
            'mensaje' => $e->getMessage()
        ], 422),

        $e instanceof StockInsuficienteException => response()->json([
            'error' => 'Stock insuficiente',
            'mensaje' => $e->getMessage(),
            'productos_sin_stock' => $e->getProductosSinStock()
        ], 422),

        $e instanceof CreditoInsuficienteException => response()->json([
            'error' => 'Crédito insuficiente',
            'mensaje' => $e->getMessage()
        ], 422),

        $e instanceof ValidationException => response()->json([
            'error' => 'Error de validación',
            'errores' => $e->errors()
        ], 422),

        default => response()->json([
            'error' => 'Error interno del servidor',
            'mensaje' => 'Ha ocurrido un error inesperado'
        ], 500)
    };
}


---

### ❌ INCORRECTO - Sin transacciones:


public function crearPedido(CreatePedidoRequest $request, PedidoService $pedidoService): JsonResponse
{
    // MALO: Sin transacciones - si falla algo queda inconsistente
    $pedido = $pedidoService->crearPedido($request->validated());
    $pedidoService->crearItems($pedido->id, $request->items);
    $pedidoService->actualizarStock($request->items); // Si falla aquí queda inconsistente
    $pedidoService->generarFactura($pedido->id);

    return response()->json(['data' => $pedido], 201);
}


---

### ❌ INCORRECTO - beginTransaction dentro del try:


public function crearPedido(CreatePedidoRequest $request, PedidoService $pedidoService): JsonResponse
{
    try {
        DB::beginTransaction(); // ← MAL: Si falla aquí, no se puede hacer rollback

        $pedido = $pedidoService->crearPedidoCompleto($request->validated());

        DB::commit();

        return response()->json(['data' => $pedido], 201);

    } catch (Exception $e) {
        DB::rollBack(); // ← Este rollback no funcionaría si beginTransaction falló
        return $this->internalErrorResponse($e);
    }
}


---

### **REGLA CRÍTICA PARA TRANSACCIONES:**


// ✅ ESTRUCTURA CORRECTA:
DB::beginTransaction();        // ← SIEMPRE fuera del try
try {
    // operaciones que pueden fallar
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();            // ← Garantizado que funcione
    return $this->internalErrorResponse($e);
}


### IMPORTANTE: Los servicios NO deben manejar try-catch

Los servicios deben lanzar excepciones y dejar que el controlador las maneje:


// ✅ CORRECTO en el Servicio:
public function crearPedidoCompleto(array $datos): Pedido
{
    // NO usar try-catch aquí - dejar que las excepciones suban al controlador
    $this->validarDatosNegocio($datos);

    $pedido = $this->pedidoRepository->crear($datos);

    foreach ($datos['items'] as $item) {
        $this->verificarStock($item);
        $this->inventarioService->reducirStock($item['producto_id'], $item['cantidad']);
    }

    $this->facturaService->generarFactura($pedido);

    return $pedido;
}

private function verificarStock(array $item): void
{
    $stock = $this->inventarioService->obtenerStock($item['producto_id']);

    if ($stock < $item['cantidad']) {
        // Lanzar excepción - el controlador la manejará
        throw new StockInsuficienteException(
            "Stock insuficiente para producto {$item['producto_id']}"
        );
    }
}


### Función Centralizada de Manejo de Errores

Para mantener el código limpio y consistente, usa una función centralizada para manejar todas las excepciones:


// En BaseController o en un trait
protected function internalErrorResponse(Exception $e): JsonResponse
{
    // Opcional: Loggear el error para debugging
    Log::error('Error en controlador', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    return match (true) {
        // Errores de recursos no encontrados
        $e instanceof ModelNotFoundException => response()->json([
            'error' => 'Recurso no encontrado',
            'mensaje' => 'El recurso solicitado no existe'
        ], Response::HTTP_NOT_FOUND),

        // Errores de lógica de negocio
        $e instanceof DomainException => response()->json([
            'error' => 'Error de lógica de negocio',
            'mensaje' => $e->getMessage()
        ], Response::HTTP_UNPROCESSABLE_ENTITY),

        // Errores específicos de tu dominio
        $e instanceof StockInsuficienteException => response()->json([
            'error' => 'Stock insuficiente',
            'mensaje' => $e->getMessage(),
            'detalles' => $e->getDetalles()
        ], Response::HTTP_UNPROCESSABLE_ENTITY),

        $e instanceof CreditoInsuficienteException => response()->json([
            'error' => 'Crédito insuficiente',
            'mensaje' => $e->getMessage()
        ], Response::HTTP_UNPROCESSABLE_ENTITY),

        $e instanceof PermisoInsuficienteException => response()->json([
            'error' => 'Permisos insuficientes',
            'mensaje' => 'No tienes permisos para realizar esta acción'
        ], Response::HTTP_FORBIDDEN),

        // Errores de validación (aunque Laravel los maneja automáticamente)
        $e instanceof ValidationException => response()->json([
            'error' => 'Error de validación',
            'errores' => $e->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY),

        // Error genérico - no exponer detalles técnicos
        default => response()->json([
            'error' => 'Error interno del servidor',
            'mensaje' => 'Ha ocurrido un error inesperado'
        ], Response::HTTP_INTERNAL_SERVER_ERROR)
    };
}


### Ventajas de este enfoque:

1. **Consistencia**: Todas las respuestas de error tienen el mismo formato
2. **Mantenimiento**: Cambios en el manejo de errores se hacen en un solo lugar
3. **Limpieza**: Los controladores quedan más limpios con un solo catch
4. **Logging**: Puedes centralizar el logging de errores
5. **Seguridad**: Controlas qué información expones al cliente

---

## 3.3. Cuándo usar Servicios vs Operaciones Directas

### ✅ USAR SERVICIOS cuando hay:
- **Lógica de negocio compleja**
- **Múltiples entidades involucradas**
- **Cálculos o transformaciones**
- **Validaciones de reglas de negocio**
- **Coordinación entre repositorios**
- **Procesamiento que puede reutilizarse**


// Ejemplo: Crear pedido (lógica compleja)
public function store(CreatePedidoRequest $request, PedidoService $pedidoService): JsonResponse
{
    DB::beginTransaction();

    try {
        // Usar servicio porque involucra: validaciones complejas, stock, cálculos, etc.
        $pedido = $pedidoService->crearPedidoCompleto($request->validated());

        DB::commit();

        return response()->json([
            'mensaje' => 'Pedido creado exitosamente',
            'data' => $pedido
        ], 201);

    } catch (Exception $e) {
        DB::rollBack();
        return $this->internalErrorResponse($e);
    }
}


### ✅ OPERACIONES DIRECTAS cuando:
- **CRUD simple** sin lógica adicional
- **Cambios de estado básicos** con validaciones simples
- **Eliminaciones** con validaciones de relaciones
- **Actualizaciones de campos** sin procesamiento complejo


// Ejemplo: Activar/desactivar usuario (operación simple)
public function toggleEstado(int $id): JsonResponse
{
    try {
        $usuario = Usuario::findOrFail($id);

        // Validación simple
        if ($usuario->es_admin && $usuario->estado === UsuarioEstadoEnum::ACTIVO) {
            throw new DomainException('No se puede desactivar un administrador');
        }

        // Cambio simple de estado usando ENUM
        $usuario->estado = $usuario->estado === UsuarioEstadoEnum::ACTIVO
            ? UsuarioEstadoEnum::INACTIVO
            : UsuarioEstadoEnum::ACTIVO;

        $usuario->save();

        return response()->json([
            'mensaje' => 'Estado actualizado exitosamente'
        ]);

    } catch (Exception $e) {
        return $this->internalErrorResponse($e);
    }
}

// Ejemplo: Eliminar con validación simple
public function destroy(int $id): JsonResponse
{
    try {
        $registroProduccion = RegistroProduccion::findOrFail($id);

        // Validación simple de relaciones
        $tieneProductos = RegistroProduccionProducto::where('registro_produccion_id', $id)->exists();

        if ($tieneProductos) {
            throw new DomainException('No se puede eliminar. Tiene productos asociados.');
        }

        // Cambio de estado simple usando ENUM
        $registroProduccion->estado = EstadoRegistroProduccionEnum::ANULADO;
        $registroProduccion->save();

        return response()->json([
            'mensaje' => 'Registro anulado exitosamente'
        ]);

    } catch (Exception $e) {
        return $this->internalErrorResponse($e);
    }
}


### Criterios de decisión:

| Usar Servicio | Operación Directa |
|---------------|-------------------|
| Múltiples entidades | Una sola entidad |
| Cálculos complejos | Cambios simples |
| Reglas de negocio | Validaciones básicas |
| Transacciones complejas | Updates directos |
| Reutilizable | Específico del endpoint |

---

## 4. Servicios

Los servicios contienen toda la lógica de negocio de la aplicación.

### REGLAS PARA SERVICIOS:
1. **Contienen lógica de negocio**
2. **Lanzan excepciones, NO las manejan**
3. **No usan try-catch** - dejan que el controlador las capture
4. **Realizan validaciones de reglas de negocio**
5. **Coordinan entre repositorios y otros servicios**

---

### Estructura de un Servicio:


class ProductoService
{
    public function __construct(
        private ProductoRepository $productoRepository,
        private InventarioService $inventarioService,
        private NotificacionService $notificacionService
    ) {}

    public function crear(array $datos): Producto
    {
        // Validaciones de negocio - lanzar excepciones, no manejarlas
        $this->validarReglasDenegocio($datos);

        // Procesamiento de datos
        $datos['precio_con_impuestos'] = $this->calcularPrecioConImpuestos($datos['precio']);
        $datos['codigo'] = $this->generarCodigoUnico();

        // Crear producto
        $producto = $this->productoRepository->crear($datos);

        // Procesos adicionales que pueden fallar
        $this->inventarioService->registrarStock($producto->id, $datos['stock_inicial']);
        $this->notificacionService->notificarNuevoProducto($producto);

        return $producto;
    }

    public function obtenerProductos(array $filtros): Collection
    {
        // Procesar filtros
        $filtrosProcesados = $this->procesarFiltros($filtros);

        // Obtener datos
        return $this->productoRepository->obtenerConFiltros($filtrosProcesados);
    }

    public function actualizar(int $id, array $datos): Producto
    {
        $producto = $this->productoRepository->obtenerPorId($id);

        if (!$producto) {
            throw new ModelNotFoundException('Producto no encontrado');
        }

        $this->validarCambiosPermitidos($producto, $datos);

        return $this->productoRepository->actualizar($id, $datos);
    }

    private function validarReglasDenegocio(array $datos): void
    {
        if ($datos['precio'] <= 0) {
            throw new DomainException('El precio debe ser mayor a 0');
        }

        if ($this->productoRepository->existeConNombre($datos['nombre'])) {
            throw new DomainException('Ya existe un producto con ese nombre');
        }
    }

    private function validarCambiosPermitidos(Producto $producto, array $datos): void
    {
        if ($producto->estado === 'discontinuado' && isset($datos['precio'])) {
            throw new DomainException('No se puede cambiar el precio de un producto discontinuado');
        }
    }

    private function calcularPrecioConImpuestos(float $precio): float
    {
        return $precio * 1.21; // IVA 21%
    }

    private function generarCodigoUnico(): string
    {
        return 'PROD-' . date('Y') . '-' . str_pad(
            $this->productoRepository->contarProductosDelAño(),
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}



---

## 5. Repositorios

Los repositorios solo se encargan de acceder a los datos, sin lógica de negocio.

### QUÉ DEBE TENER UN REPOSITORIO:
- Queries a la base de datos
- Conversión de datos
- Filtros básicos
- Operaciones CRUD

### QUÉ NO DEBE TENER UN REPOSITORIO:
- Lógica de negocio
- Validaciones complejas
- Cálculos de negocio
- Dependencias a otros servicios

### ✅ EJEMPLO CORRECTO:


class ProductoRepository
{
    public function __construct(
        private Producto $modelo
    ) {}

    public function crear(array $datos): Producto
    {
        return $this->modelo->create($datos);
    }

    public function obtenerPorId(int $id): ?Producto
    {
        return $this->modelo->find($id);
    }

    public function obtenerConFiltros(array $filtros): Collection
    {
        $query = $this->modelo->newQuery();

        if (isset($filtros['categoria_id'])) {
            $query->where('categoria_id', $filtros['categoria_id']);
        }

        if (isset($filtros['precio_min'])) {
            $query->where('precio', '>=', $filtros['precio_min']);
        }

        if (isset($filtros['precio_max'])) {
            $query->where('precio', '<=', $filtros['precio_max']);
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $query->get();
    }

    public function existeConNombre(string $nombre): bool
    {
        return $this->modelo->where('nombre', $nombre)->exists();
    }

    public function contarProductosDelAño(int $año = null): int
    {
        $año = $año ?? date('Y');

        return $this->modelo
            ->whereYear('created_at', $año)
            ->count();
    }
}


### ❌ EJEMPLO INCORRECTO:


class ProductoRepository
{
    public function crear(array $datos): Producto
    {
        // MALO: Validación de negocio en repositorio
        if ($datos['precio'] <= 0) {
            throw new Exception('Precio inválido');
        }

        // MALO: Lógica de negocio en repositorio
        $datos['precio_con_impuestos'] = $datos['precio'] * 1.21;

        // MALO: Dependencia a servicios externos
        $notificacionService = new NotificacionService();
        $producto = Producto::create($datos);
        $notificacionService->enviarNotificacion($producto);

        return $producto;
    }
}


---

## 6. Inyección de Dependencias

### En Laravel: Inyección en Métodos vs Constructor

**USAR inyección en métodos para:**
- Controladores (siempre)
- Cuando solo algunos métodos necesitan dependencias
- Para mantener controladores ligeros

**USAR inyección en constructor para:**
- Servicios y repositorios
- Cuando todas las funciones necesitan las dependencias
- Jobs, Events, Listeners

### Configuración en el Service Provider:


// AppServiceProvider.php
public function register(): void
{
    // Binding de interfaces a implementaciones
    $this->app->bind(ProductoRepositoryInterface::class, ProductoRepository::class);
    $this->app->bind(NotificacionServiceInterface::class, EmailNotificacionService::class);

    // Singletons para servicios pesados
    $this->app->singleton(CacheService::class, function ($app) {
        return new CacheService($app->make('redis'));
    });
}


### ✅ CORRECTO - Controladores (Inyección en método):


class ProductoController extends Controller
{
    // NO usar constructor para controladores

    public function store(CreateProductoRequest $request, ProductoService $productoService): JsonResponse
    {
        $producto = $productoService->crear($request->validated());
        return response()->json(['data' => $producto], 201);
    }

    public function index(Request $request, ProductoService $productoService): JsonResponse
    {
        $productos = $productoService->obtenerTodos();
        return response()->json(['data' => $productos]);
    }
}


### ✅ CORRECTO - Servicios (Inyección en constructor):


class ProductoService
{
    public function __construct(
        private ProductoRepositoryInterface $productoRepository,
        private NotificacionServiceInterface $notificacionService,
        private CacheService $cacheService
    ) {}

    public function crear(array $datos): Producto
    {
        // Usar las dependencias inyectadas
        $producto = $this->productoRepository->crear($datos);
        $this->notificacionService->notificarNuevoProducto($producto);

        return $producto;
    }
}


---

## 7. Manejo de Requests

### Estructura de Request personalizado:


class CreateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('crear_productos');
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', 'unique:productos'],
            'precio' => ['required', 'numeric', 'min:0.01'],
            'categoria_id' => ['required', 'exists:categorias,id'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'stock_inicial' => ['required', 'integer', 'min:0'],
            'imagen' => ['nullable', 'image', 'max:2048']
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del producto es obligatorio',
            'precio.min' => 'El precio debe ser mayor a 0',
            'imagen.max' => 'La imagen no puede ser mayor a 2MB'
        ];
    }

    public function prepareForValidation(): void
    {
        // Limpieza de datos antes de validar
        $this->merge([
            'nombre' => trim($this->nombre),
            'precio' => (float) str_replace(',', '.', $this->precio)
        ]);
    }
}


---

## 8. Validaciones

### EVITA validaciones con IF que envuelven todo:

### ❌ INCORRECTO:

public function crear(array $datos): Producto
{
    if (
        isset($datos['nombre']) &&
        !empty($datos['nombre']) &&
        strlen($datos['nombre']) <= 255 &&
        isset($datos['precio']) &&
        is_numeric($datos['precio']) &&
        $datos['precio'] > 0
    ) {
        // 50 líneas de código aquí...
        return $producto;
    } else {
        throw new ValidationException('Datos inválidos');
    }
}


### ✅ CORRECTO:

public function crear(array $datosValidados): Producto
{
    // Los datos ya vienen validados desde el CreateProductoRequest
    $this->validarReglasDenegocio($datosValidados);

    return $this->procesarCreacion($datosValidados);
}

private function validarReglasDenegocio(array $datos): void
{
    if ($this->productoRepository->existeConNombre($datos['nombre'])) {
        throw new DomainException('Producto ya existe con ese nombre');
    }

    if (!$this->categoriaRepository->estaActiva($datos['categoria_id'])) {
        throw new DomainException('La categoría no está activa');
    }
}

private function procesarCreacion(array $datos): Producto
{
    $datos['precio_con_impuestos'] = $this->calcularPrecioConImpuestos($datos['precio']);
    $datos['codigo'] = $this->generarCodigoUnico();

    $producto = $this->productoRepository->crear($datos);

    $this->inventarioService->registrarStock($producto->id, $datos['stock_inicial']);
    $this->notificacionService->notificarNuevoProducto($producto);

    return $producto;
}


---

## 9. Manejo de Condicionales

### Evita el exceso de IFs anidados:

### ❌ INCORRECTO:

public function procesarPedido(Pedido $pedido): void
{
    if ($pedido->estado === 'pendiente') {
        if ($pedido->cliente->tipo === 'premium') {
            if ($pedido->total > 1000) {
                if ($this->inventario->hayStock($pedido)) {
                    if ($this->cliente->tieneCredito($pedido->total)) {
                        // Procesar pedido premium
                    } else {
                        throw new Exception('Sin crédito');
                    }
                } else {
                    throw new Exception('Sin stock');
                }
            } else {
                // Procesar pedido normal
            }
        } else {
            // Cliente regular
        }
    } else {
        throw new Exception('Estado inválido');
    }
}


### ✅ CORRECTO:

public function procesarPedido(Pedido $pedido): void
{
    $this->validarEstadoPedido($pedido);

    $tipoProcesamiento = $this->determinarTipoProcesamiento($pedido);

    match ($tipoProcesamiento) {
        'premium_alto_valor' => $this->procesarPedidoPremiumAltoValor($pedido),
        'premium_normal' => $this->procesarPedidoPremiumNormal($pedido),
        'regular' => $this->procesarPedidoRegular($pedido),
        default => throw new DomainException('Tipo de procesamiento no válido')
    };
}

private function validarEstadoPedido(Pedido $pedido): void
{
    if ($pedido->estado !== 'pendiente') {
        throw new DomainException('El pedido no está en estado pendiente');
    }
}

private function determinarTipoProcesamiento(Pedido $pedido): string
{
    if ($pedido->cliente->tipo !== 'premium') {
        return 'regular';
    }

    return $pedido->total > 1000 ? 'premium_alto_valor' : 'premium_normal';
}

private function procesarPedidoPremiumAltoValor(Pedido $pedido): void
{
    $this->validarInventarioYCredito($pedido);
    // Lógica específica para premium alto valor
}


---

## 10. Definición de Variables

### Reglas para nombres de variables:


// ✅ CORRECTO: Descriptivo y claro
$precioConImpuestos = $precio * 1.21;
$usuariosActivos = User::where('estado', 'activo')->get();
$fechaVencimientoFactura = now()->addDays(30);

// ❌ INCORRECTO: Abreviado y confuso
$pci = $p * 1.21;
$ua = User::where('estado', 'activo')->get();
$fvf = now()->addDays(30);

// ✅ CORRECTO: Booleanos con prefijos is/has/can
$esUsuarioAdmin = $usuario->rol === 'admin';
$tienePermisoLectura = $usuario->can('leer_documentos');
$puedeEliminar = $this->verificarPermisos($usuario, 'eliminar');

// ✅ CORRECTO: Arrays y colecciones en plural
$productos = Product::all();
$categorias = Category::active()->get();
$erroresValidacion = $validator->errors();

// ✅ CORRECTO: Constantes en mayúsculas
const PRECIO_ENVIO_GRATUITO = 5000;
const ESTADO_ACTIVO = 'activo';
const TIEMPO_EXPIRACION_TOKEN = 3600;


### Scope de variables:


class ProductoService
{
    // ✅ CORRECTO: Propiedades privadas para dependencias
    private ProductoRepository $productoRepository;
    private CalculadoraPrecios $calculadora;

    public function calcularPrecioFinal(Producto $producto): float
    {
        // ✅ CORRECTO: Variables locales descriptivas
        $precioBase = $producto->precio;
        $descuentoAplicable = $this->calcularDescuento($producto);
        $impuestosCalculados = $this->calculadora->calcularImpuestos($precioBase);

        return $precioBase - $descuentoAplicable + $impuestosCalculados;
    }
}


---

## 11. Manejo de Retornos

### Cuándo retornar y cuándo no:

### ✅ MÉTODOS QUE DEBEN RETORNAR:

// Consultas
public function obtenerProductoPorId(int $id): ?Producto
{
    return $this->productoRepository->find($id);
}

// Cálculos
public function calcularTotal(array $items): float
{
    return array_sum(array_column($items, 'subtotal'));
}

// Transformaciones
public function convertirAArray(Producto $producto): array
{
    return [
        'id' => $producto->id,
        'nombre' => $producto->nombre,
        'precio_formateado' => number_format($producto->precio, 2)
    ];
}

// Validaciones (boolean)
public function esProductoValido(array $datos): bool
{
    return isset($datos['nombre']) &&
           isset($datos['precio']) &&
           $datos['precio'] > 0;
}


### ✅ MÉTODOS QUE NO NECESITAN RETORNAR:

// Acciones (void)
public function eliminarProducto(int $id): void
{
    $this->productoRepository->delete($id);
    $this->cacheService->limpiarCache("producto_{$id}");
}

// Notificaciones
public function notificarStockBajo(Producto $producto): void
{
    $this->emailService->enviar([
        'to' => 'admin@empresa.com',
        'subject' => "Stock bajo: {$producto->nombre}",
        'mensaje' => "El producto {$producto->nombre} tiene stock bajo"
    ]);
}

// Actualizaciones sin respuesta necesaria
public function actualizarUltimaConexion(Usuario $usuario): void
{
    $usuario->update(['ultima_conexion' => now()]);
}


### Manejo de errores en retornos:


// ✅ CORRECTO: Manejo explícito de errores
public function obtenerProducto(int $id): Producto
{
    $producto = $this->productoRepository->find($id);

    if (!$producto) {
        throw new ModelNotFoundException('Producto no encontrado');
    }

    return $producto;
}

// ✅ CORRECTO: Retorno opcional cuando es válido
public function buscarProductoPorCodigo(string $codigo): ?Producto
{
    return $this->productoRepository->findByCodigo($codigo);
}

// ✅ CORRECTO: Result objects para operaciones complejas
public function crearProducto(array $datos): ProductoResult
{
    try {
        $producto = $this->productoRepository->crear($datos);

        return new ProductoResult(
            success: true,
            producto: $producto,
            mensaje: 'Producto creado exitosamente'
        );

    } catch (ValidationException $e) {
        return new ProductoResult(
            success: false,
            errores: $e->errors(),
            mensaje: 'Error de validación'
        );
    }
}


---

## 12. Separación de Funciones

### Principio de Responsabilidad Única:

### ❌ FUNCIÓN QUE HACE DEMASIADO:

public function procesarPedido(array $datosPedido): array
{
    // Validar datos
    if (empty($datosPedido['cliente_id'])) {
        throw new Exception('Cliente requerido');
    }

    // Calcular total
    $total = 0;
    foreach ($datosPedido['items'] as $item) {
        $producto = Product::find($item['producto_id']);
        $subtotal = $producto->precio * $item['cantidad'];
        $total += $subtotal;
    }

    // Aplicar descuentos
    $cliente = Client::find($datosPedido['cliente_id']);
    if ($cliente->tipo === 'premium') {
        $total = $total * 0.9;
    }

    // Verificar stock
    foreach ($datosPedido['items'] as $item) {
        $producto = Product::find($item['producto_id']);
        if ($producto->stock < $item['cantidad']) {
            throw new Exception('Stock insuficiente');
        }
    }

    // Crear pedido
    $pedido = Pedido::create([
        'cliente_id' => $datosPedido['cliente_id'],
        'total' => $total,
        'estado' => 'pendiente'
    ]);

    // Enviar email
    Mail::send('emails.pedido', ['pedido' => $pedido], function($message) {
        $message->to('admin@empresa.com')->subject('Nuevo pedido');
    });

    return ['pedido' => $pedido, 'total' => $total];
}


### ✅ FUNCIONES SEPARADAS CORRECTAMENTE:

public function procesarPedido(array $datosPedido): Pedido
{
    $datosValidados = $this->validarDatosPedido($datosPedido);
    $itemsProcesados = $this->procesarItemsPedido($datosValidados['items']);
    $totalCalculado = $this->calcularTotalPedido($itemsProcesados, $datosValidados['cliente_id']);

    $this->verificarDisponibilidadStock($itemsProcesados);

    $pedido = $this->crearPedido($datosValidados, $totalCalculado);
    $this->notificarNuevoPedido($pedido);

    return $pedido;
}

private function validarDatosPedido(array $datos): array
{
    $validator = Validator::make($datos, [
        'cliente_id' => 'required|exists:clientes,id',
        'items' => 'required|array|min:1',
        'items.*.producto_id' => 'required|exists:productos,id',
        'items.*.cantidad' => 'required|integer|min:1'
    ]);

    if ($validator->fails()) {
        throw new ValidationException($validator);
    }

    return $validator->validated();
}

private function procesarItemsPedido(array $items): array
{
    return collect($items)->map(function ($item) {
        $producto = $this->productoRepository->find($item['producto_id']);

        return [
            'producto_id' => $producto->id,
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $producto->precio,
            'subtotal' => $producto->precio * $item['cantidad']
        ];
    })->toArray();
}

private function calcularTotalPedido(array $items, int $clienteId): float
{
    $subtotal = collect($items)->sum('subtotal');
    $descuento = $this->calcularDescuentoCliente($clienteId, $subtotal);

    return $subtotal - $descuento;
}

private function calcularDescuentoCliente(int $clienteId, float $subtotal): float
{
    $cliente = $this->clienteRepository->find($clienteId);

    return match ($cliente->tipo) {
        'premium' => $subtotal * 0.1,
        'vip' => $subtotal * 0.15,
        default => 0
    };
}

private function verificarDisponibilidadStock(array $items): void
{
    foreach ($items as $item) {
        $stockDisponible = $this->inventarioService->obtenerStock($item['producto_id']);

        if ($stockDisponible < $item['cantidad']) {
            throw new StockInsuficienteException(
                "Stock insuficiente para producto {$item['producto_id']}"
            );
        }
    }
}

private function crearPedido(array $datos, float $total): Pedido
{
    return $this->pedidoRepository->crear([
        'cliente_id' => $datos['cliente_id'],
        'total' => $total,
        'estado' => 'pendiente',
        'items' => $datos['items']
    ]);
}

private function notificarNuevoPedido(Pedido $pedido): void
{
    $this->notificacionService->enviarNotificacionPedido($pedido);
}


---

## Reglas de Oro

### 1. **Una función, una responsabilidad**
Si puedes describir lo que hace tu función con "y", probablemente debas dividirla.

### 2. **Máximo 20 líneas por función**
Si tu función es más larga, divídela en funciones más pequeñas.

### 3. **Nombres descriptivos**
El nombre de la función debe decir exactamente qué hace.

### 4. **Evita más de 3 parámetros**
Si necesitas más, considera un objeto o array de configuración.

### 5. **Return early**
Valida condiciones al inicio y retorna/lanza excepciones temprano.

### 6. **Transacciones para múltiples entidades**
Si tu operación afecta más de una entidad, usa transacciones en el controlador.

### 7. **Excepciones arriba, manejo abajo**
Los servicios lanzan excepciones, los controladores las manejan.

### 8. **ENUMs sobre strings**
Usa ENUMs en lugar de strings hardcodeados para valores constantes.

### 9. **Operaciones simples en controladores, complejas en servicios**
CRUD simple va en controladores, lógica de negocio va en servicios.


// ✅ CORRECTO: Return early
public function procesarPago(Pedido $pedido): bool
{
    if ($pedido->estado !== 'pendiente') {
        return false;
    }

    if ($pedido->total <= 0) {
        return false;
    }

    // Lógica principal aquí
    return $this->procesamientoPago->procesar($pedido);
}

// ❌ INCORRECTO: Anidamiento profundo
public function procesarPago(Pedido $pedido): bool
{
    if ($pedido->estado === 'pendiente') {
        if ($pedido->total > 0) {
            // Lógica principal aquí
            return $this->procesamientoPago->procesar($pedido);
        } else {
            return false;
        }
    } else {
        return false;
    }
}


---

## Checklist de Revisión de Código

Antes de hacer commit, verifica:

- [ ] ¿Los controladores solo orquestan?
- [ ] ¿Los controladores usan transacciones para múltiples entidades?
- [ ] ¿`DB::beginTransaction()` está FUERA del try-catch?
- [ ] ¿Los controladores usan `internalErrorResponse()` para manejar excepciones?
- [ ] ¿Solo hay un catch por método en controladores?
- [ ] ¿Se usan ENUMs en lugar de strings hardcodeados?
- [ ] ¿Las operaciones simples están en controladores y complejas en servicios?
- [ ] ¿La lógica de negocio está en servicios?
- [ ] ¿Los servicios lanzan excepciones sin manejarlas?
- [ ] ¿Los repositorios solo acceden a datos?
- [ ] ¿Las funciones tienen una sola responsabilidad?
- [ ] ¿Los nombres son descriptivos?
- [ ] ¿Hay máximo 3 niveles de indentación?
- [ ] ¿Las validaciones están separadas de la lógica?
- [ ] ¿Se usan las dependencias correctamente?
- [ ] ¿No hay try-catch anidados en servicios?
- [ ] ¿Las transacciones cubren operaciones completas?
- [ ] ¿El código es fácil de leer y entender?
- [ ] ¿Se pueden escribir tests fácilmente?

---

Este manual debe ser la base para mantener un código limpio, mantenible y escalable en todos los proyectos del equipo.

---

## 💡 Reflexión Final

**"No escribes código para ti, lo escribes para el desarrollador que vendrá después de ti."**

Cada línea de código que escribes será leída, modificada y mantenida por otros. Tu responsabilidad como desarrollador va más allá de hacer que funcione: debes hacer que sea **comprensible**, **mantenible** y **escalable**.

Cuando sigues estas reglas de arquitectura, no solo estás creando software que funciona hoy, estás construyendo una base sólida para el futuro del proyecto y facilitando la vida de todo el equipo.

**El código limpio es un acto de consideración hacia tus compañeros de equipo y hacia tu futuro yo.**

---

*Versión del manual: 1.0*
*Última actualización: Noviembre 2025*
