<?php

namespace Modules\Clients\Services;

use Illuminate\Support\Collection;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Clients\Models\ClientApplicationUnit;

class ApplicationChecklistRuntimeService
{
    /** Checklist owner => the Application Unit JSON column that backs it. */
    public const OWNER_COLUMNS = [
        'applicant' => 'applicant_checklist',
        'inviter' => 'inviter_checklist',
        'internal' => 'internal_checklist',
    ];

    public static function columnFor(string $owner): string
    {
        return self::OWNER_COLUMNS[$owner] ?? self::OWNER_COLUMNS['applicant'];
    }

    public function sync(ClientApplicationUnit $applicationUnit): Collection
    {
        $items = collect();

        foreach (self::OWNER_COLUMNS as $owner => $column) {
            foreach (($applicationUnit->{$column} ?? []) as $index => $row) {
                if (empty($row['title'])) {
                    continue;
                }

                $item = CaseChecklistItem::updateOrCreate(
                    [
                        'application_unit_id' => $applicationUnit->id,
                        'owner' => $owner,
                        'source_index' => $index,
                    ],
                    [
                        'client_id' => $applicationUnit->client_id,
                        'title' => $row['title'],
                        'status' => $row['status'] ?? 'missing',
                        'is_required' => (bool) ($row['required'] ?? true),
                        'document_required' => true,
                        'linked_file_id' => $row['linked_file_id'] ?? null,
                        'note' => $row['note'] ?? null,
                        'completed_at' => in_array($row['status'] ?? '', ['completed', 'verified'], true) ? now() : null,
                    ],
                );

                $items->push($item);
            }
        }

        return $items;
    }

    public function syncJsonRow(CaseChecklistItem $item): void
    {
        $applicationUnit = $item->applicationUnit;
        if (! $applicationUnit) {
            return;
        }

        $column = self::columnFor((string) $item->owner);
        $rows = $applicationUnit->{$column} ?? [];
        if (! array_key_exists($item->source_index, $rows)) {
            return;
        }

        $rows[$item->source_index] = [
            ...$rows[$item->source_index],
            'status' => $item->status,
            'linked_file_id' => $item->linked_file_id,
            'linked_file_name' => $item->linkedFile?->original_name ?? ($rows[$item->source_index]['linked_file_name'] ?? null),
            'linked_file_url' => $item->linked_file_id ? route('api.files.download', $item->linked_file_id) : null,
            'linked_file_verified' => $item->status === 'verified',
            'rejection_reason' => $item->rejection_reason,
            'verified_at' => $item->verified_at?->toISOString(),
            'rejected_at' => $item->rejected_at?->toISOString(),
        ];

        $applicationUnit->forceFill([$column => array_values($rows)])->save();
    }
}
