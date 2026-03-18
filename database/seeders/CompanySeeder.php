<?php
// database/seeders/CompanySeeder.php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Accenture',
                'siret' => '30211238900011',
                'website' => 'https://www.accenture.com/fr-fr',
                'linkedin_url' => 'https://www.linkedin.com/company/accenture',
                'description' => 'Leader mondial du conseil en management, technologies et externalisation. Accenture accompagne les organisations pour les aider à transformer leur activité.',
                'industry' => 'Conseil en technologies',
                'size' => '1001+', // ✅ Changé de '1000+' à '1001+'
                'headquarters_city' => 'Paris',
                'headquarters_country' => 'France',
                'is_partner' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
            [
                'name' => 'Capgemini',
                'siret' => '55507995100013',
                'website' => 'https://www.capgemini.com/fr-fr',
                'linkedin_url' => 'https://www.linkedin.com/company/capgemini',
                'description' => 'Pionnier de la transformation numérique. Capgemini est un leader mondial, responsable et multiservices, spécialisé dans le conseil, les services technologiques et la transformation numérique.',
                'industry' => 'Conseil & Services IT',
                'size' => '1001+', // ✅
                'headquarters_city' => 'Paris',
                'headquarters_country' => 'France',
                'is_partner' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
            [
                'name' => 'Thales',
                'siret' => '55200452600013',
                'website' => 'https://www.thalesgroup.com/fr',
                'linkedin_url' => 'https://www.linkedin.com/company/thales',
                'description' => 'Leader mondial des hautes technologies, Thales investit dans les innovations du numérique et de la "deep tech" – connectivité, big data, intelligence artificielle, cybersécurité et quantique.',
                'industry' => 'Défense & Aérospatiale',
                'size' => '1001+', // ✅
                'headquarters_city' => 'Paris',
                'headquarters_country' => 'France',
                'is_partner' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
            [
                'name' => 'Sopra Steria',
                'siret' => '32684681900013',
                'website' => 'https://www.soprasteria.com/fr',
                'linkedin_url' => 'https://www.linkedin.com/company/sopra-steria',
                'description' => 'Acteur majeur de la Tech en Europe, Sopra Steria est reconnu pour ses activités de conseil, de services numériques et d\'édition de logiciels.',
                'industry' => 'Services IT',
                'size' => '1001+', // ✅
                'headquarters_city' => 'Paris',
                'headquarters_country' => 'France',
                'is_partner' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
            [
                'name' => 'Orange',
                'siret' => '38012986200013',
                'website' => 'https://www.orange.com/fr',
                'linkedin_url' => 'https://www.linkedin.com/company/orange',
                'description' => 'Orange est l\'un des principaux opérateurs de télécommunications dans le monde. L\'entreprise emploie plus de 260 000 personnes dans le monde.',
                'industry' => 'Télécommunications',
                'size' => '1001+', // ✅
                'headquarters_city' => 'Paris',
                'headquarters_country' => 'France',
                'is_partner' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
        ];

        foreach ($companies as $companyData) {
            Company::create($companyData);
        }
    }
}