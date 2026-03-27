<?php
// app/Http/Controllers/ArticleController.php

namespace App\Http\Controllers;

use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    // ── Rôles auteurs ─────────────────────────────────────────────────────────
    private const AUTHOR_ROLES = ['pedagogical', 'bde_member'];

    // ─────────────────────────────────────────────────────────────────────────
    // LECTURE (public)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /articles
     * - Visiteurs / étudiants : articles publiés uniquement
     * - Auteurs (pedagogical | bde_member) : leurs brouillons + tous les publiés
     *   Paramètre ?mine=true → uniquement les articles de l'auteur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $isAuthor  = $user && in_array($user->role, self::AUTHOR_ROLES);
    
        $query = Article::with(['category', 'author.info'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at');
    
        // ── Visibilité ────────────────────────────────────────────────────────────
        if ($isAuthor && $request->boolean('mine')) {
            // Mode "mes articles" : tous les articles de cet auteur (publiés + brouillons)
            $query->where('author_id', $user->id);
        } elseif ($isAuthor) {
            // Auteur connecté : publiés de tous + ses propres brouillons
            $query->where(function ($q) use ($user) {
                $q->where('is_published', true)
                ->orWhere('author_id', $user->id);
            });
        } else {
            // Public : publiés uniquement
            $query->where('is_published', true);
        }
    
        // ── Filtres ───────────────────────────────────────────────────────────────
        if ($search = $request->query('search')) {
            $query->search($search);
        }
        if ($category = $request->query('category')) {
            $query->byCategory($category);
        }
        if ($difficulty = $request->query('difficulty')) {
            $query->where('difficulty', $difficulty);
        }
        if ($audience = $request->query('audience')) {
            $query->whereJsonContains('target_audience', $audience);
        }
        if ($request->boolean('featured')) {
            $query->featured();
        }
        // Filtre statut pour le dashboard auteur
        if ($request->query('status') === 'draft') {
            $query->where('is_published', false);
        } elseif ($request->query('status') === 'published') {
            $query->where('is_published', true);
        }
    
        $articles = $query->paginate(12);
    
        return response()->json([
            'data' => ArticleResource::collection($articles->items()),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
                'per_page'     => $articles->perPage(),
                'total'        => $articles->total(),
            ],
        ]);
    }

    /**
     * GET /articles/featured
     * 4 articles mis en avant
     */
    public function featured(): JsonResponse
    {
        $articles = Article::with(['category', 'author.info'])
            ->published()
            ->featured()
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        return response()->json([
            'data' => ArticleResource::collection($articles),
        ]);
    }


    /**
     * GET /articles/{slug}
     * Accepte un slug OU un UUID — utile pour la page d'édition admin
     * qui connaît l'ID mais pas forcément le slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        // Cherche d'abord par slug, puis par id (UUID)
        $article = Article::with(['category', 'author.info'])
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->first();

        if (! $article) {
            abort(404, 'Article introuvable');
        }

        // Brouillons visibles uniquement par les auteurs
        if (! $article->is_published) {
            abort_unless(
                $request->user() && in_array($request->user()->role, self::AUTHOR_ROLES),
                404
            );
        }

        $article->incrementQuietly('views_count');

        return response()->json([
            'data' => new ArticleResource($article),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉCRITURE (pedagogical | bde_member)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /articles
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAuthor($request);

        $data = $this->validateArticle($request);
        $data['author_id'] = $request->user()->id;

        // Slug — auto-généré depuis le titre si absent
        $data['slug'] = Article::generateUniqueSlug($data['slug'] ?? $data['title']);

        // Upload cover image si présente
        if ($request->hasFile('cover_image')) {
            $data['cover_image_url'] = $this->uploadCover($request);
        }

        $article = Article::create($data);
        $article->load(['category', 'author.info']);

        return response()->json([
            'data'    => new ArticleResource($article),
            'message' => 'Article créé',
        ], 201);
    }

    /**
     * PATCH /articles/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAuthor($request);

        $article = Article::findOrFail($id);

        // Seul l'auteur ou un pédagogique peut modifier
        abort_unless(
            $article->author_id === $request->user()->id
            || $request->user()->role === 'pedagogical',
            403,
            'Vous ne pouvez modifier que vos propres articles'
        );

        $data = $this->validateArticle($request, isUpdate: true);

        // Nouveau slug si le titre change et qu'aucun slug explicite n'est fourni
        if (isset($data['title']) && !isset($data['slug'])) {
            $data['slug'] = Article::generateUniqueSlug($data['title'], $article->id);
        } elseif (isset($data['slug'])) {
            $data['slug'] = Article::generateUniqueSlug($data['slug'], $article->id);
        }

        // Upload nouvelle cover
        if ($request->hasFile('cover_image')) {
            // Supprimer l'ancienne
            if ($article->cover_image_url) {
                Storage::disk('public')->delete(
                    str_replace('/storage/', '', $article->cover_image_url)
                );
            }
            $data['cover_image_url'] = $this->uploadCover($request);
        }

        $article->update($data);
        $article->load(['category', 'author.info']);

        return response()->json([
            'data'    => new ArticleResource($article->fresh()),
            'message' => 'Article mis à jour',
        ]);
    }

    /**
     * DELETE /articles/{id}
     * Soft delete
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAuthor($request);

        $article = Article::findOrFail($id);

        abort_unless(
            $article->author_id === $request->user()->id
            || $request->user()->role === 'pedagogical',
            403,
            'Vous ne pouvez supprimer que vos propres articles'
        );

        $article->delete(); // SoftDelete

        return response()->json(['message' => 'Article supprimé']);
    }

    /**
     * POST /articles/{id}/toggle-publish
     */
    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $this->authorizeAuthor($request);

        $article = Article::findOrFail($id);

        abort_unless(
            $article->author_id === $request->user()->id
            || $request->user()->role === 'pedagogical',
            403
        );

        $article->update(['is_published' => ! $article->is_published]);

        return response()->json([
            'data'    => new ArticleResource($article->fresh(['category', 'author.info'])),
            'message' => $article->is_published ? 'Article publié' : 'Article dépublié',
        ]);
    }

    /**
     * POST /articles/{id}/helpful
     * Vote "utile" — 1 vote max par user par article
     */
    public function markHelpful(Request $request, string $id): JsonResponse
    {
        $article = Article::where('id', $id)->firstOrFail();
        $userId  = $request->user()->id;

        // Vérifier si déjà voté
        $alreadyVoted = $article->helpfulVoters()
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyVoted) {
            return response()->json([
                'message' => 'Vous avez déjà voté pour cet article',
            ], 422);
        }

        // Enregistrer le vote et incrémenter le compteur
        $article->helpfulVoters()->attach($userId);
        $article->incrementQuietly('helpful_count');

        return response()->json([
            'message'       => 'Merci pour votre retour !',
            'helpful_count' => $article->fresh()->helpful_count,
        ]);
    }

    /**
     * POST /articles/{id}/cover
     * Upload cover image séparé (optionnel, si tu veux un endpoint dédié)
     */
    public function uploadCover(Request $request, string $id = null): string|JsonResponse
    {
        // Appelé en interne depuis store/update → retourne le chemin
        if ($id === null) {
            $path = $request->file('cover_image')->store('articles/covers', 'public');
            return '/storage/' . $path;
        }

        // Appelé comme endpoint HTTP dédié
        $this->authorizeAuthor($request);
        $request->validate(['cover_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        $article = Article::findOrFail($id);

        if ($article->cover_image_url) {
            Storage::disk('public')->delete(
                str_replace('/storage/', '', $article->cover_image_url)
            );
        }

        $path = $request->file('cover_image')->store('articles/covers', 'public');
        $url  = '/storage/' . $path;

        $article->update(['cover_image_url' => $url]);

        return response()->json([
            'data'    => ['cover_image_url' => $url],
            'message' => 'Image uploadée',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function authorizeAuthor(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, self::AUTHOR_ROLES),
            403,
            'Action réservée aux rédacteurs'
        );
    }

    /**
     * Règles de validation partagées store/update
     */
    private function validateArticle(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes|' : 'required|';

        return $request->validate([
            // Content
            'title'               => $required . 'string|max:200',
            'slug'                => 'nullable|string|max:220',
            'description'         => 'nullable|string|max:300',
            'category_id'         => 'nullable|uuid|exists:article_categories,id',
            'content'             => $required . 'string',
            'cover_image'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            // Metadata
            'difficulty'          => 'sometimes|in:easy,medium,complex',
            'estimated_read_time' => 'nullable|integer|min:1|max:120',
            'target_audience'     => 'nullable|array',
            'target_audience.*'   => 'string|max:100',

            // Rich blocs JSON
            'related_links'              => 'nullable|array',
            'related_links.*.title'      => 'required_with:related_links|string|max:200',
            'related_links.*.url'        => 'required_with:related_links|url',
            'related_links.*.type'       => 'required_with:related_links|in:official,guide,video,tool,other',

            'timeline'                          => 'nullable|array',
            'timeline.*.step'                   => 'required_with:timeline|integer|min:1',
            'timeline.*.title'                  => 'required_with:timeline|string|max:200',
            'timeline.*.description'            => 'nullable|string',
            'timeline.*.estimated_duration'     => 'nullable|string|max:100',

            'attachments'                  => 'nullable|array',
            'attachments.*.title'          => 'required_with:attachments|string|max:200',
            'attachments.*.description'    => 'nullable|string',
            'attachments.*.file_url'       => 'required_with:attachments|url',
            'attachments.*.type'           => 'required_with:attachments|in:pdf,doc,image,other',

            'checklist'                        => 'nullable|array',
            'checklist.title'                  => 'required_with:checklist|string|max:200',
            'checklist.items'                  => 'required_with:checklist|array',
            'checklist.items.*.text'           => 'required|string|max:300',
            'checklist.items.*.is_optional'    => 'boolean',

            'costs'                  => 'nullable|array',
            'costs.*.item'           => 'required_with:costs|string|max:200',
            'costs.*.amount'         => 'nullable|numeric|min:0',
            'costs.*.currency'       => 'required_with:costs|string|max:10',
            'costs.*.is_variable'    => 'boolean',

            // Versioning
            'changelog'    => 'nullable|string|max:1000',

            // Status
            'is_published' => 'sometimes|boolean',
            'is_featured'  => 'sometimes|boolean',
        ]);
    }
}