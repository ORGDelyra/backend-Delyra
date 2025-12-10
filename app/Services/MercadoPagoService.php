<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    private string $accessToken;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->accessToken = env('MERCADO_PAGO_ACCESS_TOKEN');
    }

    /**
     * Crear preferencia de pago en Mercado Pago
     */
    public function crearPreferencia(array $data): array
    {
        try {
            $preference = [
                'items' => $this->formatearItems($data['items']),
                'payer' => [
                    'name' => $data['cliente']['nombre'] ?? 'Cliente',
                    'email' => $data['cliente']['email'],
                    'phone' => [
                        'area_code' => '57',
                        'number' => $data['cliente']['telefono'] ?? ''
                    ]
                ],
                'back_urls' => [
                    'success' => env('APP_ENV') === 'production'
                        ? 'https://delyra.app/cliente/pedidos?payment=success'
                        : 'http://localhost:4200/cliente/pedidos?payment=success',
                    'failure' => env('APP_ENV') === 'production'
                        ? 'https://delyra.app/cliente/pedidos?payment=failure'
                        : 'http://localhost:4200/cliente/pedidos?payment=failure',
                    'pending' => env('APP_ENV') === 'production'
                        ? 'https://delyra.app/cliente/pedidos?payment=pending'
                        : 'http://localhost:4200/cliente/pedidos?payment=pending'
                ],
                'auto_return' => 'approved',
                'notification_url' => env('APP_ENV') === 'production'
                    ? 'https://backend-delyra-production.up.railway.app/api/mercado-pago/webhook'
                    : 'http://localhost:8000/api/mercado-pago/webhook',
                'external_reference' => 'PEDIDO-' . $data['pedidoId'],
                'currency_id' => 'COP',
                'metadata' => [
                    'pedido_id' => $data['pedidoId']
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/checkout/preferences', $preference);

            if (!$response->successful()) {
                Log::error('Mercado Pago - Error creando preferencia', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Error al crear preferencia en Mercado Pago');
            }

            $resultado = $response->json();

            Log::info('Mercado Pago - Preferencia creada', [
                'preference_id' => $resultado['id'],
                'pedido_id' => $data['pedidoId']
            ]);

            return [
                'id' => $resultado['id'],
                'init_point' => $resultado['init_point'] ?? null,
                'sandbox_init_point' => $resultado['sandbox_init_point'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Mercado Pago - Excepción al crear preferencia', [
                'mensaje' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verificar estado de pago
     */
    public function verificarPago(string $preferenceId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken
            ])->get($this->baseUrl . '/checkout/preferences/' . $preferenceId);

            if (!$response->successful()) {
                Log::error('Mercado Pago - Error verificando pago', [
                    'preference_id' => $preferenceId,
                    'status' => $response->status()
                ]);
                throw new \Exception('Error al verificar pago');
            }

            $preference = $response->json();

            return [
                'id' => $preference['id'],
                'estado' => $this->extraerEstadoPago($preference),
                'total' => $this->calcularTotal($preference['items'] ?? []),
                'datos_completos' => $preference
            ];

        } catch (\Exception $e) {
            Log::error('Mercado Pago - Excepción al verificar pago', [
                'mensaje' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Procesar notificación webhook
     */
    public function procesarWebhook(array $datos): array
    {
        try {
            // Validar que sea notificación de pago
            $type = $datos['type'] ?? null;
            $action = $datos['action'] ?? null;
            if (!$type || !in_array($type, ['payment', 'payment.created', 'payment.updated'])) {
                Log::warning('Mercado Pago - Webhook con tipo inválido', ['type' => $type]);
                return ['error' => 'Tipo de notificación inválido'];
            }

            $paymentId = $datos['data']['id']
                ?? $datos['data']['payment_id']
                ?? $datos['id']
                ?? $datos['payment_id']
                ?? null;
            if (!$paymentId && isset($datos['resource'])) {
                $paymentId = basename($datos['resource']);
            }
            if (!$paymentId) {
                Log::warning('Mercado Pago - Webhook sin payment ID', ['action' => $action]);
                return ['error' => 'Payment ID no encontrado'];
            }

            // Consultar detalles del pago
            $pagoDetalles = $this->obtenerDetallesPago($paymentId);

            Log::info('Mercado Pago - Webhook procesado', [
                'payment_id' => $paymentId,
                'estado' => $pagoDetalles['status'],
                'external_reference' => $pagoDetalles['external_reference'] ?? null
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'estado' => $pagoDetalles['status'],
                'external_reference' => $pagoDetalles['external_reference'] ?? null,
                'datos' => $pagoDetalles
            ];

        } catch (\Exception $e) {
            Log::error('Mercado Pago - Excepción al procesar webhook', [
                'mensaje' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Obtener detalles del pago por ID
     */
    public function obtenerDetallesPago(string $paymentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken
            ])->get($this->baseUrl . '/v1/payments/' . $paymentId);

            if (!$response->successful()) {
                Log::error('Mercado Pago - Error obteniendo detalles de pago', [
                    'payment_id' => $paymentId,
                    'status' => $response->status()
                ]);
                throw new \Exception('Error al obtener detalles del pago');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Mercado Pago - Excepción obteniendo detalles', [
                'mensaje' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Formatear items para Mercado Pago (todo en COP sin decimales)
     */
    private function formatearItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'description' => $item['description'] ?? $item['title'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (int) $item['unit_price'], // Enteros en COP
                'currency_id' => 'COP'
            ];
        }, $items);
    }

    /**
     * Extraer estado del pago desde preferencia
     */
    private function extraerEstadoPago(array $preference): string
    {
        if (!empty($preference['payments'])) {
            foreach ($preference['payments'] as $pago) {
                if ($pago['status'] === 'approved') {
                    return 'approved';
                }
            }
            foreach ($preference['payments'] as $pago) {
                if ($pago['status'] === 'rejected') {
                    return 'rejected';
                }
            }
            if (!empty($preference['payments']) && $preference['payments'][0]['status'] === 'pending') {
                return 'pending';
            }
        }
        return 'pending';
    }

    /**
     * Calcular el total de la preferencia sumando todos los items
     */
    private function calcularTotal(array $items): int
    {
        return (int) array_reduce($items, function ($carry, $item) {
            return $carry + ((int) $item['unit_price'] * (int) $item['quantity']);
        }, 0);
    }
}
