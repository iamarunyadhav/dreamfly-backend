<?php

use Illuminate\Support\Facades\Route;
use Modules\Clients\Http\Controllers\ClientAdminSummaryController;
use Modules\Clients\Http\Controllers\ClientApplicationUnitController;
use Modules\Clients\Http\Controllers\ClientCaseClosureController;
use Modules\Clients\Http\Controllers\ClientDocumentPrepUnitController;
use Modules\Clients\Http\Controllers\ClientNotesController;
use Modules\Clients\Http\Controllers\ClientProfileController;
use Modules\Clients\Http\Controllers\ClientResponsibilityNoticeController;
use Modules\Clients\Http\Controllers\ClientsController;
use Modules\Clients\Http\Controllers\CountriesController;
use Modules\Clients\Http\Controllers\AuthorityRequestsController;
use Modules\Clients\Http\Controllers\DocumentationTasksController;
use Modules\Clients\Http\Controllers\SupervisorReviewController;
use Modules\Clients\Http\Controllers\TaskQueuesController;
use Modules\Clients\Http\Controllers\VisaDecisionController;
use Modules\Clients\Http\Controllers\VisaSubmissionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Both teams executing documentation_tasks rows need their own queue -
    // Correction Unit (documentation-unit.view) creates/owns them, Documentation
    // Unit (document-prep-unit.view) works the ones assigned to it.
    Route::get('tasks/my', [TaskQueuesController::class, 'my'])->middleware('permission:documentation-unit.view|document-prep-unit.view')->name('tasks.my');
    Route::get('tasks/pending', [TaskQueuesController::class, 'pending'])->middleware('permission:documentation-unit.view|document-prep-unit.view')->name('tasks.pending');
    Route::get('tasks/overdue', [TaskQueuesController::class, 'overdue'])->middleware('permission:documentation-unit.view|document-prep-unit.view')->name('tasks.overdue');

    // Shared native/destination country list - readable by anyone logged in;
    // adding one is gated in the controller since it's used from both the
    // Common Users and Clients forms.
    Route::get('countries', [CountriesController::class, 'index'])->name('countries.index');
    Route::post('countries', [CountriesController::class, 'store'])->name('countries.store');

    Route::get('clients', [ClientsController::class, 'index'])->middleware('permission:clients.view')->name('clients.index');
    Route::post('clients', [ClientsController::class, 'store'])->middleware('permission:clients.create')->name('clients.store');
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->middleware('permission:clients.view')->name('clients.profile.show');
    Route::post('clients/{client}/profile-photo', [ClientsController::class, 'uploadProfilePhoto'])->middleware('permission:clients.edit')->name('clients.profile-photo.store');
    Route::get('clients/{client}/additional-charges', [ClientsController::class, 'additionalCharges'])
        ->middleware('permission:clients.view')->name('clients.additional-charges.index');
    Route::post('clients/{client}/additional-charges', [ClientsController::class, 'storeAdditionalCharge'])
        ->middleware(['permission:clients.edit', 'permission:payments.create'])->name('clients.additional-charges.store');
    Route::delete('clients/{client}/additional-charges/{additionalCharge}', [ClientsController::class, 'destroyAdditionalCharge'])
        ->middleware(['permission:clients.edit', 'permission:payments.delete'])->name('clients.additional-charges.destroy');
    Route::get('clients/{client}/notes', [ClientNotesController::class, 'index'])->middleware('permission:clients.view')->name('clients.notes.index');
    Route::post('clients/{client}/notes', [ClientNotesController::class, 'store'])->middleware('permission:clients.edit')->name('clients.notes.store');
    Route::delete('clients/{client}/notes/{note}', [ClientNotesController::class, 'destroy'])->middleware('permission:clients.edit')->name('clients.notes.destroy');
    Route::get('clients/{client}', [ClientsController::class, 'show'])->middleware('permission:clients.view')->name('clients.show');
    Route::get('clients/{client}/admin-summary', [ClientAdminSummaryController::class, 'show'])
        ->middleware('permission:clients.view')->name('clients.admin-summary.show');
    Route::put('clients/{client}/admin-summary', [ClientAdminSummaryController::class, 'saveDraft'])
        ->middleware('permission:clients.edit')->name('clients.admin-summary.save');
    Route::post('clients/{client}/admin-summary/complete', [ClientAdminSummaryController::class, 'complete'])
        ->middleware('permission:clients.edit')->name('clients.admin-summary.complete');
    Route::post('clients/{client}/admin-summary/generate-docx', [ClientAdminSummaryController::class, 'generateDocx'])
        ->middleware('permission:clients.edit')->name('clients.admin-summary.generate-docx');
    Route::get('clients/{client}/application-unit', [ClientApplicationUnitController::class, 'show'])
        ->middleware('permission:application-unit.view')->name('clients.application-unit.show');
    Route::get('clients/{client}/application-unit/checklist-defaults', [ClientApplicationUnitController::class, 'checklistDefaults'])
        ->middleware('permission:application-unit.view')->name('clients.application-unit.checklist-defaults');
    Route::put('clients/{client}/application-unit', [ClientApplicationUnitController::class, 'saveDraft'])
        ->middleware('permission:application-unit.update')->name('clients.application-unit.save');
    Route::post('clients/{client}/application-unit/complete', [ClientApplicationUnitController::class, 'complete'])
        ->middleware('permission:application-unit.complete')->name('clients.application-unit.complete');
    Route::post('clients/{client}/application-unit/generate-docx', [ClientApplicationUnitController::class, 'generateDocx'])
        ->middleware('permission:application-unit.generate')->name('clients.application-unit.generate-docx');
    // The runtime checklist is read/uploaded to well beyond Application Unit
    // itself - Correction Unit verifies it and Documentation Unit downloads/
    // uploads to it, so both of their own view/update permissions qualify too.
    Route::get('clients/{client}/application-unit/checklist-items', [ClientApplicationUnitController::class, 'checklistItems'])
        ->middleware('permission:application-unit.view|documentation-unit.view|document-prep-unit.view')->name('clients.application-unit.checklist-items');
    Route::post('clients/{client}/application-unit/checklist-file', [ClientApplicationUnitController::class, 'uploadChecklistFile'])
        ->middleware('permission:application-unit.update|documentation-unit.update|document-prep-unit.update|files.create')->name('clients.application-unit.checklist-file');
    Route::patch('clients/{client}/application-unit/checklist-items/{item}/verify', [ClientApplicationUnitController::class, 'verifyChecklistItem'])
        ->middleware('permission:files.verify')->name('clients.application-unit.checklist-items.verify');
    Route::patch('clients/{client}/application-unit/checklist-items/{item}/reject', [ClientApplicationUnitController::class, 'rejectChecklistItem'])
        ->middleware('permission:files.verify')->name('clients.application-unit.checklist-items.reject');
    Route::get('clients/{client}/responsibility-notice', [ClientResponsibilityNoticeController::class, 'show'])
        ->middleware('permission:clients.view')->name('clients.responsibility-notice.show');
    Route::put('clients/{client}/responsibility-notice', [ClientResponsibilityNoticeController::class, 'saveDraft'])
        ->middleware('permission:clients.edit')->name('clients.responsibility-notice.save');
    Route::post('clients/{client}/responsibility-notice/generate', [ClientResponsibilityNoticeController::class, 'generate'])
        ->middleware('permission:clients.edit')->name('clients.responsibility-notice.generate');
    Route::post('clients/{client}/responsibility-notice/share', [ClientResponsibilityNoticeController::class, 'share'])
        ->middleware('permission:communications.send')->name('clients.responsibility-notice.share');
    Route::post('clients/{client}/responsibility-notice/acknowledge', [ClientResponsibilityNoticeController::class, 'acknowledge'])
        ->middleware('permission:clients.edit')->name('clients.responsibility-notice.acknowledge');
    Route::post('clients/{client}/responsibility-notice/revoke-acknowledgement', [ClientResponsibilityNoticeController::class, 'revokeAcknowledgement'])
        ->middleware('permission:clients.edit')->name('clients.responsibility-notice.revoke');
    Route::get('clients/{client}/supervisor-review', [SupervisorReviewController::class, 'show'])
        ->middleware('permission:supervisor-review.view')->name('clients.supervisor-review.show');
    Route::post('clients/{client}/supervisor-review/approve', [SupervisorReviewController::class, 'approve'])
        ->middleware('permission:supervisor-review.approve')->name('clients.supervisor-review.approve');
    Route::post('clients/{client}/supervisor-review/send-back', [SupervisorReviewController::class, 'sendBack'])
        ->middleware('permission:supervisor-review.send_back')->name('clients.supervisor-review.send-back');
    Route::post('clients/{client}/supervisor-review/comments', [SupervisorReviewController::class, 'storeComment'])
        ->middleware('permission:supervisor-review.comment')->name('clients.supervisor-review.comments.store');
    Route::delete('clients/{client}/supervisor-review/comments/{comment}', [SupervisorReviewController::class, 'destroyComment'])
        ->middleware('permission:supervisor-review.comment')->name('clients.supervisor-review.comments.destroy');

    Route::get('clients/{client}/visa-submission', [VisaSubmissionController::class, 'show'])
        ->middleware('permission:clients.view')->name('clients.visa-submission.show');
    Route::put('clients/{client}/visa-submission', [VisaSubmissionController::class, 'save'])
        ->middleware('permission:clients.edit')->name('clients.visa-submission.save');
    Route::post('clients/{client}/visa-submission/receipt', [VisaSubmissionController::class, 'uploadReceipt'])
        ->middleware('permission:clients.edit')->name('clients.visa-submission.receipt');

    Route::get('clients/{client}/authority-requests', [AuthorityRequestsController::class, 'index'])
        ->middleware('permission:clients.view')->name('clients.authority-requests.index');
    Route::post('clients/{client}/authority-requests', [AuthorityRequestsController::class, 'store'])
        ->middleware('permission:clients.edit')->name('clients.authority-requests.store');
    Route::put('clients/{client}/authority-requests/{authorityRequest}', [AuthorityRequestsController::class, 'update'])
        ->middleware('permission:clients.edit')->name('clients.authority-requests.update');
    Route::post('clients/{client}/authority-requests/{authorityRequest}/response-file', [AuthorityRequestsController::class, 'uploadResponse'])
        ->middleware('permission:clients.edit')->name('clients.authority-requests.response-file');
    Route::delete('clients/{client}/authority-requests/{authorityRequest}', [AuthorityRequestsController::class, 'destroy'])
        ->middleware('permission:clients.delete')->name('clients.authority-requests.destroy');

    Route::post('clients/{client}/visa-decision', [VisaDecisionController::class, 'record'])
        ->middleware('permission:clients.edit')->name('clients.visa-decision.record');
    Route::post('clients/{client}/visa-decision/document', [VisaDecisionController::class, 'uploadDecisionDocument'])
        ->middleware('permission:clients.edit')->name('clients.visa-decision.document');

    Route::get('clients/{client}/case-closure', [ClientCaseClosureController::class, 'show'])
        ->middleware('permission:clients.view')->name('clients.case-closure.show');
    Route::put('clients/{client}/case-closure', [ClientCaseClosureController::class, 'saveDraft'])
        ->middleware('permission:clients.edit')->name('clients.case-closure.save');
    Route::post('clients/{client}/case-closure/archive', [ClientCaseClosureController::class, 'archive'])
        ->middleware('permission:clients.edit')->name('clients.case-closure.archive');
    Route::post('clients/{client}/case-closure/complete', [ClientCaseClosureController::class, 'complete'])
        ->middleware('permission:clients.edit')->name('clients.case-closure.complete');

    // documentation_tasks rows are shared: Correction Unit creates/manages them
    // (documentation-unit.*), Documentation Unit only lists/updates the ones
    // assigned to it (document-prep-unit.*) - it never gets create/delete.
    Route::get('clients/{client}/documentation-tasks', [DocumentationTasksController::class, 'index'])
        ->middleware('permission:documentation-unit.view|document-prep-unit.view')->name('clients.documentation-tasks.index');
    Route::post('clients/{client}/documentation-tasks/confirm-assignments', [DocumentationTasksController::class, 'confirmAssignments'])
        ->middleware('permission:clients.edit')->name('clients.documentation-tasks.confirm-assignments');
    Route::post('clients/{client}/documentation-tasks', [DocumentationTasksController::class, 'store'])
        ->middleware('permission:documentation-unit.create')->name('clients.documentation-tasks.store');
    Route::put('clients/{client}/documentation-tasks/{task}', [DocumentationTasksController::class, 'update'])
        ->middleware('permission:documentation-unit.update|document-prep-unit.update')->name('clients.documentation-tasks.update');
    Route::post('clients/{client}/documentation-tasks/{task}/file', [DocumentationTasksController::class, 'uploadFile'])
        ->middleware('permission:documentation-unit.update|document-prep-unit.update')->name('clients.documentation-tasks.upload-file');
    Route::delete('clients/{client}/documentation-tasks/{task}', [DocumentationTasksController::class, 'destroy'])
        ->middleware('permission:documentation-unit.delete')->name('clients.documentation-tasks.destroy');

    Route::post('clients/{client}/document-prep-unit/generate-summary', [ClientDocumentPrepUnitController::class, 'generateSummary'])
        ->middleware('permission:document-prep-unit.view')->name('clients.document-prep-unit.generate-summary');
    Route::post('clients/{client}/document-prep-unit/complete', [ClientDocumentPrepUnitController::class, 'complete'])
        ->middleware('permission:document-prep-unit.complete')->name('clients.document-prep-unit.complete');
    Route::put('clients/{client}', [ClientsController::class, 'update'])->middleware('permission:clients.edit')->name('clients.update');
    Route::patch('clients/{client}', [ClientsController::class, 'update'])->middleware('permission:clients.edit');
    Route::delete('clients/{client}', [ClientsController::class, 'destroy'])->middleware('permission:clients.delete')->name('clients.destroy');
    Route::post('clients/{clientId}/restore', [ClientsController::class, 'restore'])
        ->middleware('permission:clients.edit')->name('clients.restore');
});
