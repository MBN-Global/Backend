<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * GET /users/{id}
     * Profil public — retourne UserResource + articles publiés si auteur
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::with('info')->findOrFail($id);
    
        // Articles publiés si auteur
        $articles = [];
        $articlesCount = 0;
    
        if (in_array($user->role, ['pedagogical', 'bde_member'])) {
            $articlesQuery = \App\Models\Article::with('category')
                ->where('author_id', $user->id)
                ->where('is_published', true)
                ->orderByDesc('created_at');
    
            $articlesCount = $articlesQuery->count();
    
            $articles = $articlesQuery->limit(6)->get()->map(fn($a) => [
                'id'                  => $a->id,
                'title'               => $a->title,
                'slug'                => $a->slug,
                'description'         => $a->description,
                'difficulty'          => $a->difficulty,
                'estimated_read_time' => $a->estimated_read_time,
                'views_count'         => $a->views_count,
                'created_at'          => $a->created_at,
                'category'            => $a->category ? [
                    'name' => $a->category->name,
                    'icon' => $a->category->icon,
                    'slug' => $a->category->slug,
                ] : null,
            ]);
        }
 
        // UserResource + champs supplémentaires via additional()
        return (new UserResource($user))
            ->additional([
                'articles'       => $articles,
                'articles_count' => $articlesCount,
                'is_own_profile' => $request->user()?->id === $user->id,
            ])
            ->response();
    }

    /**
     * Mettre à jour le profil
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Vérifier autorisation
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Update user (auth fields)
        // $userValidated = $request->validate([
        //     'name' => 'sometimes|string|max:255',
        // ]);

        // if (!empty($userValidated)) {
        //     $user->update($userValidated);
        // }

        // Validation
        $validated = $request->validate([
            'bio' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'linkedin_url' => 'nullable|url|max:255',
            'github_url' => 'nullable|url|max:255',
            'website_url' => 'nullable|url|max:255',
            'cv_url' => 'nullable|url|max:255',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:50',
            'languages' => 'nullable|array',
            'languages.*.language' => 'required|string',
            'languages.*.level' => 'required|string',
            'program' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1|max:5',
            'graduation_year' => 'nullable|integer|min:2020|max:2030',
            'specialization' => 'nullable|string|max:100',
            'campus' => 'nullable|string|max:100',
        ]);

        if ($user->info) {
            $user->info->update($validated);
            
            // Recalculer profile_completion
            $profileCompletion = $user->info->calculateProfileCompletion();
            $user->info->update(['profile_completion' => $profileCompletion]);
        }

        // Reload
        $user->load('info');

        return new UserResource($user);
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($request->user()->id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

         // Upload fichier
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            
            // Stocker dans storage/app/public/avatars
            $path = $file->store('avatars', 'public');
            
            // URL publique
            $url = asset('storage/' . $path);
            
            // Update user_info
            if ($user->info) {
                // Supprimer ancien avatar si existe
                if ($user->info->avatar_url) {
                    $oldPath = str_replace(asset('storage/'), '', $user->info->avatar_url);
                    \Storage::disk('public')->delete($oldPath);
                }
                
                $user->info->update(['avatar_url' => $url]);
            }

            return response()->json([
                'message' => 'Avatar uploadé avec succès',
                'avatar_url' => $url,
            ]);
        }
        
        return response()->json(['message' => 'Aucun fichier trouvé'], 400);
    }

    public function uploadCv(Request $request, string $id): JsonResponse
    {
        // Seul l'utilisateur lui-même peut uploader son CV
        abort_unless($request->user()->id === $id, 403, 'Action non autorisée');
    
        $request->validate([
            'cv' => 'required|file|mimes:pdf|max:5120', // 5 Mo max
        ]);
    
        $userInfo = $request->user()->info;
    
        // Supprimer l'ancien CV du storage si existant
        if ($userInfo->cv_url) {
            $oldPath = str_replace('/storage/', '', $userInfo->cv_url);
            \Storage::disk('public')->delete($oldPath);
        }
    
        // Stocker le nouveau CV
        $path = $request->file('cv')->store("users/{$id}/cv", 'public');
        $url  = '/storage/' . $path;
    
        // Mettre à jour UserInfo
        $userInfo->update(['cv_url' => $url]);
    
        // Recalculer la complétion du profil
        $userInfo->update([
            'profile_completion' => $userInfo->calculateProfileCompletion(),
        ]);
    
        return response()->json([
            'data' => [
                'cv_url'             => $url,
                'profile_completion' => $userInfo->fresh()->profile_completion,
            ],
            'message' => 'CV uploadé avec succès',
        ]);
    }
 
    /**
     * DELETE /users/{id}/cv
     * Suppression du CV
     */
    public function deleteCv(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()->id === $id, 403, 'Action non autorisée');
    
        $userInfo = $request->user()->info;
    
        if ($userInfo->cv_url) {
            $path = str_replace('/storage/', '', $userInfo->cv_url);
            \Storage::disk('public')->delete($path);
    
            $userInfo->update(['cv_url' => null]);
            $userInfo->update([
                'profile_completion' => $userInfo->calculateProfileCompletion(),
            ]);
        }
    
        return response()->json([
            'data' => [
                'cv_url'             => null,
                'profile_completion' => $userInfo->fresh()->profile_completion,
            ],
            'message' => 'CV supprimé',
        ]);
    }
}