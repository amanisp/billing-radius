<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\globalSettings;
use App\Models\Groups;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = "superadmin";


        $group = Groups::create([

            'name' => 'AMAN ADMINISTRATOR',
            'slug' => 'aman-administrator',
            'wa_api_token' => Str::random(15),
            'group_type' => $role,
        ]);

        globalSettings::create(['isolir_mode' => false, 'group_id' => $group['id']]);

        User::create([
            'username'       => 'superadmin',
            'name'           => 'AMAN ADMINISTRATOR',
            'role'           => $role,
            'email'          => 'support@aman-isp.net',
            'phone_number'   => '628977624949',
            'group_id'       => $group['id'],
            // 'email_verified_at' => now(),
            'password'       => Hash::make('superaman'),
            'remember_token' => Str::random(10),
        ]);
    }
}
