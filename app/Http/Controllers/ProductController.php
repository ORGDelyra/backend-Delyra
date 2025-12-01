<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;



class ProductController extends Controller
{
    // Lista todos los productos (público)
    public function index()
    {
        $products = Product::with('category','user')->get();
        return response()->json($products);
    }

    // Crear producto (solo comerciante) + imágenes
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if($user->id_rol != 3){ // 3 = comerciante
            return response()->json(['mensaje' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'id_categoria' => 'required|exists:categories,id',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric',
            'cantidad' => 'required|integer',
            'image_url' => 'required|url',         // URL de la imagen principal (Cloudinary)
            'comprobante_url' => 'nullable|url',   // URL del comprobante (opcional)
        ]);

        // Crear el producto
        $product = $user->products()->create([
            'id_categoria' => $data['id_categoria'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'precio' => $data['precio'],
            'cantidad' => $data['cantidad']
        ]);

        // Guardar imagen principal del producto
        $product->images()->create([
            'url' => $data['image_url'],
            'type' => 'product',
            'descripcion' => 'Imagen principal del producto',
        ]);

        // Guardar comprobante de pago si existe
        if ($request->comprobante_url) {
            $product->images()->create([
                'url' => $data['comprobante_url'],
                'type' => 'comprobante',
                'descripcion' => 'Comprobante de pago adjunto',
            ]);
        }

        return response()->json([
            'mensaje' => 'Producto y activos guardados con éxito',
            'product' => $product->load('images')
        ], 201);
    }

    // Actualizar producto (solo comerciante dueño del producto)
    public function update(Request $request, Product $product)
    {
        $user = Auth::user();
        if($user->id_rol != 3 || $product->id_usuario != $user->id){
            return response()->json(['mensaje' => 'No autorizado'], 403);
        }
            /** @var \App\Models\User $user */
            $user = Auth::user();
        $data = $request->validate([
            'id_categoria' => 'sometimes|exists:categories,id',
            'nombre' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string',
            'precio' => 'sometimes|numeric',
            'cantidad' => 'sometimes|integer'
        ]);

        $product->update($data);
        return response()->json($product);
    }

    // Agregar producto al carrito (usuario logueado)
    public function addToCart(Request $request, Product $product)
    {
        $user = Auth::user();
        if($user->id_rol != 2){ // 2 = cliente
            return response()->json(['mensaje' => 'No autorizado'], 403);
        }
            /** @var \App\Models\User $user */
            $user = Auth::user();
        $data = $request->validate([
            'cantidad' => 'required|integer|min:1'
        ]);

        // Busca carrito activo o crea uno
        $cart = Cart::firstOrCreate(
            ['id_usuario' => $user->id, 'activo' => true]
        );

        // Si el producto ya está en el carrito, suma la cantidad
        if($cart->products()->where('product_id', $product->id)->exists()){
            $cart->products()->updateExistingPivot($product->id, [
                'cantidad' => DB::raw('cantidad + '.$data['cantidad'])
            ]);
        } else {
            $cart->products()->attach($product->id, ['cantidad' => $data['cantidad']]);
        }

        return response()->json(['mensaje' => 'Producto agregado al carrito', 'cart' => $cart->load('products')]);
    }

    // Ver carrito activo del usuario
    public function viewCart()
    {
        $user = Auth::user();
        if($user->id_rol != 2){
            return response()->json(['mensaje' => 'No autorizado'], 403);
        }
            /** @var \App\Models\User $user */
            $user = Auth::user();
        $cart = Cart::with('products')->where('id_usuario', $user->id)->where('activo', true)->first();
        if(!$cart) return response()->json(['mensaje' => 'Carrito vacío']);

        return response()->json($cart);
    }

    // Checkout: marca carrito como inactivo
    public function checkout()
    {
        $user = Auth::user();
        if($user->id_rol != 2){
            return response()->json(['mensaje' => 'No autorizado'], 403);
        }
            /** @var \App\Models\User $user */
            $user = Auth::user();
        $cart = Cart::where('id_usuario', $user->id)->where('activo', true)->first();
        if(!$cart) return response()->json(['mensaje' => 'Carrito vacío']);

          $cart->update(['activo' => false]);

        return response()->json(['mensaje' => 'Compra realizada', 'cart' => $cart]);
    }
}
