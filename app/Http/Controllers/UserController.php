<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use laravel\Sanctum\HasApiTokens;
class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::included()->filter()->sort()->get();
        return response()->json($users);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Guardar foto de perfil del usuario (URL de Cloudinary)
     */
    public function updateProfileImage(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'mensaje' => 'Error: Usuario no autenticado'
            ], 401);
        }

        $request->validate([
            'profile_url' => 'required|url',
        ]);

        // Eliminar imagen de perfil anterior si existe
        $user->images()->where('type', 'profile')->delete();

        // Crear nueva imagen de perfil
        $user->images()->create([
            'url' => $request->profile_url,
            'type' => 'profile',
            'descripcion' => 'Foto de perfil del usuario',
        ]);

        return response()->json([
            'mensaje' => 'Foto de perfil actualizada con éxito',
            'user' => $user->load('images')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
