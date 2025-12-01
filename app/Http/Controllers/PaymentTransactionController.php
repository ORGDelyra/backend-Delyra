<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Cart;


class PaymentTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'id_carrito' => 'required|exists:carts,id',
            'monto' => 'required|numeric',
            'metodo_pago' => 'required|string',
            'comprobante_url' => 'nullable|url'
        ]);

        // Crear transacción
        $transaction = PaymentTransaction::create([
            'id_carrito' => $data['id_carrito'],
            'monto' => $data['monto'],
            'metodo_pago' => $data['metodo_pago'],
            'estado' => 'pendiente'
        ]);

        // Guardar comprobante si viene la url
        if (!empty($data['comprobante_url'])) {
            $transaction->images()->create([
                'url' => $data['comprobante_url'],
                'type' => 'comprobante',
                'descripcion' => 'Comprobante de pago enviado por el cliente'
            ]);
        }

        return response()->json(['mensaje' => 'Transacción creada', 'transaction' => $transaction->load('images')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentTransaction $paymentTransaction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentTransaction $paymentTransaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentTransaction $paymentTransaction)
    {
        // Permitir actualizar estado u otros campos si es necesario
        $data = $request->validate([
            'estado' => 'sometimes|string',
            'monto' => 'sometimes|numeric'
        ]);

        $paymentTransaction->update($data);

        return response()->json(['mensaje' => 'Transacción actualizada', 'transaction' => $paymentTransaction], 200);
    }

    /**
     * Confirmar una transacción (ej. vendedor confirma recepción del comprobante y marca como pagado)
     */
    public function confirm(Request $request, PaymentTransaction $paymentTransaction)
    {
        $user = Auth::user();

        // Verificar permisos: vendedores o administradores
        if (!in_array($user->id_rol, [1, 2, 3])) {
            return response()->json(['mensaje' => 'No tienes permisos para confirmar la transacción'], 403);
        }

        $data = $request->validate([
            'comentario' => 'nullable|string|max:500'
        ]);

        $paymentTransaction->update(['estado' => 'pagado']);

        // También actualizar el estado del pedido asociado
        $cart = $paymentTransaction->cart;
        if ($cart) {
            $cart->update(['estado_pedido' => 'confirmado']);
        }

        return response()->json(['mensaje' => 'Transacción marcada como pagada', 'transaction' => $paymentTransaction->load('images')], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentTransaction $paymentTransaction)
    {
        //
    }
}
