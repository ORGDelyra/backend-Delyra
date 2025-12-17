<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
// use laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Log;
class UserController extends Controller
{
    /**
     * Devuelve el perfil del usuario autenticado
     * GET /api/user/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['mensaje' => 'No autenticado'], 401);
        }
        return response()->json(['usuario' => $user->load('images')], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = User::included()->filter()->sort();
        $perPage = request('per_page');
        if ($perPage) {
            $users = $query->paginate($perPage);
        } else {
            $users = $query->get();
        }
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
     * PUT /api/user/{id}
     *
     * Acepta: email, telefono, primer_nombre, primer_apellido, foto_url, etc.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();

        if (!$user || ($user->id != $id && $user->id_rol !== 1)) {
            return response()->json([
                'mensaje' => 'No tienes permisos para actualizar este usuario'
            ], 403);
        }

        $userToUpdate = User::find($id);
        if (!$userToUpdate) {
            return response()->json([
                'mensaje' => 'Usuario no encontrado'
            ], 404);
        }

        $data = $request->validate([
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'telefono' => 'sometimes|string|max:20',
            'primer_nombre' => 'sometimes|string|max:100',
            'segundo_nombre' => 'sometimes|string|max:100',
            'primer_apellido' => 'sometimes|string|max:100',
            'segundo_apellido' => 'sometimes|string|max:100',
            'foto_url' => 'nullable|url',  // URL de foto de perfil (Cloudinary)
            'profile_url' => 'nullable|url',  // Alias para foto_url
        ]);

        // Actualizar campos básicos
        $userToUpdate->update($data);

        // Si incluye foto_url o profile_url, guardar como imagen de perfil
        $profileUrl = $data['profile_url'] ?? $data['foto_url'] ?? null;
        if ($profileUrl) {
            $userToUpdate->profile_url = $profileUrl;
            $userToUpdate->save();

            $userToUpdate->images()->where('type', 'profile')->delete();
            $userToUpdate->images()->create([
                'url' => $profileUrl,
                'type' => 'profile',
                'descripcion' => 'Foto de perfil del usuario',
            ]);
        }

        return response()->json([
            'mensaje' => 'Usuario actualizado con éxito',
            'user' => $userToUpdate->load('images')
        ], 200);
    }

    /**
     * Guardar foto de perfil del usuario (URL de Cloudinary)
     * POST /api/user/profile-image
     */
    public function updateProfileImage(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $url = $request->input('url') ?? $request->input('profile_url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['message' => 'URL de imagen no válida'], 422);
        }

        try {
            $user->profile_url = $url;
            $user->save();

            if (method_exists($user, 'images')) {
                $user->images()->where('type', 'profile')->delete();
                $user->images()->create([
                    'url' => $url,
                    'type' => 'profile',
                    'descripcion' => 'Foto de perfil del usuario',
                ]);
            }

            return response()->json([
                'mensaje' => 'Foto de perfil actualizada correctamente',
                'profile_url' => $user->profile_url,
                'usuario' => $user->load('images')
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar foto de perfil'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}



//siendo la 1 de la mañana escrib este comentario con 3 horas de sueño y con 2 latas de monster encima
//siendo la 1 de la mañana escrib este comentario con 3 horas de sueño y con 2 latas de monster encima
