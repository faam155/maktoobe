<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\Process\Process;

class SocialExport
{
    public function download(array $data, string $format)
    {
        $disk = Storage::disk('local');
        $key = 'social-exports/'.Str::uuid().'.'.$format;
        $path = $disk->path($key);
        $disk->makeDirectory('social-exports');
        try {
            if ($format === 'xlsx') {
                $this->xlsx($data, $path);
            } else {
                $htmlKey = 'social-exports/'.Str::uuid().'.html';
                $disk->put($htmlKey, view('social.pdf', $data)->render());
                try {
                    $env = config('social.chromium_binary') ? ['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => config('social.chromium_binary')] : null;
                    $process = new Process([config('social.node_binary', 'node'), base_path('scripts/social-pdf.mjs'), $disk->path($htmlKey), $path], base_path(), $env, null, 45);
                    $process->mustRun();
                } finally {
                    $disk->delete($htmlKey);
                }
            }
            abort_unless(is_file($path), 503);

            return response()->download($path, 'linkedin-'.$data['f']['from'].'-'.$data['f']['to'].'.'.$format, ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
        } catch (\Throwable) {
            $disk->delete($key);
            abort(503, __('social.export_unavailable'));
        }
    }

    private function xlsx(array $d, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $add = function (array $values) use ($writer) {
            // Explicit string cells stop names beginning with "=" from becoming formulas.
            $writer->addRow(new Row(array_map(fn ($v) => is_string($v) ? new StringCell($v) : Cell::fromValue($v), array_values($values))));
        };
        $add([__('social.title'), $d['account']->name]);
        $add([__('social.period'), $d['f']['from'], $d['f']['to']]);
        $add([__('social.generated'), now()->toIso8601String()]);
        $add([__('social.filters'), json_encode($d['f'], JSON_UNESCAPED_UNICODE)]);
        $add([__('social.formula'), implode(' + ', $d['settings']['engagement_components']).' / impressions × 100']);
        $add([__('social.metric'), __('social.value'), __('social.compare').' '.$d['f']['comparison_from'].' — '.$d['f']['comparison_to'], __('social.change')]);
        foreach ($d['metrics'] as $key => $value) {
            $add([__('social.'.$key), $value, $d['previous'][$key] ?? null, $d['changes'][$key]['percent'] ?? null]);
        }
        $add([__('social.summary'), $d['summary']]);
        $add([__('social.score'), $d['score']['value'], __('social.'.$d['score']['status'])]);
        foreach (['content_type_id', 'campaign_id', 'event_id'] as $key) {
            $writer->addNewSheetAndMakeItCurrent();
            $add([__('social.'.$key), __('social.posts'), __('social.impressions'), __('social.engagements'), __('social.engagement_rate')]);
            foreach ($d['groups'][$key] as $row) {
                $add([$row->name, $row->posts, $row->impressions, $row->engagements, $row->engagement_rate]);
            }
        }
        $writer->addNewSheetAndMakeItCurrent();
        $add([__('social.top_posts'), __('social.impressions'), __('social.engagements'), __('social.clicks')]);
        foreach ($d['top'] as $p) {
            $add([$p->title, $p->impressions, $p->engagements, $p->clicks]);
        }
        $writer->addNewSheetAndMakeItCurrent();
        $add([__('social.audience'), __('social.value'), __('social.audience_count'), __('social.percentage')]);
        foreach ($d['audience'] as $dimension => $rows) {
            foreach ($rows as $row) {
                $add([__('social.'.$dimension), $row->value, $row->audience_count, $row->percentage]);
            }
        }
        $writer->addNewSheetAndMakeItCurrent();
        $add([__('social.trend'), __('social.impressions'), __('social.engagements'), __('social.clicks'), __('social.followers')]);
        foreach ($d['trend'] as $row) {
            $add($row);
        }
        $writer->addNewSheetAndMakeItCurrent();
        $add([__('social.targets'), __('social.period'), __('social.value'), __('social.actual'), __('social.achievement')]);
        foreach ($d['targets'] as $row) {
            $add([__('social.'.$row->metric), $row->year.'/'.($row->month ?: __('social.annual')), $row->value, $row->actual, $row->achievement]);
        }
        foreach ($d['insights'] ?? [] as $row) {
            if ($row->status === 'completed') {
                $add([__('social.ai_insights'), $row->content]);
                $add([__('social.recommendations'), $row->recommendations]);
            }
        }
        $writer->close();
    }
}
