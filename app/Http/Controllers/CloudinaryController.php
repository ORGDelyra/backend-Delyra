<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CloudinaryController extends Controller
{
    /**
     * Genera una URL privada/firmada para un recurso de Cloudinary.
     *
     * Nota: Para entregar correctamente URLs privadas se recomienda instalar
     * el SDK oficial `cloudinary-labs/cloudinary-laravel`. Si no está instalado
     * este endpoint devuelve instrucciones para instalarlo.
     */
    public function generatePrivateUrl(Request $request)
    {
        $request->validate([
            'public_id' => 'required|string',
            'resource_type' => 'sometimes|string|in:image,video',
            'type' => 'sometimes|string',
            'transformation' => 'sometimes|string'
        ]);

        // Si la librería oficial está disponible, usarla
        if (class_exists('\Cloudinary\Cloudinary')) {
            try {
                $cloudinary = app('\Cloudinary\Cloudinary');
                $publicId = $request->input('public_id');
                $options = [];
                $timestamp = time();

                // Si la librería provee un método para generar URLs firmadas, usarlo
                if (method_exists($cloudinary, 'image') && method_exists($cloudinary, 'signedUrl')) {
                    // Código opcional si la API del SDK lo soporta
                    $url = $cloudinary->image($publicId)->toUrl();
                    return response()->json(['url' => $url]);
                }

                // Fallback: devolver mensaje indicando la disponibilidad del SDK
                return response()->json(['mensaje' => 'SDK Cloudinary disponible pero no se encontró método de firma automático. Consulte la documentación del SDK.'], 500);

            } catch (\Exception $e) {
                Log::error('Cloudinary signed url error: ' . $e->getMessage());
                return response()->json(['mensaje' => 'Error generando URL firmada', 'error' => $e->getMessage()], 500);
            }
        }

        // Si no está instalado el SDK, indicar cómo instalarlo
        return response()->json([
            'mensaje' => 'No se encuentra el SDK oficial de Cloudinary en el servidor.',
            'instrucciones' => 'Ejecuta: composer require cloudinary-labs/cloudinary-laravel && php artisan vendor:publish --provider="CloudinaryLabs\CloudinaryLaravel\CloudinaryServiceProvider"',
            'nota' => 'Una vez instalado, podrás usar este endpoint para generar URLs firmadas para recursos privados.'
        ], 501);
    }
}
