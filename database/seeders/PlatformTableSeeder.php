<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformTableSeeder extends Seeder
{
    public function run()
    {
        $platforms = [
            ['name' => 'whatsapp', 'status' => 1],
            ['name' => 'facebook_messenger', 'status' => 1],
            ['name' => 'instagram', 'status' => 1],
            ['name' => 'facebook', 'status' => 1],
            ['name' => 'website', 'status'   => 1],
        ];

        foreach ($platforms as $platform) {
            DB::table('platforms')->insert([
                'name'             => $platform['name'],
                'status'           => $platform['status'],
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ]);
        }
    }
}
