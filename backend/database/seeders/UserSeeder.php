<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'code');

        $users = [
            ['Administrator Sistem', 'admin', 'admin@divaanugrahutama.co.id', RoleCode::ADMIN],
            ['Nisa Amelia', 'qs.nisa', 'nisa@divaanugrahutama.co.id', RoleCode::QS],
            ['Jihan Safira', 'qs.jihan', 'jihan@divaanugrahutama.co.id', RoleCode::QS],
            ["A'id Maghfur", 'kontraktor.aid', 'aid@divaanugrahutama.co.id', RoleCode::KONTRAKTOR],
            ['Abdul Muiz', 'kontraktor.muiz', 'muiz@divaanugrahutama.co.id', RoleCode::KONTRAKTOR],
        ];

        foreach ($users as [$name, $username, $email, $role]) {
            User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => 'password123',
                    'role_id' => $roles[$role->value],
                    'is_active' => true,
                ]
            );
        }
    }
}
