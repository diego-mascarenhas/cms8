<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MeetKnockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MeetKnockController extends Controller
{
    public function store(Request $request, MeetKnockService $knocks): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255'],
            'app_url' => ['nullable', 'string', 'max:200'],
        ], [
            'name.required' => 'Escribí tu nombre.',
            'name.max' => 'El nombre es demasiado largo.',
            'email.required' => 'Escribí tu email.',
            'email.email' => 'Ingresá un email válido.',
        ]);

        try
        {
            $knock = $knocks->request(
                (int) $request->input('team_id'),
                $validated['name'],
                $validated['email'],
                $validated['app_url'] ?? null,
            );
        } catch (RuntimeException $exception)
        {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json([
            'id' => $knock['id'],
            'status' => $knock['status'],
        ], 201);
    }

    public function show(Request $request, MeetKnockService $knocks, string $id): JsonResponse
    {
        $status = $knocks->status((int) $request->input('team_id'), $id);
        if ($status === null)
        {
            return response()->json([
                'message' => 'La petición no existe.',
                'status' => 'expired',
            ], 404);
        }

        return response()->json($status);
    }

    public function admit(Request $request, MeetKnockService $knocks): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
        ], [
            'token.required' => 'La petición no es válida.',
            'token.regex' => 'La petición no es válida.',
        ]);

        $admitted = $knocks->admit((int) $request->input('team_id'), $validated['token']);
        if ($admitted === null)
        {
            return response()->json(['message' => 'La petición ya no está activa.'], 404);
        }

        return response()->json($admitted);
    }
}
