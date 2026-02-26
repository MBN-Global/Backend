<?php
// app/Models/UserInfo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_info';

    protected $fillable = [
        'user_id',
        'avatar_url',
        'bio',
        'phone',
        'linkedin_url',
        'github_url',
        'website_url',
        'cv_url',
        'skills',
        'languages',
        'program',
        'year',
        'graduation_year',
        'specialization',
        'campus',
        'company_id',
        'reputation_points',
        'level',
        'profile_completion',
    ];

    protected $casts = [
        'skills' => 'array',
        'languages' => 'array',
        'reputation_points' => 'integer',
        'profile_completion' => 'integer',
    ];

    /**
     * Relation inverse avec User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Calculer automatiquement profile_completion
     */
    public function calculateProfileCompletion(): int
    {
        $fields = [
            'avatar_url' => 10,
            'bio' => 15,
            'phone' => 5,
            'linkedin_url' => 10,
            'github_url' => 5,
            'cv_url' => 20,
            'skills' => 15,
            'program' => 10,
            'campus' => 5,
        ];

        $total = 20; // Base (name + email)

        foreach ($fields as $field => $points) {
            if (!empty($this->$field)) {
                $total += $points;
            }
        }

        return min($total, 100);
    }
}