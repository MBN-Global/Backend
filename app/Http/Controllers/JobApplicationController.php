<?php
// app/Http/Controllers/JobApplicationController.php

namespace App\Http\Controllers;

use App\Http\Resources\JobApplicationResource;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobApplicationController extends Controller
{
    /**
     * Apply to a job (students/alumni only)
     */
    public function apply(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);

        // Check: Only internal jobs
        if ($job->source_type !== 'internal') {
            return response()->json([
                'message' => 'Cette offre est externe. Veuillez postuler directement sur le site de l\'entreprise.',
                'application_url' => $job->application_url,
            ], 422);
        }

        // Check: Job is active
        if (!$job->isActive()) {
            return response()->json([
                'message' => 'Cette offre n\'est plus disponible.',
            ], 422);
        }

        $user = $request->user();

        // Check: User can apply (student/alumni)
        if (!in_array($user->role, ['student', 'alumni'])) {
            return response()->json([
                'message' => 'Seuls les étudiants et alumni peuvent postuler.',
            ], 403);
        }

        // Check: User hasn't already applied
        if ($job->hasUserApplied($user->id)) {
            return response()->json([
                'message' => 'Vous avez déjà postulé à cette offre.',
            ], 422);
        }

        // Validation
        $validated = $request->validate([
            'cover_letter' => 'required|string|min:50|max:5000',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120', // 5MB max
            'additional_documents.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:5120',
        ]);

        // Check si UserInfo existe
        if (!$user->info) {
            return response()->json([
                'message' => 'Veuillez compléter votre profil avant de postuler.',
            ], 422);
        }


        // Use user's CV if not provided
        $cvUrl = $user->info->cv_url;

        // Upload custom CV if provided
        if ($request->hasFile('cv')) {
            $cvPath = $request->file('cv')->store('applications/cvs', 'public');
            $cvUrl = $cvPath;
        }

        // Upload additional documents
        $additionalDocs = [];
        if ($request->hasFile('additional_documents')) {
            foreach ($request->file('additional_documents') as $file) {
                $path = $file->store('applications/documents', 'public');
                $additionalDocs[] = $path;
            }
        }

        // Create application
        $application = JobApplication::create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'cover_letter' => $validated['cover_letter'],
            'cv_url' => $cvUrl,
            'additional_documents' => $additionalDocs,
            'status' => 'pending',
        ]);

        // Increment job applications count
        $job->incrementApplications();

        // Load relations
        $application->load(['job', 'user.info']);

        return response()->json([
            'message' => 'Candidature envoyée avec succès !',
            'application' => new JobApplicationResource($application),
        ], 201);
    }

    /**
     * Get user's applications (own applications)
     */
    public function myApplications(Request $request)
    {
        $applications = JobApplication::with(['job.company', 'job.postedBy'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return JobApplicationResource::collection($applications);
    }

    /**
     * Get single application (user's own or admin/company)
     */
    public function show(Request $request, $id)
    {
        $application = JobApplication::with(['job.company', 'user.info'])
            ->findOrFail($id);

        $user = $request->user();

        // Authorization: owner, admin, or job creator
        if ($application->user_id !== $user->id 
            && $user->role !== 'admin' 
            && $application->job->posted_by !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return new JobApplicationResource($application);
    }

    /**
     * Get applications for a job (admin/company/job creator only)
     */
    public function jobApplications(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        $user = $request->user();

        // Authorization: admin or job creator
        if ($user->role !== 'admin' && $job->posted_by !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $query = JobApplication::with(['user.info'])
            ->where('job_id', $jobId)
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->paginate(20);

        return JobApplicationResource::collection($applications);
    }

    /**
     * Update application status (admin/company/job creator only)
     */
    public function updateStatus(Request $request, $id)
    {
        $application = JobApplication::with('job')->findOrFail($id);
        $user = $request->user();

        // Authorization: admin or job creator
        if ($user->role !== 'admin' && $application->job->posted_by !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,reviewed,shortlisted,interview,rejected,accepted',
            'notes' => 'nullable|string|max:1000',
            'interview_at' => 'nullable|date|after:now',
        ]);

        $application->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $application->notes,
        ]);

        // Update timestamps based on status
        if ($validated['status'] === 'reviewed' && !$application->reviewed_at) {
            $application->update(['reviewed_at' => now()]);
        }

        if ($validated['status'] === 'interview' && isset($validated['interview_at'])) {
            $application->update(['interview_at' => $validated['interview_at']]);
        }

        if (in_array($validated['status'], ['accepted', 'rejected']) && !$application->responded_at) {
            $application->update(['responded_at' => now()]);
        }

        $application->load(['job', 'user.info']);

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'application' => new JobApplicationResource($application),
        ]);
    }

    /**
     * Withdraw application (user only, before reviewed)
     */
    public function withdraw(Request $request, $id)
    {
        $application = JobApplication::findOrFail($id);
        $user = $request->user();

        // Authorization: only owner
        if ($application->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Can only withdraw if pending
        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Vous ne pouvez retirer que les candidatures en attente.',
            ], 422);
        }

        // Delete application
        $application->delete();

        // Decrement job applications count
        $application->job->decrement('applications_count');

        return response()->json([
            'message' => 'Candidature retirée avec succès',
        ]);
    }

    /**
     * Delete application (admin only)
     */
    public function destroy(Request $request, $id)
    {
        $application = JobApplication::findOrFail($id);

        // Authorization: admin only
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Delete uploaded files
        if ($application->cv_url) {
            Storage::disk('public')->delete($application->cv_url);
        }

        if ($application->additional_documents) {
            foreach ($application->additional_documents as $doc) {
                Storage::disk('public')->delete($doc);
            }
        }

        $application->delete();

        return response()->json([
            'message' => 'Candidature supprimée avec succès',
        ]);
    }
}