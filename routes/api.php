<?php
// routes/api.php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleCategoryController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EventController;

// ========== PUBLIC ROUTES ==========

// Companies (public)
Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/partners', [CompanyController::class, 'partners']);
Route::get('/companies/{id}', [CompanyController::class, 'show']);

// Vérifier invitation (public)
Route::post('/invitations/verify', [InvitationController::class, 'verify']);

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Routes publics jobs
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{id}', [JobController::class, 'show']);

// Events (public)
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// ⚠️  /articles/featured DOIT être déclaré AVANT /articles/{slug}
//     sinon Laravel interpréterait "featured" comme un slug
 
Route::get('/article-categories', [ArticleCategoryController::class, 'index']);
 
Route::get('/articles',          [ArticleController::class, 'index']);
Route::get('/articles/featured', [ArticleController::class, 'featured']);
Route::get('/articles/{slug}',   [ArticleController::class, 'show']);

// ── blogs ────────────────────────────────────────────────────────────────────
Route::get('/blog-categories',        [PostController::class, 'categories']);
Route::get('/posts',                  [PostController::class, 'index']);
Route::get('/posts/{slug}',           [PostController::class, 'show']);

// ========== PROTECTED ROUTES ==========

Route::middleware(['auth:sanctum', 'not.suspended'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'user']);

    // Account settings
    Route::patch('/account/password', [AuthController::class, 'changePassword']);
    Route::patch('/account/email',    [AuthController::class, 'changeEmail']);
    Route::delete('/account',         [AuthController::class, 'deleteAccount']);
    
    // Invitations (Admin only - vérification dans controller)
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::post('/invitations/{id}/resend', [InvitationController::class, 'resend']);
    Route::delete('/invitations/{id}', [InvitationController::class, 'destroy']);
    // User
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::post('/users/{id}/avatar', [UserController::class, 'uploadAvatar']);
    Route::post('/users/{id}/cv',   [UserController::class, 'uploadCv']);
    Route::delete('/users/{id}/cv', [UserController::class, 'deleteCv']);

    // Jobs - CRUD (admin/company)
    Route::get('/my-posted-jobs', [JobController::class, 'myPostedJobs']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::patch('/jobs/{id}', [JobController::class, 'update']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);
    
    // Job Applications
    Route::post('/jobs/{id}/apply', [JobApplicationController::class, 'apply']);
    Route::get('/my-applications', [JobApplicationController::class, 'myApplications']);
    Route::get('/applications/{id}', [JobApplicationController::class, 'show']);
    Route::get('/jobs/{id}/applications', [JobApplicationController::class, 'jobApplications']);
    Route::patch('/applications/{id}/status', [JobApplicationController::class, 'updateStatus']);
    Route::post('/applications/{id}/withdraw', [JobApplicationController::class, 'withdraw']);
    Route::delete('/applications/{id}', [JobApplicationController::class, 'destroy']);

    // COMPANIES - CRUD (admin only)
    // ----------------------------------------
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::patch('/companies/{id}', [CompanyController::class, 'update']);
    Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);
    
    // Company logo
    Route::post('/companies/{id}/logo', [CompanyController::class, 'uploadLogo']);
    
    // Company status toggles
    Route::post('/companies/{id}/toggle-partner', [CompanyController::class, 'togglePartner']);
    Route::post('/companies/{id}/toggle-verified', [CompanyController::class, 'toggleVerified']);

    // Vote "utile" — tous les connectés
    Route::post('/articles/{slug}/helpful', [ArticleController::class, 'markHelpful']);
 
    // Rédaction — pedagogical | bde_member (check inline dans le controller)
    Route::post('/articles',                      [ArticleController::class, 'store']);
    Route::patch('/articles/{id}',                [ArticleController::class, 'update']);
    Route::delete('/articles/{id}',               [ArticleController::class, 'destroy']);
    Route::post('/articles/{id}/toggle-publish',  [ArticleController::class, 'togglePublish']);
    Route::post('/articles/{id}/cover',           [ArticleController::class, 'uploadCover']);
 
    // Catégories — même rôles
    Route::post('/article-categories',         [ArticleCategoryController::class, 'store']);
    Route::patch('/article-categories/{id}',   [ArticleCategoryController::class, 'update']);
    Route::delete('/article-categories/{id}',  [ArticleCategoryController::class, 'destroy']);

     // Posts CRUD
    Route::post('/posts',                      [PostController::class, 'store']);
    Route::patch('/posts/{id}',                [PostController::class, 'update']);
    Route::delete('/posts/{id}',               [PostController::class, 'destroy']);
    Route::post('/posts/{id}/toggle-publish',  [PostController::class, 'togglePublish']);
    Route::post('/posts/{id}/cover',           [PostController::class, 'uploadCoverImage']);
 
    // Réactions
    Route::post('/posts/{id}/react',           [PostController::class, 'react']);
 
    // Commentaires
    Route::get('/posts/{postId}/comments',     [CommentController::class, 'index']);
    Route::post('/posts/{postId}/comments',    [CommentController::class, 'store']);
    Route::get('/comments/{id}/replies',       [CommentController::class, 'replies']);
    Route::patch('/comments/{id}',             [CommentController::class, 'update']);
    Route::delete('/comments/{id}',            [CommentController::class, 'destroy']);

    // Events — CRUD (bde_member, pedagogical, admin)
    Route::post('/events', [EventController::class, 'store']);
    Route::patch('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::post('/events/{id}/cover', [EventController::class, 'uploadCover']);
    Route::post('/events/{id}/publish', [EventController::class, 'publish']);

    // Event attendance (authenticated)
    Route::post('/events/{id}/attend', [EventController::class, 'attend']);
    Route::delete('/events/{id}/attend', [EventController::class, 'unattend']);
    Route::get('/events/{id}/attendees', [EventController::class, 'attendees']);

    // ── ADMIN ──────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::get('/stats',                   [AdminController::class, 'stats']);
        Route::get('/users',                   [AdminController::class, 'users']);
        Route::patch('/users/{id}/suspend',    [AdminController::class, 'toggleSuspend']);
        Route::patch('/users/{id}/role',       [AdminController::class, 'updateRole']);
        Route::post('/blog-categories',        [AdminController::class, 'storeBlogCategory']);
        Route::patch('/blog-categories/{id}',  [AdminController::class, 'updateBlogCategory']);
        Route::delete('/blog-categories/{id}', [AdminController::class, 'destroyBlogCategory']);
        Route::get('/events', [EventController::class, 'adminIndex']);
    });
});