<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\AuthorityRequestDeadlineService;
use Modules\Clients\Services\DocumentationTaskDeadlineService;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Communications\Services\AlertDispatcher;
use Modules\Folders\Services\FolderService;
use Modules\Invoices\Services\InvoiceService;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documentation-tasks:process-deadlines', function (DocumentationTaskDeadlineService $deadlines) {
    $result = $deadlines->process();

    $this->info("Documentation task deadlines processed. Reminded: {$result['reminded']}, escalated: {$result['escalated']}, overdue: {$result['overdue']}.");
})->purpose('Process Documentation Unit reminders, escalations, and overdue statuses');

Schedule::command('documentation-tasks:process-deadlines')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('authority-requests:process-deadlines', function (AuthorityRequestDeadlineService $deadlines) {
    $result = $deadlines->process();

    $this->info("Authority request deadlines processed. Reminded: {$result['reminded']}, overdue: {$result['overdue']}.");
})->purpose('Remind and escalate authority requests as their due date approaches or passes');

Schedule::command('authority-requests:process-deadlines')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('invoices:mark-overdue', function (InvoiceService $invoices) {
    $count = $invoices->markOverdue();

    $this->info("Invoices marked overdue: {$count}.");
})->purpose('Flag issued/partially-paid invoices past their due date as overdue');

Schedule::command('invoices:mark-overdue')->dailyAt('01:00')->withoutOverlapping();

Artisan::command('alerts:dispatch', function (AlertDispatcher $alerts) {
    $result = $alerts->flushDue();

    $this->info("Alerts dispatched. Sent: {$result['sent']}, failed: {$result['failed']}, skipped: {$result['skipped']}.");
})->purpose('Send configured alerts whose delay has elapsed');

Schedule::command('alerts:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('alerts:prune {--days=90}', function (AlertDispatcher $alerts) {
    $deleted = $alerts->prune((int) $this->option('days'));

    $this->info("Alert dispatch history pruned: {$deleted} row(s).");
})->purpose('Delete alert dispatch history older than the retention window');

Schedule::command('alerts:prune')->weeklyOn(1, '02:00')->withoutOverlapping();

Artisan::command('case-steps:backfill', function (CaseStepService $service) {
    $count = 0;

    Client::whereNotIn('id', CaseStep::select('client_id')->distinct())
        ->chunkById(50, function ($clients) use ($service, &$count) {
            foreach ($clients as $client) {
                $service->initializeForClient($client);
                $count++;
            }
        });

    $this->info("Initialized case_steps for {$count} client(s) that had none.");
})->purpose('One-off backfill: seed case_steps for clients created before the runtime engine was wired in');

