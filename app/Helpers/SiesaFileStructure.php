<?php

namespace App\Helpers;

class SiesaFileStructure
{
    // 1) Orden de compra del cliente
    const ORDEN_COMPRA_START = 1;
    const ORDEN_COMPRA_END = 10;
    const ORDEN_COMPRA_LENGTH = 10;

    // 2) Tipo de identificación del cliente
    const TIPO_CLIENTE_START = 11;
    const TIPO_CLIENTE_END = 11;
    const TIPO_CLIENTE_LENGTH = 1;

    // 3) Código EAN de equivalencias EDI
    const CODIGO_EAN_START = 12;
    const CODIGO_EAN_END = 31;
    const CODIGO_EAN_LENGTH = 20;

    // 4) Código del cliente
    const CODIGO_CLIENTE_START = 32;
    const CODIGO_CLIENTE_END = 44;
    const CODIGO_CLIENTE_LENGTH = 13;

    // 5) Sucursal del cliente
    const SUCURSAL_START = 45;
    const SUCURSAL_END = 46;
    const SUCURSAL_LENGTH = 2;

    // 6) Fecha del pedido (AAAAMMDD)
    const FECHA_PEDIDO_START = 47;
    const FECHA_PEDIDO_END = 54;
    const FECHA_PEDIDO_LENGTH = 8;

    // 7) Centro de operación / Bodega
    const BODEGA_START = 55;
    const BODEGA_END = 57;
    const BODEGA_LENGTH = 3;

    // 8) Localización de la transacción
    const LOCALIZACION_START = 58;
    const LOCALIZACION_END = 59;
    const LOCALIZACION_LENGTH = 2;

    // 9) Tipo de búsqueda del ítem
    const TIPO_ITEM_START = 60;
    const TIPO_ITEM_END = 60;
    const TIPO_ITEM_LENGTH = 1;

    // 10) Código de barras del ítem
    const CODIGO_BARRAS_START = 61;
    const CODIGO_BARRAS_END = 75;
    const CODIGO_BARRAS_LENGTH = 15;

    // 11) Código del ítem / Referencia
    const CODIGO_ITEM_START = 76;
    const CODIGO_ITEM_END = 90;
    const CODIGO_ITEM_LENGTH = 15;

    // 12) Extensión del ítem
    const EXTENSION_ITEM_START = 91;
    const EXTENSION_ITEM_END = 93;
    const EXTENSION_ITEM_LENGTH = 3;

    // 13) Fecha de entrega del ítem (AAAAMMDD)
    const FECHA_ENTREGA_START = 94;
    const FECHA_ENTREGA_END = 101;
    const FECHA_ENTREGA_LENGTH = 8;

    // 14) Unidad de captura
    const UNIDAD_CAPTURA_START = 102;
    const UNIDAD_CAPTURA_END = 104;
    const UNIDAD_CAPTURA_LENGTH = 3;

    // 15) Cantidad de la transacción
    const CANTIDAD_START = 105;
    const CANTIDAD_END = 117;
    const CANTIDAD_LENGTH = 13;

    // 16) Cantidad en unidad inventario 2
    const CANTIDAD_UNIDAD2_START = 118;
    const CANTIDAD_UNIDAD2_END = 130;
    const CANTIDAD_UNIDAD2_LENGTH = 13;

    // 17) Unidad del precio
    const UNIDAD_PRECIO_START = 131;
    const UNIDAD_PRECIO_END = 131;
    const UNIDAD_PRECIO_LENGTH = 1;

    // 18) Lista de precio
    const LISTA_PRECIO_START = 132;
    const LISTA_PRECIO_END = 134;
    const LISTA_PRECIO_LENGTH = 3;

    // 19) Lista de descuento por línea
    const LISTA_DESCUENTO_START = 135;
    const LISTA_DESCUENTO_END = 136;
    const LISTA_DESCUENTO_LENGTH = 2;

    // 20) Precio unitario
    const PRECIO_UNITARIO_START = 137;
    const PRECIO_UNITARIO_END = 148;
    const PRECIO_UNITARIO_LENGTH = 12;

