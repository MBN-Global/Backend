<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // Récupère la liste de tous les utilisateurs
    public function index(){
        return response()->json([
            'success' => true,
            'data' => User::all(),
            'message' => 'Utilisateurs récupérés avec succès'
        ]);
    }

    // Enregistre un nouvel utilisateur
    public function register(Request $request)
    {
        // Valide les données d'entrée
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed'
        ]);

        // Crée un nouvel utilisateur avec le mot de passe hashé
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Utilisateur enregistré avec succès',
            'user' => $user
        ], 201);
    }

    // Authentifie l'utilisateur et génère un token API
    public function login(Request $request)
    {
        // Valide les identifiants
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Vérifie si les identifiants sont valides
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Identifiants invalides'
            ], 401);
        }

        // Crée un token Sanctum pour l'authentification API
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user,
            'token' => $token
        ]);
    }

    // Déconnecte l'utilisateur en supprimant son token d'accès
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    // Retourne les données de l'utilisateur actuellement authentifié
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}