Artisan::command('case-steps:insert-document-prep-unit', function () {
    // Inserts one new stage immediately after $afterKey for one client, if it
    // doesn't already have $newKey. A closure (not a top-level function) -
    // this file can be loaded more than once per process, which would make a
    // plain `function` declaration here fatal on "cannot redeclare". Shared
    // by both insertions below so Documentation Unit and Upload Team backfill
    // identically. Returns true if a row was actually inserted.
    $insertStageAfter = function (Client $client, string $afterKey, string $newKey, string $newName, string $ownerRole, int $durationDays): bool {
        if (CaseStep::where('client_id', $client->id)->where('key', $newKey)->exists()) {
            return false;
        }

        $predecessor = CaseStep::where('client_id', $client->id)->where('key', $afterKey)->first();
        if (! $predecessor) {
            return false;
        }

        $insertionOrder = $predecessor->order + 1;

        // Read the step that currently sits right after the predecessor BEFORE
        // shifting anything - its status (not anything further down the chain)
        // is what tells us whether the client is exactly at the insertion boundary.
        $nextStep = CaseStep::where('client_id', $client->id)->where('order', $insertionOrder)->first();

        $predecessorDone = in_array($predecessor->status, ['completed', 'skipped'], true);
        $clientAtBoundary = $predecessorDone && $nextStep?->status === 'in_progress';

        if (! $predecessorDone) {
            $status = 'pending';
        } elseif ($clientAtBoundary) {
            $status = 'in_progress';
        } else {
            // Client already progressed past this point - do not retroactively
            // block finished progress.
            $status = 'completed';
        }

        // Everything at or after the insertion point shifts down one slot to
        // make room. Ordered descending so no two rows ever collide on `order`.
        CaseStep::where('client_id', $client->id)
            ->where('order', '>=', $insertionOrder)
            ->orderByDesc('order')
            ->get()
            ->each(fn ($step) => $step->update(['order' => $step->order + 1]));

        CaseStep::create([
            'client_id' => $client->id,
            'workflow_template_id' => $predecessor->workflow_template_id,
            'key' => $newKey,
            'name' => $newName,
            'order' => $insertionOrder,
            'owner_role' => $ownerRole,
            'status' => $status,
            'duration_days' => $durationDays,
            'requires_checklist' => false,
            'started_at' => $status !== 'pending' ? now() : null,
            'due_at' => $status === 'in_progress' ? now()->addDays($durationDays) : null,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        if ($clientAtBoundary) {
            // The new stage takes over the "in progress" slot - the step that
            // used to be next has not actually started yet.
            $nextStep->update(['status' => 'pending', 'started_at' => null, 'due_at' => null]);
            $client->update(['current_stage' => $newKey]);
        }

        return true;
    };

    $insertedPrep = 0;
    $insertedUpload = 0;
    $skippedNoSteps = 0;

    Client::whereIn('id', CaseStep::select('client_id')->distinct())
        ->chunkById(50, function ($clients) use ($insertStageAfter, &$insertedPrep, &$insertedUpload, &$skippedNoSteps) {
            foreach ($clients as $client) {
                DB::transaction(function () use ($client, $insertStageAfter, &$insertedPrep, &$insertedUpload, &$skippedNoSteps) {
                    if (! CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->exists()) {
                        $skippedNoSteps++;

                        return;
                    }

                    if ($insertStageAfter($client, 'documentation_unit', 'document_prep_unit', 'Documentation Unit', 'Documentation Unit Staff', 5)) {
                        $insertedPrep++;
                    }

                    if ($insertStageAfter($client->refresh(), 'document_prep_unit', 'upload_team', 'Upload Team', 'Upload Team Staff', 2)) {
                        $insertedUpload++;
                    }
                });
            }
        });

    $this->info("Inserted Documentation Unit for {$insertedPrep} client(s), Upload Team for {$insertedUpload} client(s). Skipped (no Correction Unit step found): {$skippedNoSteps}.");
})->purpose('One-off backfill: insert the new Documentation Unit and Upload Team stages for clients whose case_steps predate them');

Artisan::command('folders:archive-converted-leads', function (FolderService $service) {
    $moved = 0;

    CommonUser::where('status', 'converted')
        ->chunkById(50, function ($leads) use ($service, &$moved) {
            foreach ($leads as $lead) {
                $folder = $service->moveConvertedLeadFolderTree($lead);
                if ($folder && $folder->wasChanged('parent_id')) {
                    $moved++;
                }
            }
        });

    $this->info("Moved the folder tree for {$moved} already-converted lead(s).");
})->purpose('One-off backfill: move already-converted leads\' folder trees into Moved > Common Users');

Artisan::command('folders:repair-managed-structure {--user-id=}', function (FolderService $service) {
    $result = $service->repairManagedStructure(
        $this->option('user-id') ? (int) $this->option('user-id') : null,
    );

    $this->info(
        "Folder structure repaired. Clients: {$result['clients_repaired']}, leads: {$result['leads_repaired']}, "
        ."folders removed: {$result['folders_removed']}, files moved: {$result['files_moved']}."
    );
})->purpose('One-off backfill: merge malformed owner folders into Clients/Common Users > country > owner trees');
