<?php
// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * List all companies (public)
     */
    public function index(Request $request)
    {
        $query = Company::query();

        // Filters
        if ($request->filled('is_partner')) {
            $query->where('is_partner', $request->boolean('is_partner'));
        }

        if ($request->filled('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }

        if ($request->filled('industry')) {
            $query->where('industry', $request->industry);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('industry', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['name', 'created_at', 'jobs_posted', 'active_jobs'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $companies = $query->paginate(20);

        return CompanyResource::collection($companies);
    }

    /**
     * Show single company (public)
     */
    public function show($id)
    {
        $company = Company::with(['jobs' => function ($query) {
            $query->active()->latest('published_at')->limit(10);
        }])->findOrFail($id);

        return new CompanyResource($company);
    }

    /**
     * Create company (admin only)
     */
    public function store(Request $request)
    {
        // Authorization
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'siret' => 'nullable|string|max:14|unique:companies,siret',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'website' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'industry' => 'nullable|string|max:100',
            'size' => 'nullable|in:1-10,11-50,51-200,201-500,501-1000,1001+', 
            'headquarters_city' => 'nullable|string|max:100',
            'headquarters_country' => 'nullable|string|max:100',
            'is_partner' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ]);

        // Upload logo
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('companies/logos', 'public');
            $validated['logo_url'] = $logoPath;
        }

        // Set verified_at if is_verified
        if (isset($validated['is_verified']) && $validated['is_verified']) {
            $validated['verified_at'] = now();
        }

        $company = Company::create($validated);

        return new CompanyResource($company);
    }

    /**
     * Update company (admin only)
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Authorization
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'siret' => 'nullable|string|max:14|unique:companies,siret,' . $company->id,
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'website' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'industry' => 'nullable|string|max:100',
            'size' => 'nullable|in:1-10,11-50,51-200,201-500,501-1000,1001+',
            'headquarters_city' => 'nullable|string|max:100',
            'headquarters_country' => 'nullable|string|max:100',
            'is_partner' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ]);

        // Upload new logo
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($company->logo_url) {
                Storage::disk('public')->delete($company->logo_url);
            }

            $logoPath = $request->file('logo')->store('companies/logos', 'public');
            $validated['logo_url'] = $logoPath;
        }

        // Set verified_at if newly verified
        if (isset($validated['is_verified']) && $validated['is_verified'] && !$company->is_verified) {
            $validated['verified_at'] = now();
        }

        $company->update($validated);

        return new CompanyResource($company);
    }

    /**
     * Delete company (admin only)
     */
    public function destroy(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Authorization
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Check if company has active jobs
        if ($company->active_jobs > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer une entreprise avec des offres actives',
            ], 422);
        }

        // Delete logo
        if ($company->logo_url) {
            Storage::disk('public')->delete($company->logo_url);
        }

        $company->delete();

        return response()->json([
            'message' => 'Entreprise supprimée avec succès',
        ]);
    }

    /**
     * Get partners (public)
     */
    public function partners(Request $request)
    {
        $partners = Company::partners()
            ->verified()
            ->orderBy('name')
            ->get();

        return CompanyResource::collection($partners);
    }

    /**
     * Upload logo (admin only)
     */
    public function uploadLogo(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Authorization
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Delete old logo
        if ($company->logo_url) {
            Storage::disk('public')->delete($company->logo_url);
        }

        // Upload new logo
        $logoPath = $request->file('logo')->store('companies/logos', 'public');

        $company->update(['logo_url' => $logoPath]);

        return response()->json([
            'message' => 'Logo mis à jour avec succès',
            'logo_url' => Storage::url($logoPath),
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Toggle partner status (admin only)
     */
    public function togglePartner(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Authorization
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $company->update([
            'is_partner' => !$company->is_partner,
        ]);

        return response()->json([
            'message' => $company->is_partner 
                ? 'Entreprise ajoutée aux partenaires' 
                : 'Entreprise retirée des partenaires',
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Toggle verified status (admin only)
     */
    public function toggleVerified(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Authorization
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($company->is_verified) {
            // Unverify
            $company->update([
                'is_verified' => false,
                'verified_at' => null,
            ]);
            $message = 'Entreprise dévérifiée';
        } else {
            // Verify
            $company->markAsVerified();
            $message = 'Entreprise vérifiée';
        }

        return response()->json([
            'message' => $message,
            'company' => new CompanyResource($company),
        ]);
    }
}