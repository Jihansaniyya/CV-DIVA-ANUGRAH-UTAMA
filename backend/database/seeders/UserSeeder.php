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
            ['Administrator Sistem', 'admin@divaanugrahutama.co.id', RoleCode::ADMIN],
            ['Nisa Amelia', 'nisa@divaanugrahutama.co.id', RoleCode::QS],
            ['Jihan Safira', 'jihan@divaanugrahutama.co.id', RoleCode::QS],
            ["A'id Maghfur", 'aid@divaanugrahutama.co.id', RoleCode::KONTRAKTOR],
            ['Abdul Muiz', 'muiz@divaanugrahutama.co.id', RoleCode::KONTRAKTOR],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password123',
                    'role_id' => $roles[$role->value],
                    'is_active' => true,
                ]
            );
        }
    }
}
