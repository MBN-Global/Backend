<?php
// app/Http/Controllers/ArticleCategoryController.php

namespace App\Http\Controllers;

use App\Http\Resources\ArticleCategoryResource;
use App\Models\ArticleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleCategoryController extends Controller
{
    // ── Rôles autorisés à gérer les catégories ────────────────────────────────
    private const ADMIN_ROLES = ['pedagogical', 'bde_member'];

    /**
     * GET /article-categories
     * Public — liste ordonnée avec compteur d'articles publiés
     */
    public function index(): JsonResponse
    {
        $categories = ArticleCategory::withCount([
                'articles as articles_count' => fn($q) => $q->where('is_published', true)
                                                             ->whereNull('deleted_at'),
            ])
            ->ordered()
            ->get();

        return response()->json([
            'data' => ArticleCategoryResource::collection($categories),
        ]);
    }

    /**
     * POST /article-categories
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:article_categories,name',
            'slug'          => 'nullable|string|max:120|unique:article_categories,slug',
            'icon'          => 'nullable|string|max:10',
            'description'   => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = ArticleCategory::create($data);

        return response()->json([
            'data'    => new ArticleCategoryResource($category),
            'message' => 'Catégorie créée',
        ], 201);
    }

    /**
     * PATCH /article-categories/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeRole($request);

        $category = ArticleCategory::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:100|unique:article_categories,name,' . $id,
            'slug'          => 'sometimes|string|max:120|unique:article_categories,slug,' . $id,
            'icon'          => 'nullable|string|max:10',
            'description'   => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $category->update($data);

        return response()->json([
            'data'    => new ArticleCategoryResource($category->fresh()),
            'message' => 'Catégorie mise à jour',
        ]);
    }

    /**
     * DELETE /article-categories/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeRole($request);

        $category = ArticleCategory::findOrFail($id);

        // Dé-lier les articles (met category_id à null)
        $category->articles()->update(['category_id' => null]);
        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée']);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function authorizeRole(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, self::ADMIN_ROLES),
            403,
            'Action non autorisée'
        );
    }
}