    // 21) Porcentaje descuento línea 1
    const DESCUENTO1_START = 149;
    const DESCUENTO1_END = 152;
    const DESCUENTO1_LENGTH = 4;

    // 22) Porcentaje descuento línea 2
    const DESCUENTO2_START = 153;
    const DESCUENTO2_END = 156;
    const DESCUENTO2_LENGTH = 4;

    // 23) Detalle del movimiento del pedido
    const DETALLE_MOVIMIENTO_START = 157;
    const DETALLE_MOVIMIENTO_END = 176;
    const DETALLE_MOVIMIENTO_LENGTH = 20;

    // 24) Descripción del ítem
    const DESCRIPCION_ITEM_START = 177;
    const DESCRIPCION_ITEM_END = 216;
    const DESCRIPCION_ITEM_LENGTH = 40;

    // 25) Código del punto de envío
    const PUNTO_ENVIO_START = 217;
    const PUNTO_ENVIO_END = 220;
    const PUNTO_ENVIO_LENGTH = 4;

    // 26) Observación 1 del pedido
    const OBSERVACION1_START = 221;
    const OBSERVACION1_END = 280;
    const OBSERVACION1_LENGTH = 60;

    // 27) Observación 2 del pedido
    const OBSERVACION2_START = 281;
    const OBSERVACION2_END = 340;
    const OBSERVACION2_LENGTH = 60;

    // 28) 1ra Línea descripción variable
    const DESC_VARIABLE1_START = 341;
    const DESC_VARIABLE1_END = 380;
    const DESC_VARIABLE1_LENGTH = 40;

    // 29) 2da Línea descripción variable
    const DESC_VARIABLE2_START = 381;
    const DESC_VARIABLE2_END = 420;
    const DESC_VARIABLE2_LENGTH = 40;

    // 30) 3ra Línea descripción variable
    const DESC_VARIABLE3_START = 421;
    const DESC_VARIABLE3_END = 460;
    const DESC_VARIABLE3_LENGTH = 40;

    // 31) 4ta Línea descripción variable
    const DESC_VARIABLE4_START = 461;
    const DESC_VARIABLE4_END = 500;
    const DESC_VARIABLE4_LENGTH = 40;

    // 32) Código documento vendedor
    const CODIGO_VENDEDOR_START = 501;
    const CODIGO_VENDEDOR_END = 513;
    const CODIGO_VENDEDOR_LENGTH = 13;

    // 33) Motivo del movimiento
    const MOTIVO_START = 514;
    const MOTIVO_END = 515;
    const MOTIVO_LENGTH = 2;

    // 34) Centro de costo
    const CENTRO_COSTO_START = 516;
    const CENTRO_COSTO_END = 523;
    const CENTRO_COSTO_LENGTH = 8;

    // 35) Proyecto
    const PROYECTO_START = 524;
    const PROYECTO_END = 533;
    const PROYECTO_LENGTH = 10;

    // 36) Condición de pago
    const CONDICION_PAGO_START = 534;
    const CONDICION_PAGO_END = 535;
    const CONDICION_PAGO_LENGTH = 2;

    // 37) Documento alterno
    const DOCUMENTO_ALTERNO_START = 536;
    const DOCUMENTO_ALTERNO_END = 543;
    const DOCUMENTO_ALTERNO_LENGTH = 8;

    // Longitud total de la línea
    const LINE_LENGTH = 543;

