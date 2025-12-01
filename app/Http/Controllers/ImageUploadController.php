<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ImageUploadController extends Controller
{
    /**
     * Sube un archivo recibido al endpoint de Cloudinary usando un upload preset (unsigned).
     * Si prefieres usar server-side signed uploads, instala y configura el SDK oficial.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // max 50MB
        ]);

        $file = $request->file('file');

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $uploadPreset = $request->input('upload_preset', env('CLOUDINARY_UPLOAD_PRESET'));

        if (empty($cloudName) || empty($uploadPreset)) {
            return response()->json(['mensaje' => 'Cloudinary no configurado en el servidor (cloud name o upload preset faltante)'], 500);
        }

        try {
            $url = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

            $response = Http::asMultipart()->post($url, [
                [
                    'name' => 'file',
                    'contents' => fopen($file->getPathname(), 'r')
                ],
                [
                    'name' => 'upload_preset',
                    'contents' => $uploadPreset
                ]
            ]);

            if ($response->failed()) {
                Log::error('Cloudinary upload failed: ' . $response->body());
                return response()->json(['mensaje' => 'Error subiendo la imagen a Cloudinary', 'raw' => $response->body()], 500);
            }

            $body = $response->json();

            return response()->json([
                'secure_url' => $body['secure_url'] ?? null,
                'raw' => $body
            ], 200);

        } catch (\Exception $e) {
            Log::error('Cloudinary upload error: ' . $e->getMessage());
            return response()->json(['mensaje' => 'Error subiendo la imagen', 'error' => $e->getMessage()], 500);
        }
    }
}
