<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthMobileController extends Controller
{
	public function register(Request $request)
	{
		$rules = [
			'name' => 'required|string',
			'email' => 'required|string|unique:users',
			'password' => 'required|string|min:6'
		];
		
		$messages = [
			'name.required' => 'El campo nombre es obligatorio',
			'name.string' => 'El campo nombre debe ser una cadena de texto',
			'email.required' => 'El campo email es obligatorio',
			'email.string' => 'El campo email debe ser una cadena de texto',
			'email.unique' => 'El email ya está registrado. Por favor, use otro',
			'email.email' => 'El correo electrónico no es válido',
			'password.required' => 'El campo contraseña es obligatorio',
			'password.string' => 'El campo contraseña debe ser una cadena de texto',
			'password.min' => 'La contraseña debe tener al menos 6 caracteres',
		];
		
		$validator = Validator::make($request->all(), $rules, $messages);
		
		if ($validator->fails())
		{
			return response()->json($validator->errors(), 400);
		}

		$user = User::create([
			'name' => $request->name,
			'email' => $request->email,
			'password' => Hash::make($request->password)
		]);

		$token = $user->createToken('CMS8 Access Token')->plainTextToken;
		//$response = ['user' => $user, 'token' => $token];
		$response = ['email' => $user->email, 'token' => $token];

		return response()->json($response, 200);
	}

	public function login(Request $request)
	{
		$rules = [
			'email' => 'required',
			'password' => 'required|string',
			'remember_me' => 'boolean'
		];

		$request->validate($rules);

		$user = User::where('email', $request->email)->first();

		if ($user && Hash::check($request->password, $user->password))
		{
			$token = $user->createToken('CMS8 Access Token')->plainTextToken;
			
			//$response = ['user' => $user, 'token' => $token];
			$response = ['email' => $user->email, 'token' => $token];

			return response()->json($response, 200);
		}

		$response = ['message' => 'Los datos de acceso son incorrectos'];

		return response()->json($response, 401);
	}

	public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'Su sesión se ha cerrado correctamente'
        ]);
    }
}
