<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
	public function register(Request $request)
	{
		$rules = [
			'name' => 'required|string',
			'email' => 'required|string|unique:users',
			'password' => 'required|string|min:6',
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
			'password' => Hash::make($request->password),
		]);

		$token = $user->createToken('CMS8 Access Token')->plainTextToken;

		$response = ['email' => $user->email, 'token' => $token];

		return response()->json($response, 200);
	}

	public function login(Request $request)
	{
		$rules = [
			'email' => 'required',
			'password' => 'required|string',
			'remember_me' => 'boolean',
		];

		$request->validate($rules);

		$user = User::where('email', $request->email)->first();

		if ($user && Hash::check($request->password, $user->password))
		{
			$token = $user->createToken('CMS8 Access Token')->plainTextToken;

			$response = ['email' => $user->email, 'token' => $token];

			return response()->json($response, 200);
		}

		$response = ['message' => 'Los datos de acceso son incorrectos'];

		return response()->json($response, 401);
	}

	public function logout(Request $request)
	{
		$request->user()->currentAccessToken()->delete();

		return response()->json([
			'message' => 'Su sesión se ha cerrado correctamente',
		]);
	}

	/**
	 * Login user with token for auto-access to client area
	 */
	public function loginWithToken($token)
	{
		try
		{
			// First, try to parse as signed token (new format)
			$user = TokenHelper::validateSignedToken($token);

			// If signed token validation fails, try Sanctum token (legacy)
			if (! $user)
			{
				$user = $this->validateSanctumToken($token);
			}

			if (! $user)
			{
				return redirect()->route('login')->withErrors(['error' => 'Token inválido o expirado']);
			}

			// Log the user in using the session guard
			auth()->login($user, true);

			// Redirect to the appropriate dashboard based on user role
			if ($user->hasRole('admin'))
			{
				return redirect()->route('dashboard');
			} elseif ($user->hasRole('collaborator'))
			{
				return redirect()->route('dashboard.collaborator');
			} elseif ($user->hasRole('client'))
			{
				return redirect()->route('dashboard.client');
			}

			// Default fallback
			return redirect()->route('dashboard');
		} catch (\Exception $e)
		{
			\Log::error('Error in token login: '.$e->getMessage());

			return redirect()->route('login')->withErrors(['error' => 'Error al procesar el token']);
		}
	}

	/**
	 * Validate Sanctum token (legacy support)
	 */
	private function validateSanctumToken($token)
	{
		try
		{
			$accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

			if (! $accessToken || $accessToken->expires_at < now())
			{
				return null;
			}

			$user = $accessToken->tokenable;

			if ($user)
			{
				// Revoke the token since it was used for single login
				$accessToken->delete();
				\Log::info('Sanctum token validated and revoked', ['user_id' => $user->id]);
			}

			return $user;
		} catch (\Exception $e)
		{
			\Log::error('Error validating Sanctum token: '.$e->getMessage());

			return null;
		}
	}
}
