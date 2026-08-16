<?php

namespace Modules\Clients\Services;

use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Clients\Models\Client;
use Modules\Services\Models\Service;

/**
 * Resolves the default Applicant/Inviter/Internal checklist rows for a
 * client's Application Unit, so the "Checklists" library page is the real
 * source of defaults instead of a disconnected, decorative CRUD screen.
 *
 * Resolution order per owner (applicant/inviter/internal):
 *   1. The client's matching active Service's own attached checklist
 *      templates (published + active), if it has any for that owner.
 *   2. Otherwise the whole published + active library for that owner.
 *   3. Otherwise (library empty, e.g. a fresh install before seeding) a
 *      small hardcoded fallback so the form is never left with nothing.
 */
class ApplicationChecklistDefaultsService
{
    /** @var array<string, list<string>> */
    private const FALLBACK = [
        'applicant' => [
            'Passport (Current)',
            'National Identity Card + English Translation',
            'Birth Certificate + English Translation',
            'Personal Bank Statements (Last 6 Months)',
            'Visa Photo',
            'Statement Of Purpose',
            'Flight Ticket Reservation',
            'Travel Insurance',
        ],
        'inviter' => [
            'Proof Of Legal Status',
            'Residential Address Proof',
            'Employment Letter',
            'Invitation Letter',
        ],
        'internal' => [
            'Application Form Filled',
            'Document Set Cross-Checked',
            'Appointment Booked',
        ],
    ];

    /**
     * @return array{applicant: list<array<string, mixed>>, inviter: list<array<string, mixed>>, internal: list<array<string, mixed>>}
     */
    public function defaultsFor(Client $client): array
    {
        $service = $this->serviceFor($client);

        return [
            'applicant' => $this->rowsFor('applicant', $service),
            'inviter' => $this->rowsFor('inviter', $service),
            'internal' => $this->rowsFor('internal', $service),
        ];
    }

    private function serviceFor(Client $client): ?Service
    {
        if (! $client->service_category) {
            return null;
        }

        return Service::query()
            ->where('category', $client->service_category)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFor(string $owner, ?Service $service): array
    {
        $templates = collect();

        if ($service) {
            $templates = $service->checklistTemplates()
                ->where('owner', $owner)
                ->where('status', 'published')
                ->where('is_active', true)
                ->get();
        }

        if ($templates->isEmpty()) {
            $templates = ChecklistTemplate::query()
                ->where('owner', $owner)
                ->where('status', 'published')
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('id')
                ->get();
        }

        if ($templates->isEmpty()) {
            return collect(self::FALLBACK[$owner] ?? [])
                ->map(fn (string $title) => $this->row($title, true, $owner))
                ->all();
        }

        return $templates
            ->map(fn (ChecklistTemplate $template) => $this->row(
                $template->title,
                (bool) ($template->pivot->is_required ?? $template->is_required),
                $owner,
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $title, bool $required, string $owner): array
    {
        return [
            'title' => $title,
            'status' => 'missing',
            'required' => $required,
            'owner' => $owner,
            'source' => 'library',
        ];
    }
}
