<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Authorization\Access;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Access::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Access::ROLE_PERMISSIONS as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        $standardRole = Role::findByName(Access::STANDARD_USER, 'web');
        User::query()->doesntHave('roles')->orderBy('id')->eachById(fn (User $user) => $user->assignRole($standardRole));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
