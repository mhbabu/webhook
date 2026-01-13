<?php

namespace Database\Seeders;

use App\Models\Disposition;
use App\Models\SubDisposition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SubDispositionSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('sub_dispositions') ||
            ! Schema::hasTable('dispositions')
        ) {
            return;
        }

        SubDisposition::truncate();

        $serviceIssue = Disposition::where('name', 'Service Issue')->first();
        $productIssue = Disposition::where('name', 'Product Issue')->first();
        $sales = Disposition::where('name', 'Sales')->first();

        if (! $serviceIssue || ! $productIssue || ! $sales) {
            return;
        }

        SubDisposition::insert([
            // Service Issue
            [
                'disposition_id' => $serviceIssue->id,
                'name' => 'Delay',
                'is_active' => true,
            ],
            [
                'disposition_id' => $serviceIssue->id,
                'name' => 'Rude Behavior',
                'is_active' => true,
            ],

            // Product Issue
            [
                'disposition_id' => $productIssue->id,
                'name' => 'Damaged Product',
                'is_active' => true,
            ],
            [
                'disposition_id' => $productIssue->id,
                'name' => 'Wrong Item',
                'is_active' => true,
            ],

            // Sales
            [
                'disposition_id' => $sales->id,
                'name' => 'Interested',
                'is_active' => true,
            ],
            [
                'disposition_id' => $sales->id,
                'name' => 'Follow Up Required',
                'is_active' => true,
            ],
        ]);
    }
}
