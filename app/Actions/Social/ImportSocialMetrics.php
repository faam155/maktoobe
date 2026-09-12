<?php

namespace App\Actions\Social;

use App\Actions\Audit\RecordAudit;
use App\Contracts\PrivateFileScanner;
use App\Models\SocialImport;
use App\Models\SocialMediaAccount;
use App\Models\SocialSyncLog;
use App\Models\User;
use App\Services\Social\SocialAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

class ImportSocialMetrics
{
    public const HEADER = ['subject', 'post_id', 'starts_on', 'ends_on', 'impressions', 'reactions', 'comments', 'shares', 'clicks', 'followers', 'new_followers'];

    public function preview(User $actor, SocialMediaAccount $account, UploadedFile $file): SocialImport
    {
        app(SocialAccess::class)->authorize($actor, 'import', $account);
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip']])->validate();
        app(PrivateFileScanner::class)->assertSafe($file);
        $rows = [];
        try {
            if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
                $zip = new ZipArchive;
                if ($zip->open($file->getRealPath()) !== true) {
                    throw new \RuntimeException;
                }
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $size += $stat['size'];
                    if ($size > 20000000 || $zip->numFiles > 200 || str_contains($stat['name'], 'vbaProject') || str_contains($stat['name'], 'externalLinks')) {
                        $zip->close();
                        throw new \RuntimeException;
                    }
                }
                $zip->close();
                $reader = new Reader;
                $reader->open($file->getRealPath());
                try {
                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            foreach ($row->cells as $cell) {
                                if ($cell instanceof FormulaCell) {
                                    throw new \RuntimeException;
                                }
                            }
                            $rows[] = $row->toArray();
                            if (count($rows) > config('social.max_import_rows') + 1) {
                                throw new \RuntimeException;
                            }
                        }
                        break;
                    }
                } finally {
                    $reader->close();
                    // OpenSpout's row callbacks form a cycle. Release an aborted XML
                    // iterator now so Windows can remove the uploaded temporary file.
                    unset($sheet, $reader);
                    gc_collect_cycles();
                }
            } else {
                $handle = fopen($file->getRealPath(), 'rb');
                try {
                    while (($row = fgetcsv($handle, 10000, ',', '"', '')) !== false) {
                        $rows[] = $row;
                        if (count($rows) > config('social.max_import_rows') + 1) {
                            throw new \RuntimeException;
                        }
                    }
                } finally {
                    fclose($handle);
                }
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => __('social.invalid_file')]);
        }
        $header = array_shift($rows);
        if ($header !== self::HEADER || ! $rows) {
            throw ValidationException::withMessages(['file' => __('social.invalid_template')]);
        }
        $valid = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            try {
                if (count($row) !== count(self::HEADER)) {
                    throw ValidationException::withMessages(['row' => __('social.invalid_template')]);
                }
                $data = array_combine(self::HEADER, array_map(fn ($x) => $x === '' ? null : $x, $row));
                $validated = app(SaveSocialMetric::class)->validate($data);
                $identity = implode(':', [$validated['subject'], $validated['post_id'] ?? 0, $validated['starts_on'], $validated['ends_on']]);
                if (isset($seen[$identity])) {
                    throw ValidationException::withMessages(['row' => __('social.duplicate')]);
                }
                $seen[$identity] = true;
                $valid[] = $validated;
            } catch (ValidationException $e) {
                $errors[] = ['row' => $i + 2, 'messages' => $e->errors()];
            }
        }
        // Validate database relationships, overlapping intervals and duplicates inside a rolled-back transaction.
        DB::beginTransaction();
        try {
            foreach ($valid as $i => $row) {
                try {
                    app(SaveSocialMetric::class)->handle($actor, $account, $row, 'import');
                } catch (\Throwable) {
                    $errors[] = ['row' => $i + 2, 'messages' => ['row' => [__('social.import_conflict')]]];
                }
            }
        } finally {
            DB::rollBack();
        }
        $json = json_encode($valid, JSON_THROW_ON_ERROR);

        return SocialImport::create(['social_account_id' => $account->id, 'user_id' => $actor->id, 'status' => 'preview', 'preview' => $valid, 'errors' => $errors, 'sha256' => hash('sha256', $json), 'rows' => count($rows)]);
    }

    public function confirm(User $actor, SocialMediaAccount $account, SocialImport $import): void
    {
        app(SocialAccess::class)->authorize($actor, 'import', $account);
        DB::transaction(function () use ($actor, $account, $import) {
            SocialMediaAccount::lockForUpdate()->findOrFail($account->id);
            $import = SocialImport::lockForUpdate()->findOrFail($import->id);
            abort_unless($import->social_account_id === $account->id && $import->user_id === $actor->id, 404);
            if ($import->status === 'confirmed') {
                return;
            }
            abort_unless($import->status === 'preview' && ! $import->errors && $import->created_at->gt(now()->subDay()), 409);
            abort_unless(hash_equals($import->sha256, hash('sha256', json_encode($import->preview, JSON_THROW_ON_ERROR))), 409);
            $created = 0;
            $updated = 0;
            foreach ($import->preview as $row) {
                $metric = app(SaveSocialMetric::class)->handle($actor, $account, $row, 'import');
                $metric->wasRecentlyCreated ? $created++ : $updated++;
            }
            $import->update(['status' => 'confirmed']);
            SocialSyncLog::create(['social_account_id' => $account->id, 'user_id' => $actor->id, 'type' => 'import', 'status' => 'successful', 'processed' => $import->rows, 'created' => $created, 'updated' => $updated, 'started_at' => now(), 'completed_at' => now()]);
            app(RecordAudit::class)->handle($actor->id, 'social.import.confirmed', 'social', $account->id);
        });
    }
}
