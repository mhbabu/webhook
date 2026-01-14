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
            ! Schema::hasTable('dispositions') ||
            ! Schema::hasTable('sub_dispositions')
        ) {
            return;
        }

        SubDisposition::truncate();

        $map = [
            'Service Issue' => [
                'Delay',
                'Rude Behavior',
            ],
            'Product Issue' => [
                'Damaged Product',
                'Wrong Item',
            ],
            'Sales' => [
                'Interested',
                'Follow Up Required',
            ],
            'General Inquiry' => [
                'Information Request',
                'Other',
            ],
        ];

        foreach ($map as $dispositionName => $subs) {
            $disposition = Disposition::where('name', $dispositionName)->first();

            if (! $disposition) {
                continue;
            }

            foreach ($subs as $subName) {
                SubDisposition::create([
                    'disposition_id' => $disposition->id,
                    'name' => $subName,
                    'is_active' => true,
                ]);
            }
        }
    }
}
