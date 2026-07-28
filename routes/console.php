<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Clients\Services\AuthorityRequestDeadlineService;
use Modules\Clients\Services\DocumentationTaskDeadlineService;
use Modules\Communications\Services\AlertDispatcher;
use Modules\Invoices\Services\InvoiceService;

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
