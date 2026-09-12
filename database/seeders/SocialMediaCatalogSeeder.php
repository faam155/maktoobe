<?php

namespace Database\Seeders;

use App\Models\SocialContentType;
use App\Models\SocialPlatform;
use App\Support\Authorization\Access;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SocialMediaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        SocialPlatform::firstOrCreate(['slug' => 'linkedin'], ['name' => 'LinkedIn', 'active' => true]);
        foreach (['image' => ['Image', 'صورة'], 'video' => ['Video', 'فيديو'], 'document' => ['Document', 'مستند'], 'link' => ['Link', 'رابط'], 'text' => ['Text', 'نص'], 'article' => ['Article', 'مقال'], 'poll' => ['Poll', 'استطلاع'], 'other' => ['Other', 'أخرى']] as $slug => $labels) {
            SocialContentType::firstOrCreate(['slug' => $slug], ['name' => $labels[0], 'name_ar' => $labels[1]]);
        }
        $permissions = array_values(array_filter(Access::PERMISSIONS, fn ($p) => str_starts_with($p, 'linkedin.')));
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Additive rollout: preserve every existing grant and role.
        Role::where('name', Access::SUPER_ADMINISTRATOR)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
