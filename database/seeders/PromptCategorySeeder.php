<?php

namespace Database\Seeders;

use App\Models\PromptCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PromptCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['writing', 'Writing', 'الكتابة', 'Create, review, and improve written content.', 'إنشاء المحتوى المكتوب ومراجعته وتحسينه.', 'edit'],
            ['email', 'Email', 'البريد الإلكتروني', 'Draft clear professional email content.', 'صياغة محتوى بريد إلكتروني مهني وواضح.', 'mail'],
            ['marketing', 'Marketing', 'التسويق', 'Plan and produce marketing content.', 'تخطيط المحتوى التسويقي وإنتاجه.', 'megaphone'],
            ['social-media', 'Social Media', 'وسائل التواصل الاجتماعي', 'Create content for social channels.', 'إنشاء محتوى لقنوات التواصل الاجتماعي.', 'share'],
            ['translation', 'Translation', 'الترجمة', 'Translate and localize content.', 'ترجمة المحتوى وتوطينه.', 'languages'],
            ['design', 'Design', 'التصميم', 'Prepare design concepts and briefs.', 'إعداد مفاهيم وموجزات التصميم.', 'palette'],
            ['events', 'Events', 'الفعاليات', 'Prepare event plans and communications.', 'إعداد خطط الفعاليات واتصالاتها.', 'calendar'],
            ['reports', 'Reports', 'التقارير', 'Structure and write business reports.', 'هيكلة تقارير الأعمال وكتابتها.', 'file-text'],
            ['hr', 'HR', 'الموارد البشرية', 'Support people and workplace communication.', 'دعم تواصل الموظفين وبيئة العمل.', 'users'],
            ['corporate-communication', 'Corporate Communication', 'الاتصال المؤسسي', 'Create consistent corporate communication.', 'إنشاء اتصالات مؤسسية متسقة.', 'building'],
            ['general', 'General', 'عام', 'Prompts that do not belong to a specialist category.', 'موجّهات لا تندرج ضمن فئة متخصصة.', 'folder'],
        ];

        foreach ($categories as $index => [$slug, $nameEn, $nameAr, $descriptionEn, $descriptionAr, $icon]) {
            $category = PromptCategory::withTrashed()->updateOrCreate(['slug' => $slug], [
                'icon' => $icon,
                'display_order' => $index + 1,
                'is_active' => true,
                'deleted_at' => null,
            ]);
            DB::table('prompt_category_translations')->upsert([
                ['category_id' => $category->id, 'locale' => 'en', 'name' => $nameEn, 'description' => $descriptionEn],
                ['category_id' => $category->id, 'locale' => 'ar', 'name' => $nameAr, 'description' => $descriptionAr],
            ], ['category_id', 'locale'], ['name', 'description']);
        }
    }
}
