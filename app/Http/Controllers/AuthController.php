<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // VALIDACIONES DE USUARIO
        $rules = [
            'primer_nombre' => 'required|string|max:50',
            'segundo_nombre' => 'nullable|string|max:50',
            'primer_apellido' => 'required|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
            'telefono' => 'required|string|max:10',
            'correo' => 'required|email|string|unique:users,correo',
            'password' => 'required|string|min:6',
            'cuenta_bancaria' => 'required|string|max:30',
            'id_rol' => 'required|exists:rols,id'
        ];

        // VALIDACIONES PARA DOMICILIARIO
        if ($request->id_rol == 4) {
            $rules = array_merge($rules, [
                'placa' => 'required|string|max:20',
                'tipo_vehiculo' => 'required|string',
                'seguro_vig' => 'required|date',
                'run_vig' => 'required|date'
            ]);
        }

        $data = $request->validate($rules);

        // CREAR USUARIO
        $user = User::create([
            'primer_nombre' => $data['primer_nombre'],
            'segundo_nombre' => $data['segundo_nombre'] ?? null,
            'primer_apellido' => $data['primer_apellido'],
            'segundo_apellido' => $data['segundo_apellido'] ?? null,
            'telefono' => $data['telefono'],
            'correo' => $data['correo'],
            'password' => Hash::make($data['password']),
            'id_rol' => $data['id_rol'],
            'estado_cuenta' => true,
            'cuenta_bancaria' => $data['cuenta_bancaria']
        ]);

        // CREAR VEHÍCULO AUTOMÁTICAMENTE SI ES DOMICILIARIO
        if ($data['id_rol'] == 4) {

            Vehicle::create([
                'id_usuario' => $user->id,
                'placa' => $data['placa'],
                'tipo_vehiculo' => $data['tipo_vehiculo'],
                'seguro_vig' => $data['seguro_vig'],
                'run_vig' => $data['run_vig']
            ]);

            Service::create([
                'id_usuario' => $user->id,
                'estado_dispo' => 'activo'
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'mensaje' => 'Usuario creado exitosamente',
            'usuario' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ], 200);
    }


    public function login(Request $request)
    {
        $credenciales = $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('correo', $credenciales['correo'])->first();

        if (!$user || !Hash::check($credenciales['password'], $user->password)) {
            return response()->json([
                'mensaje' => 'Credenciales incorrectas'
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'mensaje' => 'Login Exitoso',
            'usuario' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}
