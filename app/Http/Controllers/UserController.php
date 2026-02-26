<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * Liste des users (Admin only)
     */
    public function index()
    {
        // ✅ Vérifier que l'user est admin
        abort_unless(auth()->user()->role === 'admin', 403);

        return response()->json(User::paginate(20));
    }

    /**
     * Afficher un user
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        
        return response()->json($user);
    }

    /**
     * Mettre à jour un user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // ✅ Vérifier que c'est son propre profil ou admin
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string|max:500',
            'phone' => 'nullable|string',
            'linkedin_url' => 'nullable|url',
            'github_url' => 'nullable|url',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Supprimer un user (Admin only)
     */
    public function destroy($id)
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }
}