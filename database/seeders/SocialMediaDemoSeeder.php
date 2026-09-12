<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\SocialAccountMetric;
use App\Models\SocialAudienceMetric;
use App\Models\SocialCampaign;
use App\Models\SocialContentType;
use App\Models\SocialMediaAccount;
use App\Models\SocialPlatform;
use App\Models\SocialPost;
use App\Models\SocialPostMetric;
use App\Models\SocialTag;
use App\Models\SocialTarget;
use App\Models\User;
use Carbon\CarbonImmutable as Date;
use Illuminate\Database\Seeder;

class SocialMediaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'browser'])) {
            throw new \LogicException('Development fixtures only.');
        }
        $this->call(SocialMediaCatalogSeeder::class);
        $actor = User::where('username', 'admin')->first() ?? User::firstOrFail();
        $account = SocialMediaAccount::firstOrCreate(['identifier' => 'maktoob-demo', 'platform_id' => SocialPlatform::where('slug', 'linkedin')->value('id')], ['name' => 'Maktoob · Demo', 'timezone' => 'Asia/Muscat', 'created_by' => $actor->id]);
        if ($account->posts()->exists()) {
            return;
        }
        $campaigns = collect(['Corporate News', 'Employee Engagement', 'Sustainability', 'Safety', 'CSR', 'Recruitment', 'Company Achievements'])->map(fn ($name) => SocialCampaign::create(['social_account_id' => $account->id, 'name' => $name, 'kind' => 'campaign', 'created_by' => $actor->id]));
        $tags = collect(['Event', 'CSR', 'Employee', 'Achievement', 'Safety', 'Sustainability', 'Recruitment', 'Corporate News'])->map(fn ($name) => SocialTag::create(['social_account_id' => $account->id, 'name' => $name, 'created_by' => $actor->id]));
        $event = Event::factory()->create(['title' => 'OPES 2026', 'organizer_id' => $actor->id, 'created_by' => $actor->id, 'starts_at' => '2026-09-10 08:00:00', 'ends_at' => '2026-09-12 15:00:00', 'status' => 'completed', 'visibility' => 'all_users']);
        $types = SocialContentType::all();
        $start = Date::parse('2026-06-01');
        $followers = 21000;
        SocialAccountMetric::create(['social_account_id' => $account->id, 'starts_on' => '2026-05-31', 'ends_on' => '2026-05-31', 'followers' => $followers]);
        for ($day = 0; $day < 122; $day++) {
            $date = $start->addDays($day);
            $followers += 20 + ($day % 25);
            SocialAccountMetric::create(['social_account_id' => $account->id, 'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString(), 'followers' => $followers, 'new_followers' => 20 + ($day % 25),
                'impressions' => 6000 + $day * 71, 'reactions' => 180 + $day, 'comments' => 12 + $day % 15, 'shares' => 8 + $day % 9, 'clicks' => 90 + $day]);
        }
        for ($i = 0; $i < 28; $i++) {
            $date = Date::parse('2026-09-01')->addDays($i);
            $post = SocialPost::create(['social_account_id' => $account->id, 'platform_post_id' => 'demo-'.$i, 'title' => $i < 8 ? 'OPES 2026 · '.($i + 1) : $campaigns[$i % 7]->name.' · '.($i + 1),
                'content' => $i % 2 ? 'A corporate communication update for our community.' : 'مستجدات التواصل المؤسسي ومشاركة إنجازات فريق العمل.',
                'published_at' => $date->setTime(8 + $i % 8, 0), 'content_type_id' => $types[$i % $types->count()]->id, 'language' => $i % 2 ? 'en' : 'ar',
                'campaign_id' => $campaigns[$i % 7]->id, 'event_id' => $i < 8 ? $event->id : null, 'created_by' => $actor->id]);
            $post->tags()->attach($tags[$i % 8]->id);
            SocialPostMetric::create(['social_post_id' => $post->id, 'starts_on' => $date->toDateString(), 'ends_on' => $date->toDateString(),
                'impressions' => 2000 + $i * 600, 'reactions' => $i === 27 ? 0 : 50 + $i * 12, 'comments' => $i === 27 ? 0 : 5 + $i, 'shares' => $i === 27 ? 0 : 3 + $i, 'clicks' => $i === 27 ? 0 : 20 + $i * 6]);
        }
        foreach (['location' => ['Oman', 'UAE', 'Saudi Arabia'], 'industry' => ['Energy', 'Engineering', 'Oil & Gas'], 'job_function' => ['Engineering', 'Operations', 'Management'], 'seniority' => ['Manager', 'Senior', 'Director']] as $dim => $values) {
            foreach ($values as $i => $value) {
                SocialAudienceMetric::create(['social_account_id' => $account->id, 'metric_date' => '2026-09-30', 'dimension' => $dim, 'value' => $value, 'percentage' => [60, 30, 10][$i], 'audience_count' => [12000, 6000, 2000][$i], 'created_by' => $actor->id]);
            }
        }
        foreach (['new_followers' => 1200, 'engagement_rate' => 8, 'impressions' => 500000, 'clicks' => 8900, 'posts' => 30, 'engagements' => 42000] as $metric => $value) {
            foreach ([9, 0] as $month) {
                SocialTarget::create(['social_account_id' => $account->id, 'year' => 2026, 'month' => $month, 'metric' => $metric, 'value' => $month ? $value : $value * ($metric === 'engagement_rate' ? 1 : 12), 'created_by' => $actor->id]);
            }
        }
    }
}
