<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyShopifyWebhook
{
  /**
   * Verifica que el webhook venga realmente de Shopify validando la firma HMAC
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    $hmacHeader = $request->header('X-Shopify-Hmac-SHA256');

    if (!$hmacHeader) {
      Log::warning('Webhook sin header HMAC recibido', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
      ]);

      return response()->json([
        'error' => 'Unauthorized'
      ], 401);
    }

    $data = $request->getContent();
    $secret = config('shopify.webhook_secret');

    $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));

    if (!hash_equals($calculatedHmac, $hmacHeader)) {
      Log::warning('Webhook con firma HMAC inválida', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'hmac_recibido' => $hmacHeader,
      ]);

      return response()->json([
        'error' => 'Unauthorized'
      ], 401);
    }

    return $next($request);
  }
}
