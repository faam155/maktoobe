<?php

namespace App\Http\Controllers\Social;

use App\Actions\Social\DistributeAnnualTarget;
use App\Actions\Social\ManageSocialCatalog;
use App\Models\Event;
use App\Models\SocialCampaign;
use App\Models\SocialContentType;
use App\Models\SocialMediaAccount;
use App\Models\SocialTag;
use App\Models\SocialTarget;
use App\Queries\Social\SocialAnalyticsQuery;
use App\Services\Events\EventAccess;
use App\Services\Social\SocialAccess;
use Illuminate\Http\Request;

class WorkspaceController
{
    public function options(Request $r, SocialMediaAccount $account): array
    {
        return ['types' => SocialContentType::where('active', true)->get(),
            'campaigns' => SocialCampaign::where('social_account_id', $account->id)->orderBy('name')->get(),
            'tags' => SocialTag::where('social_account_id', $account->id)->orderBy('name')->get(),
            'events' => app(EventAccess::class)->visibleTo(Event::query(), $r->user())->orderByDesc('starts_at')->limit(300)->get(['id', 'title'])];
    }

    public function accounts(Request $r)
    {
        app(SocialAccess::class)->authorize($r->user(), 'settings.manage');

        return view('social.accounts', ['accounts' => SocialMediaAccount::with('platform')->whereHas('platform', fn ($q) => $q->where('slug', 'linkedin'))->paginate(20)]);
    }

    public function accountSave(Request $r, ManageSocialCatalog $action)
    {
        $id = $r->validate(['id' => ['nullable', 'integer', 'min:1']])['id'] ?? null;
        $account = $action->account($r->user(), $r->all(), $id ? SocialMediaAccount::findOrFail($id) : null);

        return back()->with('status', __('social.saved'));
    }

    public function index(Request $r, SocialMediaAccount $account, string $kind)
    {
        abort_unless(isset(ManageSocialCatalog::TYPES[$kind]), 404);
        [$class,$ability] = ManageSocialCatalog::TYPES[$kind];
        $read = match ($kind) {
            'target' => 'targets.view','audience' => 'audience.view',default => $ability
        };
        app(SocialAccess::class)->authorize($r->user(), $read, $account);
        $items = $class::where('social_account_id', $account->id)->latest('id')->paginate(20);
        $editId = $r->validate(['edit' => ['nullable', 'integer', 'min:1']])['edit'] ?? null;
        $edit = $editId ? $class::where('social_account_id', $account->id)->findOrFail($editId) : null;

        return view('social.catalog', compact('account', 'kind', 'items', 'edit', 'ability'));
    }

    public function save(Request $r, SocialMediaAccount $account, string $kind, ManageSocialCatalog $action)
    {
        $id = $r->validate(['id' => ['nullable', 'integer', 'min:1']])['id'] ?? null;
        $action->save($r->user(), $account, $kind, $r->all(), $id);

        return redirect()->route('social.catalog', [$account, $kind])->with('status', __('social.saved'));
    }

    public function delete(Request $r, SocialMediaAccount $account, string $kind, int $id, ManageSocialCatalog $action)
    {
        $action->delete($r->user(), $account, $kind, $id);

        return back()->with('status', __('social.saved'));
    }

    public function settings(Request $r, SocialMediaAccount $account)
    {
        app(SocialAccess::class)->authorize($r->user(), 'settings.manage', $account);

        return view('social.settings', ['account' => $account, 'settings' => app(SocialAnalyticsQuery::class)->settings($account)]);
    }

    public function settingsSave(Request $r, SocialMediaAccount $account, ManageSocialCatalog $action)
    {
        $action->settings($r->user(), $account, $r->all());

        return back()->with('status', __('social.saved'));
    }

    public function distribute(Request $r, SocialMediaAccount $account, SocialTarget $target, DistributeAnnualTarget $action)
    {
        $action->handle($r->user(), $account, $target);

        return back()->with('status', __('social.saved'));
    }
}
