<?php

namespace Tests\Feature\Folders;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use Tests\TestCase;

class FolderArchiveBackfillCommandTest extends TestCase
{
    public function test_backfill_archives_already_converted_leads_still_sitting_in_their_country_folder(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $lead = CommonUser::create([
            'full_name' => 'Legacy Converted Lead',
            'country' => 'France',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'paid_amount' => 100000,
            'status' => 'converted',
        ]);
        app(FolderService::class)->createLeadFolderTree($lead, $user->id);

        $leadRoot = Folder::where('common_user_id', $lead->id)
            ->whereHas('parent', fn ($q) => $q->whereNull('client_id')->whereNull('common_user_id'))
            ->first();
        $this->assertSame('France', $leadRoot->parent->name);

        Artisan::call('folders:archive-converted-leads');

        $archivedRoot = Folder::where('name', 'Archived')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Common Users')->whereNull('parent_id'))
            ->first();
        $this->assertNotNull($archivedRoot);
        $this->assertSame($archivedRoot->id, $leadRoot->refresh()->parent_id);
    }

    public function test_backfill_does_not_move_a_lead_that_is_not_converted(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $lead = CommonUser::create([
            'full_name' => 'Still A Lead',
            'country' => 'Germany',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
        app(FolderService::class)->createLeadFolderTree($lead, $user->id);
        $leadRoot = Folder::where('common_user_id', $lead->id)->whereHas('parent', fn ($q) => $q->where('name', 'Germany'))->first();

        Artisan::call('folders:archive-converted-leads');

        $this->assertSame('Germany', $leadRoot->refresh()->parent->name);
    }
}
