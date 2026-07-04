<?php

namespace Database\Seeders\Users;

use App\Enums\Users\PermissionEnum;
use App\Models\Users\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach(PermissionEnum::cases() as $permission){
            Permission::firstOrCreate(['name' => $permission->value],['description' => $permission->description()]);
        }
    }
}
