<?php

namespace App\Services\Shopify;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShopifyApiClient
{
    private Client $client;
    private string $shopDomain;
    private string $accessToken;
    private string $apiVersion;
    private int $rateLimitDelay;
    private int $maxRetries;
    private ?string $clientId;
    private ?string $clientSecret;

    public function __construct()
    {
        $this->shopDomain = config('shopify.shop_domain');
        $this->apiVersion = config('shopify.api_version');
        $this->rateLimitDelay = config('shopify.rate_limit_delay', 500);
        $this->maxRetries = config('shopify.max_retries', 3);
        $this->clientId = config('shopify.client_id');
        $this->clientSecret = config('shopify.client_secret');

        // Obtener token del cache (se renueva automáticamente cada 24h)
        $this->accessToken = Cache::get('shopify_access_token', '');

        $this->client = new Client([
            'base_uri' => "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/",
            'timeout' => config('shopify.api_timeout', 30),
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Obtiene pedidos de Shopify filtrados por fecha
     *
     * @param string $dateFrom Fecha desde (Y-m-d H:i:s)
     * @param string $dateTo Fecha hasta (Y-m-d H:i:s)
     * @param int $limit Pedidos por página (máx 250)
     * @return array
     * @throws \Exception
     */
    public function getOrders(string $dateFrom, string $dateTo, int $limit = 250): array
    {
        $this->validateConfiguration();

        $orders = [];
        $pageInfo = null;
        $hasNextPage = true;

        while ($hasNextPage) {
            $queryParams = [
                'status' => 'any',
                'created_at_min' => $dateFrom,
                'created_at_max' => $dateTo,
                'limit' => min($limit, 250), // Shopify máximo 250
            ];

            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            try {
                $response = $this->makeRequest('GET', 'orders.json', $queryParams);

                if (isset($response['orders'])) {
                    $orders = array_merge($orders, $response['orders']);
                }

                // Verificar si hay más páginas
                $pageInfo = $this->getNextPageInfo($response);
                $hasNextPage = !is_null($pageInfo);

                // Rate limiting: pausar entre requests
                if ($hasNextPage) {
                    usleep($this->rateLimitDelay * 1000); // convertir ms a microsegundos
                }
            } catch (\Exception $e) {
                Log::error('Error obteniendo pedidos de Shopify', [
                    'error' => $e->getMessage(),
                    'date_range' => "{$dateFrom} - {$dateTo}",
                ]);
                throw $e;
            }
        }

        return $orders;
    }

    /**
     * Obtiene un pedido específico de Shopify por ID
     *
     * @param string $shopifyOrderId
     * @return array|null
     * @throws \Exception
     */
    public function getOrderById(string $shopifyOrderId): ?array
    {
        $this->validateConfiguration();

        try {
            $response = $this->makeRequest('GET', "orders/{$shopifyOrderId}.json", [
                'status' => 'any',
            ]);

            return $response['order'] ?? null;
        } catch (\Exception $e) {
            Log::error('Error obteniendo pedido de Shopify por ID', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Realiza una petición a la API con retry logic
     */
    private function makeRequest(string $method, string $endpoint, array $params = [], int $attempt = 1): array
    {
        try {
            $response = $this->client->request($method, $endpoint, [
                'query' => $params,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();

            // Rate limit exceeded (429) - retry después de esperar
            if ($statusCode === 429 && $attempt < $this->maxRetries) {
                $retryAfter = $this->getRetryAfter($e) ?? 2;
                sleep($retryAfter);
                return $this->makeRequest($method, $endpoint, $params, $attempt + 1);
            }

            // Otros errores temporales - retry
            if (in_array($statusCode, [500, 502, 503, 504]) && $attempt < $this->maxRetries) {
                sleep(pow(2, $attempt)); // exponential backoff
                return $this->makeRequest($method, $endpoint, $params, $attempt + 1);
            }

            throw new \Exception("Shopify API error: {$e->getMessage()}", $statusCode);
        }
    }

    /**
     * Extrae el page_info de los headers de paginación
     */
    private function getNextPageInfo($response): ?string
    {
        // Shopify usa cursor-based pagination en el header Link
        // Por ahora simplificamos: si hay menos de 250, no hay más páginas
        if (isset($response['orders']) && count($response['orders']) < 250) {
            return null;
        }

        // TODO: Implementar parsing del header Link si necesitamos paginación completa
        return null;
    }

    /**
     * Obtiene el tiempo de espera del header Retry-After
     */
    private function getRetryAfter(GuzzleException $e): ?int
    {
        // Extraer del header si está disponible
        return 2; // Default 2 segundos
    }

    /**
     * Valida que la configuración esté completa
     */
    private function validateConfiguration(): void
    {
        if (empty($this->shopDomain)) {
            throw new \Exception('SHOPIFY_SHOP_DOMAIN no está configurado en .env');
        }

        if (empty($this->apiVersion)) {
            throw new \Exception('SHOPIFY_API_VERSION no está configurado en .env');
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception('SHOPIFY_CLIENT_ID y SHOPIFY_CLIENT_SECRET son requeridos para renovación de token');
        }
    }

    /**
     * Prueba la conexión con Shopify API
     */
    public function testConnection(): bool
    {
        try {
            $this->validateConfiguration();

            $response = $this->client->request('GET', 'shop.json');
            $data = json_decode($response->getBody()->getContents(), true);

            return isset($data['shop']);
        } catch (\Exception $e) {
            Log::error('Error conectando con Shopify API', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Renueva el access token usando client credentials
     *
     * @return string El nuevo access token
     * @throws \Exception
     */
    public function refreshAccessToken(): string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception('SHOPIFY_CLIENT_ID y SHOPIFY_CLIENT_SECRET son requeridos para renovar el token');
        }

        try {
            $oauthClient = new Client([
                'base_uri' => "https://{$this->shopDomain}/",
                'timeout' => 30,
            ]);

            $response = $oauthClient->request('POST', 'admin/oauth/access_token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['access_token'])) {
                throw new \Exception('No se recibió access_token en la respuesta');
            }

            $newToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 86400; // 24 horas por defecto

            // Guardar en cache por 23 horas (para renovar antes de que expire)
            $cacheMinutes = floor(($expiresIn - 3600) / 60); // Restar 1 hora de margen
            Cache::put('shopify_access_token', $newToken, now()->addMinutes($cacheMinutes));

            // Actualizar el token en la instancia actual
            $this->accessToken = $newToken;

            // Recrear el cliente con el nuevo token
            $this->client = new Client([
                'base_uri' => "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/",
                'timeout' => config('shopify.api_timeout', 30),
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            Log::info('Token de Shopify renovado exitosamente', [
                'expires_in_hours' => round($expiresIn / 3600, 2),
            ]);

            return $newToken;
        } catch (GuzzleException $e) {
            Log::error('Error renovando token de Shopify', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("No se pudo renovar el token: {$e->getMessage()}");
        }
    }

    /**
     * Verifica si el token necesita renovación (está en cache o no)
     */
    public function needsTokenRefresh(): bool
    {
        return !Cache::has('shopify_access_token');
    }
}
