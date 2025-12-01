<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $branches = Branch::with('user')->get();
        return response()->json($branches);
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
        $data = $request->validate([
            'nombre_sucursal'=> 'required|string|max:50',
            'nit' => 'required|string|unique:branches,nit',
            'img_nit' => 'required|string',
            'longitud' => 'required|string',
            'latitud' => 'required|string',
            'direccion' => 'required|string',
            'id_commerce_category' => 'required|exists:categories,id',
            'logo_url' => 'nullable|url',  // Logo del comercio (URL de Cloudinary, opcional)
        ]);

        // Obtener usuario autenticado
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'mensaje' => 'Error: Usuario no autenticado'
            ], 401);
        }

        // Verificar que el usuario no tenga ya una sucursal (un solo branch por usuario)
        if ($user->branches()->exists()) {
            return response()->json([
                'mensaje' => 'El usuario ya tiene una sucursal registrada'
            ], 400);
        }

        // Agregar id_usuario a los datos
        $data['id_usuario'] = $user->id;

        $branch = Branch::create($data);
        if(!$branch){
            return response()->json([
                'mensaje' => 'Error: no se ha podido crear la sucursal'
            ],400);
        }

        // Guardar logo de la sucursal si existe
        if ($request->logo_url) {
            $branch->images()->create([
                'url' => $data['logo_url'],
                'type' => 'logo',
                'descripcion' => 'Logo del comercio',
            ]);
        }

        return response()->json([
            'mensaje' => 'Sucursal creada con exito',
            'sucursal' => $branch->load('images'),
            'id' => $branch->id
        ],201);
    }
    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        return response()->json($branch->load('user'), 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Branch $branch)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $user = $request->user();

        // Verificar que el usuario sea el dueño de la sucursal
        if ($branch->id_usuario !== $user->id) {
            return response()->json([
                'mensaje' => 'No tienes permisos para actualizar esta sucursal'
            ], 403);
        }

        $data = $request->validate([
            'nombre_sucursal'=> 'sometimes|string|max:50',
            'nit' => 'sometimes|string|unique:branches,nit,' . $branch->id,
            'img_nit' => 'sometimes|string',
            'longitud' => 'sometimes|string',
            'latitud' => 'sometimes|string',
            'direccion' => 'sometimes|string'
        ]);

        $branch->update($data);

        return response()->json([
            'mensaje' => 'Sucursal actualizada con éxito',
            'sucursal' => $branch
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Branch $branch)
    {
        $user = $request->user();

        // Verificar que el usuario sea el dueño de la sucursal
        if ($branch->id_usuario !== $user->id) {
            return response()->json([
                'mensaje' => 'No tienes permisos para eliminar esta sucursal'
            ], 403);
        }

        $branch->delete();

        return response()->json([
            'mensaje' => 'Sucursal eliminada con éxito'
        ], 200);
    }
}
