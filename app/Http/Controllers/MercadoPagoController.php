<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MercadoPagoController extends Controller
{
    private MercadoPagoService $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * POST /api/mercado-pago/crear-preferencia
     * Crear preferencia de pago en Mercado Pago
     */
    public function crearPreferencia(Request $request)
    {
        try {
            $validado = $request->validate([
                'pedidoId' => 'required|integer',
                'items' => 'required|array',
                'items.*.id' => 'required|string',
                'items.*.title' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|integer|min:1',
                'total' => 'required|integer|min:1',
                'cliente.nombre' => 'required|string',
                'cliente.email' => 'required|email',
                'cliente.telefono' => 'required|string'
            ]);

            // Verificar que el pedido existe
            $carrito = Cart::find($validado['pedidoId']);
            if (!$carrito) {
                Log::warning('Mercado Pago - Pedido no encontrado', ['pedido_id' => $validado['pedidoId']]);
                return response()->json(['error' => 'Pedido no encontrado'], 404);
            }

            // Verificar que es propiedad del usuario logueado (solo para clientes)
            if (Auth::id() && Auth::user()->id_rol === 2 && $carrito->id_usuario !== Auth::id()) {
                Log::warning('Mercado Pago - Acceso denegado a pedido', [
                    'usuario_id' => Auth::id(),
                    'pedido_id' => $validado['pedidoId']
                ]);
                return response()->json(['error' => 'No tienes permiso para este pedido'], 403);
            }

            // Crear preferencia en Mercado Pago
            $preferencia = $this->mercadoPagoService->crearPreferencia($validado);

            // Actualizar el carrito con preference_id y método de pago
            $carrito->update([
                'mercado_pago_preference_id' => $preferencia['id'],
                'metodo_pago' => 'mercado_pago',
                'estado_pago' => 'pendiente'
            ]);

            Log::info('Mercado Pago - Preferencia creada y guardada', [
                'pedido_id' => $validado['pedidoId'],
                'preference_id' => $preferencia['id']
            ]);

            return response()->json([
                'mensaje' => 'Preferencia creada exitosamente',
                'preference_id' => $preferencia['id'],
                'init_point' => $preferencia['init_point'] ?? $preferencia['sandbox_init_point'],
                'pedido_id' => $carrito->id
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Mercado Pago - Error validación', ['errores' => $e->errors()]);
            return response()->json(['errores' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Mercado Pago - Error creando preferencia', ['mensaje' => $e->getMessage()]);
            return response()->json(['error' => 'Error al crear preferencia de pago'], 500);
        }
    }

    /**
     * GET /api/mercado-pago/verificar/{preference_id}
     * Verificar estado del pago
     */
    public function verificarPago($preferenceId)
    {
        try {
            // Buscar carrito por preference_id
            $carrito = Cart::where('mercado_pago_preference_id', $preferenceId)->first();

            if (!$carrito) {
                Log::warning('Mercado Pago - Preference ID no encontrado', ['preference_id' => $preferenceId]);
                return response()->json(['error' => 'Preferencia no encontrada'], 404);
            }

            // Verificar permiso
            if (Auth::id() && $carrito->id_usuario !== Auth::id()) {
                Log::warning('Mercado Pago - Acceso denegado a verificación', [
                    'usuario_id' => Auth::id(),
                    'pedido_id' => $carrito->id
                ]);
                return response()->json(['error' => 'No tienes permiso para verificar este pago'], 403);
            }

            // Obtener estado de Mercado Pago
            $estadoPago = $this->mercadoPagoService->verificarPago($preferenceId);

            return response()->json([
                'preference_id' => $preferenceId,
                'pedido_id' => $carrito->id,
                'estado_local' => $carrito->estado_pago,
                'estado_mercado_pago' => $estadoPago['estado'],
                'total' => $estadoPago['total'],
                'datos' => $estadoPago
            ], 200);

        } catch (\Exception $e) {
            Log::error('Mercado Pago - Error verificando pago', ['mensaje' => $e->getMessage()]);
            return response()->json(['error' => 'Error al verificar pago'], 500);
        }
    }

    /**
     * POST /api/mercado-pago/webhook
     * Recibir notificaciones de Mercado Pago
     */
    public function webhook(Request $request)
    {
        try {
            $datos = $request->all();

            Log::info('Mercado Pago - Webhook recibido', [
                'type' => $datos['type'] ?? null,
                'action' => $datos['action'] ?? null,
                'data' => $datos['data'] ?? null
            ]);

            // Procesar webhook
            $resultado = $this->mercadoPagoService->procesarWebhook($datos);

            if (isset($resultado['error'])) {
                Log::warning('Mercado Pago - Error procesando webhook', ['error' => $resultado['error']]);
                return response()->json($resultado, 400);
            }

            // Actualizar estado del pedido basado en el pago
            $externalReference = $resultado['external_reference'] ?? null;
            if ($externalReference && str_starts_with($externalReference, 'PEDIDO-')) {
                $pedidoId = (int) str_replace('PEDIDO-', '', $externalReference);
                $carrito = Cart::find($pedidoId);

                if ($carrito) {
                    $estadoPago = $resultado['estado'];
                    
                    if ($estadoPago === 'approved') {
                        $carrito->update([
                            'estado_pago' => 'confirmado',
                            'fecha_pago_confirmado' => now()
                        ]);
                        Log::info('Mercado Pago - Pago confirmado', ['pedido_id' => $pedidoId]);
                    } elseif ($estadoPago === 'rejected') {
                        $carrito->update(['estado_pago' => 'rechazado']);
                        Log::info('Mercado Pago - Pago rechazado', ['pedido_id' => $pedidoId]);
                    } else {
                        $carrito->update(['estado_pago' => 'pendiente']);
                        Log::info('Mercado Pago - Pago pendiente', ['pedido_id' => $pedidoId]);
                    }
                }
            }

            return response()->json([
                'mensaje' => 'Webhook procesado correctamente',
                'resultado' => $resultado
            ], 200);

        } catch (\Exception $e) {
            Log::error('Mercado Pago - Excepción en webhook', ['mensaje' => $e->getMessage()]);
            return response()->json(['error' => 'Error procesando webhook'], 500);
        }
    }
}
