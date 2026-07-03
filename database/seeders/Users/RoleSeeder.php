<?php

namespace Database\Seeders\Users;

use App\Models\Users\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'User Admin'],
            ['name' => 'manager', 'description' => 'User Manager'],
            ['name' => 'seller', 'description' => 'User Seller']
        ];

        foreach($roles as $role){
            Role::firstOrCreate($role);
        }
    }
}
