<?php
// =====================================================================
// SEEDER — database/seeders/BlogCategorySeeder.php
// =====================================================================
 
namespace Database\Seeders;
 
use App\Models\BlogCategory;
use Illuminate\Database\Seeder;
 
class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Vie étudiante',   'slug' => 'vie-etudiante',  'color' => '#0038BC', 'display_order' => 1],
            ['name' => 'Emploi & Stage',  'slug' => 'emploi-stage',   'color' => '#EF8F00', 'display_order' => 2],
            ['name' => 'International',   'slug' => 'international',   'color' => '#16A34A', 'display_order' => 3],
            ['name' => 'Projets',         'slug' => 'projets',         'color' => '#9333EA', 'display_order' => 4],
            ['name' => 'Événements',      'slug' => 'evenements',      'color' => '#DC2626', 'display_order' => 5],
            ['name' => 'Témoignages',     'slug' => 'temoignages',     'color' => '#0891B2', 'display_order' => 6],
            ['name' => 'Astuces',         'slug' => 'astuces',         'color' => '#CA8A04', 'display_order' => 7],
        ];
 
        foreach ($categories as $cat) {
            BlogCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}