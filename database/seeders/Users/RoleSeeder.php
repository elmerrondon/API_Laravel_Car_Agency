<?php

namespace Database\Seeders\Users;

use App\Enums\Users\RoleEnum;
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
        foreach(RoleEnum::cases() as $role){
            Role::firstOrCreate(['name' => $role->value], ['description' => $role->description()]);
        }
    }
}
