<?php
// database/seeders/ArticleCategorySeeder.php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'          => 'Orientation & Parcours',
                'slug'          => 'orientation',
                'icon'          => '🎓',
                'description'   => 'Guides sur les choix d\'orientation, de filière et de spécialisation',
                'display_order' => 1,
            ],
            [
                'name'          => 'Vie étudiante',
                'slug'          => 'vie-etudiante',
                'icon'          => '🏠',
                'description'   => 'Logement, transport, vie associative et services campus',
                'display_order' => 2,
            ],
            [
                'name'          => 'Emploi & Stage',
                'slug'          => 'emploi',
                'icon'          => '💼',
                'description'   => 'Recherche d\'emploi, stages, alternance et insertion professionnelle',
                'display_order' => 3,
            ],
            [
                'name'          => 'International',
                'slug'          => 'international',
                'icon'          => '🌍',
                'description'   => 'Mobilité, visas, équivalences et études à l\'étranger',
                'display_order' => 4,
            ],
            [
                'name'          => 'Administratif',
                'slug'          => 'administratif',
                'icon'          => '📋',
                'description'   => 'Démarches administratives, inscriptions, certificats et diplômes',
                'display_order' => 5,
            ],
            [
                'name'          => 'Bourses & Aides',
                'slug'          => 'bourses',
                'icon'          => '💰',
                'description'   => 'Bourses sur critères sociaux, aides d\'urgence et financements',
                'display_order' => 6,
            ],
            [
                'name'          => 'Santé & Bien-être',
                'slug'          => 'sante',
                'icon'          => '💚',
                'description'   => 'Accompagnement psychologique, santé et handicap',
                'display_order' => 7,
            ],
        ];

        foreach ($categories as $category) {
            ArticleCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}