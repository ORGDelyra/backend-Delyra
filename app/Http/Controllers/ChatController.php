<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
    /**
     * Obtener chats recientes de soporte (no de pedido) tipo WhatsApp
     * GET /api/chat/soporte/recientes
     */
    public function getRecentChats()
    {
        $userId = Auth::id();

        // Obtener todos los mensajes de soporte donde el usuario es remitente o destinatario
        $mensajes = Message::whereNull('id_pedido')
            ->where(function($q) use ($userId) {
                $q->where('id_remitente', $userId)
                  ->orWhere('id_destinatario', $userId);
            })
            ->with(['remitente', 'destinatario'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar por el otro participante y tomar el último mensaje de cada chat
        $chats = $mensajes->groupBy(function($msg) use ($userId) {
            return $msg->id_remitente == $userId ? $msg->id_destinatario : $msg->id_remitente;
        })->map(function($msgs, $otherUserId) {
            $ultimo = $msgs->first();
            $otroUsuario = $ultimo->id_remitente == auth()->id() ? $ultimo->destinatario : $ultimo->remitente;
            return [
                'usuario' => [
                    'id' => $otroUsuario->id,
                    'nombre' => $otroUsuario->primer_nombre . ' ' . $otroUsuario->primer_apellido,
                    'correo' => $otroUsuario->correo,
                ],
                'ultimo_mensaje' => [
                    'contenido' => $ultimo->contenido,
                    'fecha' => $ultimo->created_at,
                    'remitente_id' => $ultimo->id_remitente,
                ]
            ];
        })->values();

        return response()->json($chats);
    }
{
    /**
     * Obtener mensajes de un pedido o de soporte
     * GET /api/chat/{id_pedido} (pedido) o /api/chat/soporte/{userId} (soporte)
     */
    public function getMessages($id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'mensaje' => 'Usuario no autenticado'
            ], 401);
        }
        $isSoporte = $request->routeIs('chat.soporte.get');
        if ($isSoporte) {
            // Chat de soporte: $id es el id del otro usuario (soporte o cliente)
            $mensajes = Message::whereNull('id_pedido')
                ->where(function($q) use ($user, $id) {
                    $q->where(function($q2) use ($user, $id) {
                        $q2->where('id_remitente', $user->id)->where('id_destinatario', $id);
                    })->orWhere(function($q2) use ($user, $id) {
                        $q2->where('id_remitente', $id)->where('id_destinatario', $user->id);
                    });
                })
                ->with('remitente', 'destinatario')
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            // Chat de pedido
            $pedido = Cart::find($id);
            if (!$pedido) {
                return response()->json([
                    'mensaje' => 'Pedido no encontrado'
                ], 404);
            }
            $esCliente = $pedido->id_usuario === $user->id;
            $esVendedor = in_array($user->id_rol, [2, 3]);
            $esDomiciliario = $pedido->id_domiciliario === $user->id;
            if (!($esCliente || $esVendedor || $esDomiciliario)) {
                return response()->json([
                    'mensaje' => 'No tienes permiso para ver este chat'
                ], 403);
            }
            $mensajes = Message::where('id_pedido', $id)
                ->with('remitente', 'destinatario')
                ->orderBy('created_at', 'asc')
                ->get();
        }
        $mensajesFormateados = $mensajes->map(function ($msg) {
            return [
                'id' => $msg->id,
                'id_remitente' => $msg->id_remitente,
                'id_destinatario' => $msg->id_destinatario,
                'usuario' => $msg->remitente->primer_nombre . ' ' . $msg->remitente->primer_apellido,
                'mensaje' => $msg->contenido,
                'imagen_url' => $msg->imagen_url,
                'tipo_imagen' => $msg->tipo_imagen,
                'timestamp' => $msg->created_at->toIso8601String()
            ];
        });
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'messages' => $mensajesFormateados,
                'total' => count($mensajesFormateados)
            ]
        ], 200);
    }

    /**
     * Enviar un mensaje en el chat del pedido o de soporte
     * POST /api/chat/{id}/enviar (pedido) o /api/chat/soporte/{userId}/enviar (soporte)
     */
    public function sendMessage(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'mensaje' => 'Usuario no autenticado'
            ], 401);
        }
        $isSoporte = $request->routeIs('chat.soporte.enviar');
        $data = $request->validate([
            'contenido' => 'required|string|max:1000',
            'imagen_url' => 'nullable|url',
            'tipo_imagen' => 'nullable|in:comprobante,producto,otro',
            'id_destinatario' => 'nullable|exists:users,id'
        ]);
        if ($isSoporte) {
            // Chat de soporte: $id es el id del otro usuario (soporte o cliente)
            if (!isset($data['id_destinatario']) || !$data['id_destinatario']) {
                $data['id_destinatario'] = $id;
            }
            try {
                $mensaje = Message::create([
                    'id_remitente' => $user->id,
                    'id_destinatario' => $data['id_destinatario'],
                    'id_pedido' => null,
                    'contenido' => $data['contenido'],
                    'imagen_url' => $data['imagen_url'] ?? null,
                    'tipo_imagen' => $data['tipo_imagen'] ?? 'otro',
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        [
                            'id' => $mensaje->id,
                            'id_remitente' => $user->id,
                            'id_destinatario' => $data['id_destinatario'],
                            'usuario' => $user->primer_nombre . ' ' . $user->primer_apellido,
                            'mensaje' => $data['contenido'],
                            'imagen_url' => $data['imagen_url'] ?? null,
                            'tipo_imagen' => $data['tipo_imagen'] ?? null,
                            'timestamp' => now()->toIso8601String(),
                            'almacenado' => true,
                            'nota' => null
                        ]
                    ]
                ], 201);
            } catch (\Exception $e) {
                Log::error('Error al enviar mensaje: ' . $e->getMessage());
                return response()->json([
                    'mensaje' => 'Error al enviar el mensaje',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            // Chat de pedido (igual que antes)
            $pedido = Cart::find($id);
            if (!$pedido) {
                return response()->json([
                    'mensaje' => 'Pedido no encontrado'
                ], 404);
            }
            $esCliente = $pedido->id_usuario === $user->id;
            $esVendedor = in_array($user->id_rol, [2, 3]);
            $esDomiciliario = $pedido->id_domiciliario === $user->id;
            if (!($esCliente || $esVendedor || $esDomiciliario)) {
                return response()->json([
                    'mensaje' => 'No tienes permiso para enviar mensajes en este chat'
                ], 403);
            }
            if (!$data['id_destinatario'] ?? null) {
                if ($esCliente) {
                    $product = $pedido->products()->first();
                    if ($product && $product->id_usuario) {
                        $data['id_destinatario'] = $product->id_usuario;
                    } else {
                        return response()->json([
                            'mensaje' => 'Error: No se puede determinar el vendedor'
                        ], 400);
                    }
                } elseif ($esVendedor) {
                    $data['id_destinatario'] = $pedido->id_usuario;
                } elseif ($esDomiciliario) {
                    $data['id_destinatario'] = $pedido->id_usuario;
                }
            }
            $tieneImagen = !empty($data['imagen_url'] ?? null);
            try {
                $mensaje = null;
                if ($tieneImagen) {
                    $mensaje = Message::create([
                        'id_remitente' => $user->id,
                        'id_destinatario' => $data['id_destinatario'],
                        'id_pedido' => $id,
                        'contenido' => $data['contenido'],
                        'imagen_url' => $data['imagen_url'],
                        'tipo_imagen' => $data['tipo_imagen'] ?? 'comprobante',
                    ]);
                }
                return response()->json([
                    'success' => true,
                    'data' => [
                        [
                            'id' => $mensaje?->id ?? null,
                            'id_remitente' => $user->id,
                            'id_destinatario' => $data['id_destinatario'],
                            'usuario' => $user->primer_nombre . ' ' . $user->primer_apellido,
                            'mensaje' => $data['contenido'],
                            'imagen_url' => $data['imagen_url'] ?? null,
                            'tipo_imagen' => $data['tipo_imagen'] ?? null,
                            'timestamp' => now()->toIso8601String(),
                            'almacenado' => $tieneImagen ? true : false,
                            'nota' => !$tieneImagen ? 'Mensaje temporal (no se guardó en BD). Solo se guardan comprobantes.' : null
                        ]
                    ]
                ], 201);
            } catch (\Exception $e) {
                Log::error('Error al enviar mensaje: ' . $e->getMessage());
                return response()->json([
                    'mensaje' => 'Error al enviar el mensaje',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    }

    /**
     * Obtener todas las conversaciones del usuario actual
     * GET /api/conversaciones
     *
     * Devuelve lista de pedidos con últimos mensajes
     */
    public function getConversations(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'mensaje' => 'Usuario no autenticado'
            ], 401);
        }

        // Obtener pedidos relevantes para el usuario
        $pedidosQuery = null;

        if (in_array($user->id_rol, [2, 3])) {
            // Vendedor: pedidos de sus productos
            $pedidosQuery = Cart::whereHas('products', function ($q) use ($user) {
                $q->where('id_usuario', $user->id);
            });
        } elseif ($user->id_rol === 4) {
            // Domiciliario: pedidos asignados a él
            $pedidosQuery = Cart::where('id_domiciliario', $user->id);
        } else {
            // Cliente: sus propios pedidos
            $pedidosQuery = Cart::where('id_usuario', $user->id);
        }

        $pedidos = $pedidosQuery
            ->with(['user', 'products', 'domiciliario'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $conversaciones = $pedidos->map(function ($pedido) {
            // Obtener último mensaje
            $ultimoMensaje = Message::where('id_pedido', $pedido->id)
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'id_pedido' => $pedido->id,
                'cliente' => [
                    'id' => $pedido->user->id,
                    'nombre' => $pedido->user->primer_nombre . ' ' . $pedido->user->primer_apellido
                ],
                'estado_pedido' => $pedido->estado_pedido,
                'tipo_entrega' => $pedido->tipo_entrega,
                'ultimo_mensaje' => $ultimoMensaje ? [
                    'contenido' => $ultimoMensaje->contenido,
                    'remitente' => $ultimoMensaje->remitente->primer_nombre . ' ' . $ultimoMensaje->remitente->primer_apellido,
                    'timestamp' => $ultimoMensaje->created_at->toIso8601String()
                ] : null,
                'created_at' => $pedido->created_at->toIso8601String()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'conversaciones' => $conversaciones,
                'total' => count($conversaciones)
            ]
        ], 200);
    }

    /**
     * Marcar mensaje como leído (futuro)
     * POST /api/chat/mensaje/{id}/leer
     */
    public function markAsRead($id_mensaje)
    {
        $user = Auth::user();
        $mensaje = Message::find($id_mensaje);

        if (!$mensaje) {
            return response()->json([
                'mensaje' => 'Mensaje no encontrado'
            ], 404);
        }

        // Solo el destinatario puede marcar como leído
        if ($mensaje->id_destinatario !== $user->id) {
            return response()->json([
                'mensaje' => 'No tienes permiso para marcar este mensaje'
            ], 403);
        }

        // Aquí podrías agregar un campo 'leído' a la tabla messages en el futuro
        return response()->json([
            'success' => true,
            'mensaje' => 'Mensaje marcado como leído'
        ], 200);
    }
}