    /**
     * Remueve tildes y acentos convirtiendo a ASCII para compatibilidad con SIESA
     *
     * @param string $text Texto con posibles tildes
     * @return string Texto en ASCII sin tildes
     */
    public static function removeAccents(string $text): string
    {
        $unwanted_array = [
            'Á' => 'A',
            'á' => 'a',
            'À' => 'A',
            'à' => 'a',
            'Â' => 'A',
            'â' => 'a',
            'Ä' => 'A',
            'ä' => 'a',
            'É' => 'E',
            'é' => 'e',
            'È' => 'E',
            'è' => 'e',
            'Ê' => 'E',
            'ê' => 'e',
            'Ë' => 'E',
            'ë' => 'e',
            'Í' => 'I',
            'í' => 'i',
            'Ì' => 'I',
            'ì' => 'i',
            'Î' => 'I',
            'î' => 'i',
            'Ï' => 'I',
            'ï' => 'i',
            'Ó' => 'O',
            'ó' => 'o',
            'Ò' => 'O',
            'ò' => 'o',
            'Ô' => 'O',
            'ô' => 'o',
            'Ö' => 'O',
            'ö' => 'o',
            'Ú' => 'U',
            'ú' => 'u',
            'Ù' => 'U',
            'ù' => 'u',
            'Û' => 'U',
            'û' => 'u',
            'Ü' => 'U',
            'ü' => 'u',
            'Ñ' => 'N',
            'ñ' => 'n',
        ];

        return strtr($text, $unwanted_array);
    }

    /**
     * Rellena un string con caracteres a la derecha hasta alcanzar la longitud especificada
     * Trabaja con ASCII (sin tildes) para compatibilidad con SIESA
     * NO trunca el texto si es más largo
     *
     * @param string $value Valor a rellenar
     * @param int $length Longitud final deseada
     * @param string $char Carácter de relleno (por defecto espacio)
     * @return string
     */
    public static function padRight(string $value, int $length, string $char = ' '): string
    {
        return str_pad($value, $length, $char, STR_PAD_RIGHT);
    }

    /**
     * Rellena un string con caracteres a la izquierda hasta alcanzar la longitud especificada
     * Trabaja con ASCII (sin tildes) para compatibilidad con SIESA
     * NO trunca el texto si es más largo
     *
     * @param string $value Valor a rellenar
     * @param int $length Longitud final deseada
     * @param string $char Carácter de relleno (por defecto cero)
     * @return string
     */
    public static function padLeft(string $value, int $length, string $char = '0'): string
    {
        return str_pad($value, $length, $char, STR_PAD_LEFT);
    }

    /**
     * Convierte una fecha ISO 8601 a formato AAAAMMDD
     *
     * @param string $date Fecha en formato ISO 8601
     * @return string Fecha en formato AAAAMMDD
     */
    public static function formatDate(string $date): string
    {
        $datetime = new \DateTime($date);
        return $datetime->format('Ymd');
    }

    /**
     * Formatea una cantidad a 13 caracteres: 9 enteros + 3 decimales + signo
     * Ejemplo: 4 -> "000000004000+"
     *
     * @param int $quantity Cantidad en unidades enteras
     * @return string
     */
    public static function formatQuantity(int $quantity): string
    {
        $quantityWithDecimals = $quantity * 1000;
        $quantityStr = self::padLeft((string)abs($quantityWithDecimals), 12, '0');
        $sign = $quantity >= 0 ? '+' : '-';

        return $quantityStr . $sign;
    }

    /**
     * Formatea un precio a 12 caracteres: 9 enteros + 2 decimales + signo
     * Ejemplo: "48000.00" -> "000004800000+"
     *
     * @param string|float $price Precio con decimales
     * @return string
     */
    public static function formatPrice($price): string
    {
        $priceFloat = floatval($price);
        $priceWithDecimals = intval($priceFloat * 100);
        $priceStr = self::padLeft((string)abs($priceWithDecimals), 11, '0');
        $sign = $priceWithDecimals >= 0 ? '+' : '-';

        return $priceStr . $sign;
    }

    /**
     * Formatea un porcentaje a 4 dígitos: 2 enteros + 2 decimales
     * Ejemplo: 15.5 -> "1550"
     *
     * @param float $percentage Porcentaje
     * @return string
     */
    public static function formatPercentage(float $percentage): string
    {
        $percentageWithDecimals = intval($percentage * 100);
        return self::padLeft((string)$percentageWithDecimals, 4, '0');
    }

    /**
     * Construye la observación 1 con los datos del shipping address
     *
     * @param array $shippingAddress Array con los datos de envío de Shopify
     * @return string
     */
    public static function formatObservation(array $shippingAddress): string
    {
        $parts = [];

        $nombres = trim(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? ''));
        if ($nombres) {
            $parts[] = "Nombres: {$nombres}";
        }

