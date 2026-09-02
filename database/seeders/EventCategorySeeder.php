<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['conference', 'Conference', 'مؤتمر'], ['workshop', 'Workshop', 'ورشة عمل'],
            ['meeting', 'Meeting', 'اجتماع'], ['training', 'Training', 'تدريب'], ['community', 'Community', 'مجتمعي'],
        ];
        foreach ($items as $order => [$slug, $en, $ar]) {
            $category = EventCategory::updateOrCreate(['slug' => $slug], ['display_order' => $order + 1, 'is_active' => true]);
            $category->translations()->updateOrCreate(['locale' => 'en'], ['name' => $en]);
            $category->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $ar]);
        }
    }
}
