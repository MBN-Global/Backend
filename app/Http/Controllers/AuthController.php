<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register avec token d'invitation
     */
    public function register(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'invitation_token' => 'required|string',
        ]);

        // Vérifier invitation
        $invitation = Invitation::where('token', $validated['invitation_token'])->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'invitation_token' => ['Token d\'invitation invalide.'],
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation_token' => ['Cette invitation a expiré.'],
            ]);
        }

        if ($invitation->isUsed()) {
            throw ValidationException::withMessages([
                'invitation_token' => ['Cette invitation a déjà été utilisée.'],
            ]);
        }

        if ($invitation->email !== $validated['email']) {
            throw ValidationException::withMessages([
                'email' => ['L\'email ne correspond pas à l\'invitation.'],
            ]);
        }

        // Créer user avec rôle de l'invitation
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $invitation->role,
        ]);

        // UserInfo créé automatiquement via booted()

        // Marquer invitation comme utilisée
        $invitation->markAsUsed();

        // Auto-login
        Auth::login($user);
        $request->session()->regenerate();

        // Eager load info
        $user->load('info');

        return response()->json([
            'message' => 'Compte créé avec succès',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        // $request->validate([
        //     'email' => 'required|email',
        //     'password' => 'required',
        // ]);
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        
        // // Attempt login
        // if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
        //     throw ValidationException::withMessages([
        //         'email' => ['Les identifiants fournis sont incorrects.'],
        //     ]);
        // }
        $user = User::where('email', $data['email'])->first();

        if (!$user || !\Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        // Bloquer ici — avant même de créer le token
        if ($user->suspended_at) {
            return response()->json([
                'message' => 'Votre compte a été suspendu. Contactez l\'administration.',
                'code'    => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        // $token = $user->createToken('auth_token')->plainTextToken;

        // Régénérer session (sécurité)
        // $request->session()->regenerate();

        // Update last_login_at
        // Régénérer session uniquement si disponible (Sanctum SPA utilise les sessions)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // ✅ $user directement, pas Auth::user()
        $user->update(['last_login_at' => now()]);
        $user->load('info');

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Get current user
     */
    public function user(Request $request)
    {
        $user = $request->user()->load('info');
        return new UserResource($user);
    }

    /**
     * PATCH /account/password — Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Mot de passe modifié avec succès']);
    }

    /**
     * PATCH /account/email — Change email (requires password confirmation)
     */
    public function changeEmail(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Mot de passe incorrect'], 422);
        }

        $user->update(['email' => $request->email]);

        return response()->json(['message' => 'Email modifié avec succès', 'email' => $user->email]);
    }

    /**
     * DELETE /account — Delete own account (requires password confirmation)
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Mot de passe incorrect'], 422);
        }

        // Revoke all Sanctum tokens then soft-delete
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Compte supprimé']);
    }
}