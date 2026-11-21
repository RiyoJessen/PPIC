<?php

namespace Database\Seeders;

use App\Database\Seeder as AppSeeder;

class DatabaseSeeder
{
    public function run(): void
    {
        AppSeeder::ensureBaseSystemReferences();
    }
}
