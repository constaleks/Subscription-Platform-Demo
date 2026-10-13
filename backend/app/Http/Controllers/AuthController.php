<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
 
        $user = User::create($data);
 
        Auth::login($user);
        $request->session()->regenerate();
 
        return UserResource::make($user)->response()->setStatusCode(201);
    }
 
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
 
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }
 
        $request->session()->regenerate();
 
        return UserResource::make(Auth::user())->response();
    }
 
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
 
        $request->session()->invalidate();
        $request->session()->regenerateToken();
 
        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return UserResource::make(Auth::user())->response();
    }
}
