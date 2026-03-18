<?php
// app/Http/Controllers/CommentController.php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * GET /posts/{postId}/comments
     * Commentaires racines paginés + leurs replies (niveau 1)
     */
    public function index(Request $request, string $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);

        $comments = Comment::with(['author.info', 'replies.author.info'])
            ->where('post_id', $post->id)
            ->whereNull('parent_id')       // racines uniquement
            ->withTrashed()                // garder les supprimés pour la structure
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => CommentResource::collection($comments->items()),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page'    => $comments->lastPage(),
                'per_page'     => $comments->perPage(),
                'total'        => $comments->total(),
            ],
        ]);
    }

    /**
     * GET /comments/{id}/replies
     * Replies d'un commentaire (pagination si nombreuses)
     */
    public function replies(Request $request, string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        $replies = Comment::with(['author.info'])
            ->where('parent_id', $comment->id)
            ->withTrashed()
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => CommentResource::collection($replies->items()),
            'meta' => [
                'current_page' => $replies->currentPage(),
                'last_page'    => $replies->lastPage(),
                'per_page'     => $replies->perPage(),
                'total'        => $replies->total(),
            ],
        ]);
    }

    /**
     * POST /posts/{postId}/comments
     * Créer un commentaire ou une réponse
     * Body: { content, parent_id? }
     */
    public function store(Request $request, string $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);

        // On ne commente que les posts publiés
        abort_unless($post->status === 'published', 422, 'Ce post n\'est pas publié');

        $data = $request->validate([
            'content'   => 'required|string|max:2000',
            'parent_id' => 'nullable|uuid|exists:comments,id',
        ]);

        // Vérifier que le parent appartient bien à ce post
        if (!empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);
            abort_unless($parent->post_id === $post->id, 422, 'Parent invalide');
            // On n'autorise qu'un niveau de réponse (pas de réponse à une réponse)
            abort_if(!is_null($parent->parent_id), 422, 'Réponses imbriquées non supportées');
        }

        $comment = Comment::create([
            'post_id'   => $post->id,
            'author_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'content'   => $data['content'],
        ]);

        $comment->load(['author.info']);

        return response()->json([
            'data'    => new CommentResource($comment),
            'message' => 'Commentaire ajouté',
        ], 201);
    }

    /**
     * PATCH /comments/{id}
     * Modifier son propre commentaire
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        abort_unless($request->user()->id === $comment->author_id, 403);

        $data = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $comment->update($data);

        return response()->json([
            'data'    => new CommentResource($comment->fresh(['author.info'])),
            'message' => 'Commentaire modifié',
        ]);
    }

    /**
     * DELETE /comments/{id}
     * Soft delete — garde la structure, masque le contenu
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        abort_unless($request->user()->id === $comment->author_id, 403);

        $comment->delete();

        return response()->json(['message' => 'Commentaire supprimé']);
    }
}