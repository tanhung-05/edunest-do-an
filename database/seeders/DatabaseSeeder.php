<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (app()->environment('local')) {
            $this->call([DemoSeeder::class, CourseDemoSeeder::class, CatalogDemoSeeder::class, OrderDemoSeeder::class, LearningDemoSeeder::class, CommerceDemoSeeder::class, ExamDemoSeeder::class, PracticeDemoSeeder::class, GuardianDemoSeeder::class, BundleDemoSeeder::class, AiDemoSeeder::class, LiveDemoSeeder::class, CommunityDemoSeeder::class, DemoBoSungSeeder::class]);
        }
    }
}
