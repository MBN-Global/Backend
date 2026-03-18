<?php
// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'siret',
        'logo_url',
        'website',
        'linkedin_url',
        'description',
        'industry',
        'size',
        'headquarters_city',
        'headquarters_country',
        'is_partner',
        'is_verified',
        'verified_at',
        'jobs_posted',
        'active_jobs',
    ];

    protected $casts = [
        'is_partner' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'jobs_posted' => 'integer',
        'active_jobs' => 'integer',
    ];

    // Relations
    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function users()
    {
        return $this->hasMany(UserInfo::class, 'company_id');
    }

    // Scopes
    public function scopePartners($query)
    {
        return $query->where('is_partner', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Methods
    public function updateJobStats()
    {
        $this->update([
            'jobs_posted' => $this->jobs()->count(),
            'active_jobs' => $this->jobs()->active()->count(),
        ]);
    }

    public function markAsVerified()
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}