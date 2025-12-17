<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class JobOffersController extends Controller
{
    // Listar todas las ofertas o filtrar por branch_id
    public function index(Request $request)
    {
        $branchId = $request->query('branch_id');
        $query = JobOffer::with('branch');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $offers = $query->get();
        return response()->json([
            'success' => true,
            'data' => $offers,
            'message' => 'Ofertas laborales encontradas'
        ]);
    }

    // Crear oferta (solo dueño de la sucursal)
    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'requisitos' => 'nullable|string',
            'tipo_puesto' => 'required|string',
            'salario' => 'nullable|numeric',
            'estado' => 'required|in:activa,inactiva',
            'fecha_publicacion' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
        ]);
        $branch = Branch::find($data['branch_id']);
        if (!$branch || $branch->id_usuario !== $user->id) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No autorizado para crear ofertas en esta sucursal.'
            ], 403);
        }
        $offer = JobOffer::create($data);
        return response()->json([
            'success' => true,
            'data' => $offer,
            'message' => 'Oferta laboral creada correctamente.'
        ], 201);
    }

    // Ver detalle de una oferta
    public function show($id)
    {
        $offer = JobOffer::with('branch')->find($id);
        if (!$offer) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Oferta no encontrada.'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $offer,
            'message' => 'Detalle de la oferta.'
        ]);
    }

    // Editar oferta (solo dueño de la sucursal)
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $offer = JobOffer::find($id);
        if (!$offer) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Oferta no encontrada.'
            ], 404);
        }
        $branch = $offer->branch;
        if (!$branch || $branch->id_usuario !== $user->id) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No autorizado para editar esta oferta.'
            ], 403);
        }
        $data = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|string',
            'requisitos' => 'nullable|string',
            'tipo_puesto' => 'sometimes|string',
            'salario' => 'nullable|numeric',
            'estado' => 'sometimes|in:activa,inactiva',
            'fecha_publicacion' => 'sometimes|date',
        ]);
        $offer->update($data);
        return response()->json([
            'success' => true,
            'data' => $offer,
            'message' => 'Oferta laboral actualizada.'
        ]);
    }

    // Eliminar oferta (solo dueño de la sucursal)
    public function destroy($id)
    {
        $user = Auth::user();
        $offer = JobOffer::find($id);
        if (!$offer) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Oferta no encontrada.'
            ], 404);
        }
        $branch = $offer->branch;
        if (!$branch || $branch->id_usuario !== $user->id) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No autorizado para eliminar esta oferta.'
            ], 403);
        }
        $offer->delete();
        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Oferta laboral eliminada.'
        ]);
    }

    // Listar ofertas de un negocio/sucursal específico
    public function byBusiness($id)
    {
        $offers = JobOffer::where('branch_id', $id)->with('branch')->get();
        return response()->json([
            'success' => true,
            'data' => $offers,
            'message' => 'Ofertas laborales de la sucursal.'
        ]);
    }
}
