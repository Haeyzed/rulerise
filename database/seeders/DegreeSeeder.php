<?php

namespace Database\Seeders;

use App\Models\Degree;
use Illuminate\Database\Seeder;

class DegreeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $degrees = [
            [
                'name' => 'High School/Secondary School Certificate',
                'description' => 'Completion of secondary education',
                'level' => 1
            ],
            [
                'name' => 'Vocational/Technical Certificate',
                'description' => 'Specialized training in a specific trade or skill',
                'level' => 2
            ],
            [
                'name' => 'Associate Degree',
                'description' => 'Two-year undergraduate degree',
                'level' => 3
            ],
            [
                'name' => 'Bachelor\'s Degree',
                'description' => 'Four-year undergraduate degree',
                'level' => 4
            ],
            [
                'name' => 'Master\'s Degree',
                'description' => 'Graduate degree following a bachelor\'s degree',
                'level' => 5
            ],
            [
                'name' => 'Doctorate Degree',
                'description' => 'Highest academic degree (PhD)',
                'level' => 6
            ],
            [
                'name' => 'Professional Degree (e.g., MD, JD)',
                'description' => 'Specialized degree required for certain professions',
                'level' => 6
            ],
            [
                'name' => 'Postdoctoral Research',
                'description' => 'Advanced research following a doctorate',
                'level' => 7
            ],
            [
                'name' => 'Diploma',
                'description' => 'Certificate of completion for a specific course of study',
                'level' => 2
            ],
            [
                'name' => 'Certificate Program',
                'description' => 'Specialized training program',
                'level' => 2
            ],
            [
                'name' => 'Advanced Diploma',
                'description' => 'Higher-level diploma program',
                'level' => 3
            ],
            [
                'name' => 'Executive Education/Certificate',
                'description' => 'Professional development for executives',
                'level' => 4
            ],
            [
                'name' => 'Trade Certification',
                'description' => 'Certification in a specific trade',
                'level' => 2
            ],
            [
                'name' => 'Fellowship',
                'description' => 'Advanced training in a specialized field',
                'level' => 6
            ],
            [
                'name' => 'Specialist Degree (e.g., Ed.S.)',
                'description' => 'Specialized degree between master\'s and doctorate',
                'level' => 5
            ],
        ];

        foreach ($degrees as $degree) {
            Degree::updateOrCreate(
                ['name' => $degree['name']],
                [
                    'description' => $degree['description'],
                    'level' => $degree['level']
                ]
            );
        }

        $this->command->info('Degree data seeded successfully!');
    }
}
