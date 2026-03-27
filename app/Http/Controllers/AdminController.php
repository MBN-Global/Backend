<?php
// app/Http/Controllers/AdminController.php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\BlogCategory;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ── Guard ─────────────────────────────────────────────────────────────────

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Accès réservé aux administrateurs');
    }

    // ── Stats globales ────────────────────────────────────────────────────────

    /**
     * GET /admin/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $usersByRole = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return response()->json([
            'data' => [
                'users' => [
                    'total'       => User::count(),
                    'active'      => User::whereNull('suspended_at')->count(),
                    'suspended'   => User::whereNotNull('suspended_at')->count(),
                    'by_role'     => $usersByRole,
                    'new_this_month' => User::whereMonth('created_at', now()->month)
                                           ->whereYear('created_at', now()->year)
                                           ->count(),
                ],
                'posts' => [
                    'total'     => Post::count(),
                    'published' => Post::where('status', 'published')->count(),
                    'drafts'    => Post::where('status', 'draft')->count(),
                ],
                'articles' => [
                    'total'     => Article::count(),
                    'published' => Article::where('is_published', true)->count(),
                    'drafts'    => Article::where('is_published', false)->count(),
                ],
            ],
        ]);
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    /**
     * GET /admin/users
     */
    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = User::with('info')
            ->orderByDesc('created_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',  'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($request->query('status') === 'suspended') {
            $query->whereNotNull('suspended_at');
        } elseif ($request->query('status') === 'active') {
            $query->whereNull('suspended_at');
        }

        $users = $query->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * PATCH /admin/users/{id}/suspend
     * Toggle suspension
     */
    public function toggleSuspend(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user = User::findOrFail($id);

        abort_if($user->role === 'admin', 422, 'Impossible de suspendre un administrateur');
        abort_if($user->id === $request->user()->id, 422, 'Impossible de se suspendre soi-même');
        // Révoquer les tokens si HasApiTokens est utilisé
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $user->update([
            'suspended_at' => $user->suspended_at ? null : now(),
        ]);

        return response()->json([
            'data'    => [
                'id'           => $user->id,
                'suspended_at' => $user->suspended_at,
                'is_suspended' => !is_null($user->suspended_at),
            ],
            'message' => $user->suspended_at ? 'Utilisateur suspendu' : 'Suspension levée',
        ]);
    }

    /**
     * PATCH /admin/users/{id}/role
     * Changer le rôle d'un user
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'role' => 'required|in:student,alumni,bde_member,pedagogical,company',
        ]);

        $user = User::findOrFail($id);
        abort_if($user->role === 'admin', 422, 'Impossible de modifier le rôle d\'un administrateur');

        $allowedTransitions = [
            'student'    => ['bde_member', 'alumni'],
            'bde_member' => ['student', 'alumni'],
        ];

        abort_unless(
            isset($allowedTransitions[$user->role]) && in_array($data['role'], $allowedTransitions[$user->role]),
            422,
            'Transition de rôle non autorisée'
        );

        $user->update(['role' => $data['role']]);

        return response()->json([
            'data'    => ['id' => $user->id, 'role' => $user->role],
            'message' => 'Rôle mis à jour',
        ]);
    }

    // ── Catégories blog ───────────────────────────────────────────────────────

    /**
     * POST /admin/blog-categories
     */
    public function storeBlogCategory(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:blog_categories,name',
            'slug'          => 'nullable|string|max:120|unique:blog_categories,slug',
            'color'         => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $data['slug']  = $data['slug']  ?? \Str::slug($data['name']);
        $data['color'] = $data['color'] ?? '#0038BC';

        $category = BlogCategory::create($data);

        return response()->json(['data' => $category, 'message' => 'Catégorie créée'], 201);
    }

    /**
     * PATCH /admin/blog-categories/{id}
     */
    public function updateBlogCategory(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $category = BlogCategory::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:100|unique:blog_categories,name,' . $id,
            'color'         => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $category->update($data);

        return response()->json(['data' => $category->fresh(), 'message' => 'Catégorie mise à jour']);
    }

    /**
     * DELETE /admin/blog-categories/{id}
     */
    public function destroyBlogCategory(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $category = BlogCategory::findOrFail($id);
        $category->posts()->update(['category_id' => null]);
        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée']);
    }
}