        $cedula = $shippingAddress['company'] ?? '';
        if ($cedula) {
            $parts[] = "Cédula: {$cedula}";
        }

        $direccion = trim(($shippingAddress['address1'] ?? '') . ' ' . ($shippingAddress['address2'] ?? ''));
        if ($direccion) {
            $parts[] = "Dirección: {$direccion}";
        }

        if (!empty($shippingAddress['city'])) {
            $parts[] = "Ciudad: {$shippingAddress['city']}";
        }

        if (!empty($shippingAddress['province'])) {
            $parts[] = "Departamento: {$shippingAddress['province']}";
        }

        if (!empty($shippingAddress['zip'])) {
            $parts[] = "Código postal: {$shippingAddress['zip']}";
        }

        if (!empty($shippingAddress['phone'])) {
            $parts[] = "Teléfono: {$shippingAddress['phone']}";
        }

        return implode(' - ', $parts);
    }

    /**
     * Distribuye los datos de envío en dos observaciones de 60 caracteres cada una
     * Formato compacto sin etiquetas redundantes para maximizar información
     * Convierte caracteres UTF-8 a ASCII para compatibilidad con SIESA
     *
     * @param array $shippingAddress Array con los datos de envío de Shopify
     * @return array ['observacion1' => string, 'observacion2' => string]
     */
    public static function formatObservations(array $shippingAddress): array
    {
        $partsObs1 = [];
        $partsObs2 = [];

        // Observación 1: Nombre - Cédula - Teléfono
        $nombres = trim(($shippingAddress['first_name'] ?? '') . ' ' . ($shippingAddress['last_name'] ?? ''));
        if ($nombres) {
            $partsObs1[] = self::removeAccents($nombres);
        }

        $cedula = $shippingAddress['company'] ?? '';
        if ($cedula) {
            $partsObs1[] = self::removeAccents($cedula);
        }

        if (!empty($shippingAddress['phone'])) {
            $partsObs1[] = self::removeAccents("Tel: {$shippingAddress['phone']}");
        }

        // Observación 2: Dirección - Ciudad, Departamento
        $direccion = trim(($shippingAddress['address1'] ?? '') . ' ' . ($shippingAddress['address2'] ?? ''));
        if ($direccion) {
            $partsObs2[] = self::removeAccents($direccion);
        }

        $locationParts = [];
        if (!empty($shippingAddress['city'])) {
            $locationParts[] = self::removeAccents($shippingAddress['city']);
        }

        if (!empty($shippingAddress['province'])) {
            $locationParts[] = self::removeAccents($shippingAddress['province']);
        }

        if (!empty($locationParts)) {
            $partsObs2[] = implode(', ', $locationParts);
        }

        // Construir observaciones distribuyendo de forma inteligente
        $observacion1 = implode(' - ', $partsObs1);
        $observacion2 = implode(' - ', $partsObs2);

        // Si obs1 se pasa de 60, mover el último elemento a obs2
        if (strlen($observacion1) > 60 && count($partsObs1) > 1) {
            $lastPart = array_pop($partsObs1);
            $observacion1 = implode(' - ', $partsObs1);
            array_unshift($partsObs2, $lastPart);
            $observacion2 = implode(' - ', $partsObs2);
        }

        // Truncar si aún se pasa
        if (strlen($observacion1) > 60) {
            $observacion1 = substr($observacion1, 0, 60);
        }
        if (strlen($observacion2) > 60) {
            $observacion2 = substr($observacion2, 0, 60);
        }

        return [
            'observacion1' => $observacion1,
            'observacion2' => $observacion2,
        ];
    }

    /**
     * Trunca un texto a la longitud especificada o lo rellena con espacios si es más corto
     * Trabaja con ASCII (sin tildes) para compatibilidad con SIESA
     *
     * @param string $text Texto a truncar/rellenar
     * @param int $maxLength Longitud máxima
     * @return string
     */
    public static function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength);
        }

        return self::padRight($text, $maxLength);
    }
}
