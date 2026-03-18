<?php
// app/Http/Controllers/PostController.php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\BlogCategoryResource;
use App\Models\Post;
use App\Models\BlogCategory;
use App\Models\PostReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    // Tous les connectés peuvent créer des posts
    private const AUTHOR_ROLES = ['student', 'alumni', 'bde_member', 'pedagogical', 'company'];

    // ── LECTURE ───────────────────────────────────────────────────────────────

    /**
     * GET /posts
     * Liste paginée — publiés uniquement sauf si mine=true (ses propres drafts)
     */
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $query    = Post::with(['author.info', 'category'])
                        ->orderByDesc('published_at')
                        ->orderByDesc('created_at');

        // Visibilité
        if ($request->boolean('mine') && $user) {
            $query->where('author_id', $user->id);
        } else {
            $query->published();
        }

        // Filtres
        if ($search = $request->query('search')) {
            $query->search($search);
        }
        if ($category = $request->query('category')) {
            $query->byCategory($category);
        }
        if ($tag = $request->query('tag')) {
            $query->byTag($tag);
        }
        if ($authorId = $request->query('author_id')) {
            $query->where('author_id', $authorId);
        }
        if ($request->boolean('mine') && $request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $posts = $query->paginate(12);

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
            ],
        ]);
    }

    /**
     * GET /posts/{slug}
     * Accepte slug ou UUID — incrémente views_count
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $post = Post::with(['author.info', 'category'])
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        // Brouillons visibles uniquement par l'auteur
        if ($post->status !== 'published') {
            abort_unless(
                $request->user()?->id === $post->author_id,
                404
            );
        }

        $post->incrementQuietly('views_count');

        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * GET /blog-categories
     */
    public function categories(): JsonResponse
    {
        $categories = BlogCategory::withCount([
                'posts as posts_count' => fn($q) => $q->published(),
            ])
            ->ordered()
            ->get();

        return response()->json([
            'data' => BlogCategoryResource::collection($categories),
        ]);
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    /**
     * POST /posts
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAuthor($request);

        $data = $this->validatePost($request);
        $data['author_id'] = $request->user()->id;
        $data['slug']      = Post::generateUniqueSlug($data['slug'] ?? $data['title']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image_url'] = $this->uploadCover($request);
        }

        $post = Post::create($data);
        $post->load(['author.info', 'category']);

        return response()->json(['data' => new PostResource($post)], 201);
    }

    /**
     * PATCH /posts/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        abort_unless($request->user()->id === $post->author_id, 403, 'Non autorisé');

        $data = $this->validatePost($request, isUpdate: true);

        if (isset($data['title']) && !isset($data['slug'])) {
            $data['slug'] = Post::generateUniqueSlug($data['title'], $post->id);
        }

        if ($request->hasFile('cover_image')) {
            if ($post->cover_image_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $post->cover_image_url));
            }
            $data['cover_image_url'] = $this->uploadCover($request);
        }

        $post->update($data);
        $post->load(['author.info', 'category']);

        return response()->json(['data' => new PostResource($post->fresh())]);
    }

    /**
     * DELETE /posts/{id}
     * Soft delete
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        abort_unless($request->user()->id === $post->author_id, 403);
        $post->delete();

        return response()->json(['message' => 'Post supprimé']);
    }

    /**
     * POST /posts/{id}/toggle-publish
     */
    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        abort_unless($request->user()->id === $post->author_id, 403);

        $newStatus = $post->status === 'published' ? 'draft' : 'published';
        $post->update([
            'status'       => $newStatus,
            'published_at' => $newStatus === 'published' ? ($post->published_at ?? now()) : $post->published_at,
        ]);

        return response()->json([
            'data'    => new PostResource($post->fresh(['author.info', 'category'])),
            'message' => $newStatus === 'published' ? 'Post publié !' : 'Post dépublié',
        ]);
    }

    /**
     * POST /posts/{id}/cover
     * Upload image de couverture séparé
     */
    public function uploadCoverImage(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        abort_unless($request->user()->id === $post->author_id, 403);

        $request->validate(['cover_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        if ($post->cover_image_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $post->cover_image_url));
        }

        $url = $this->uploadCover($request);
        $post->update(['cover_image_url' => $url]);

        return response()->json([
            'data'    => ['cover_image_url' => $url],
            'message' => 'Image uploadée',
        ]);
    }

    // ── RÉACTIONS ─────────────────────────────────────────────────────────────

    /**
     * POST /posts/{id}/react
     * Body: { type: 'like' | 'useful' | 'bravo' }
     * - Si même type → retire la réaction (toggle)
     * - Si type différent → change la réaction
     */
    public function react(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:like,useful,bravo',
        ]);

        $post     = Post::findOrFail($id);
        $userId   = $request->user()->id;
        $newType  = $request->input('type');
        $counter  = PostReaction::COUNTERS[$newType];

        $existing = PostReaction::where('post_id', $post->id)
                                ->where('user_id', $userId)
                                ->first();

        if ($existing) {
            if ($existing->type === $newType) {
                // Toggle off — retirer la réaction
                $existing->delete();
                $post->decrementQuietly(PostReaction::COUNTERS[$newType]);
                $userReaction = null;
            } else {
                // Changer de type
                $post->decrementQuietly(PostReaction::COUNTERS[$existing->type]);
                $existing->update(['type' => $newType]);
                $post->incrementQuietly($counter);
                $userReaction = $newType;
            }
        } else {
            // Nouvelle réaction
            PostReaction::create([
                'post_id' => $post->id,
                'user_id' => $userId,
                'type'    => $newType,
            ]);
            $post->incrementQuietly($counter);
            $userReaction = $newType;
        }

        $post->refresh();

        return response()->json([
            'data' => [
                'likes_count'   => $post->likes_count,
                'useful_count'  => $post->useful_count,
                'bravo_count'   => $post->bravo_count,
                'user_reaction' => $userReaction,
            ],
        ]);
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────

    private function authorizeAuthor(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, self::AUTHOR_ROLES),
            403,
            'Action non autorisée'
        );
    }

    private function uploadCover(Request $request): string
    {
        $path = $request->file('cover_image')->store('posts/covers', 'public');
        return '/storage/' . $path;
    }

    private function validatePost(Request $request, bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes|' : 'required|';

        return $request->validate([
            'title'        => $req . 'string|max:200',
            'slug'         => 'nullable|string|max:220',
            'excerpt'      => 'nullable|string|max:300',
            'content'      => $req . 'string',
            'cover_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'category_id'  => 'nullable|uuid|exists:blog_categories,id',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
            'status'       => 'sometimes|in:draft,published',
        ]);
    }
}