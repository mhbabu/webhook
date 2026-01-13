<?php

namespace Database\Seeders;

use App\Models\Disposition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DispositionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('dispositions')) {
            return;
        }

        Disposition::truncate();

        Disposition::insert([
            ['name' => 'Service Issue', 'is_active' => true],
            ['name' => 'Product Issue', 'is_active' => true],
            ['name' => 'General Inquiry', 'is_active' => true],
            ['name' => 'Sales', 'is_active' => true],
        ]);
    }
}
