<?php

namespace Database\Seeders\Users;

use App\Enums\Users\RoleEnum;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::where('is_active', true)->orderBy('id')->first();
        $password = config('seeders.user_password');

        $users = [
            [
                'name' => 'Ana',
                'last_name' => 'Torres',
                'document_number' => 'V-10000001',
                'code' => 'USR-0001',
                'email' => 'admin@caragency.test',
                'role' => RoleEnum::ADMIN,
            ],
            [
                'name' => 'Manuel',
                'last_name' => 'Rojas',
                'document_number' => 'V-10000002',
                'code' => 'USR-0002',
                'email' => 'manager@caragency.test',
                'role' => RoleEnum::MANAGER,
            ],
            [
                'name' => 'Valeria',
                'last_name' => 'Méndez',
                'document_number' => 'V-10000003',
                'code' => 'USR-0003',
                'email' => 'vendedor@caragency.test',
                'role' => RoleEnum::SELLER,
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'document_number' => $data['document_number'],
                    'code' => $data['code'],
                    'password' => $password,
                    'is_active' => true,
                ]
            );

            $role = Role::where('name', $data['role']->value)->firstOrFail();
            $user->roles()->sync([$role->id]);

            if ($branch) {
                $user->branches()->sync([$branch->id]);
            }
        }
    }
}
