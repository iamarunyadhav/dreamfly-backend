<?php

namespace Modules\Clients\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientAdminSummary;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use ZipArchive;

class AdminSummaryDocumentService
{
    private const TEMPLATE_PATH = 'templates/admin-summary-template.docx';

    /** @var array<string, string> template label => form_data key */
    private const LABEL_MAP = [
        'Client Name' => 'client_name',
        'Travel Country' => 'travel_country',
        'Purpose of Visit' => 'purpose_of_visit',
        'Application Type' => 'application_type',
        'Event Date Plan to visit' => 'event_date_plan_to_visit',
        'Inviter Name' => 'inviter_name',
        'About Inviter' => 'about_inviter',
        'Sponsor Spending Amount' => 'sponsor_spending_amount',
        'Reason for Sponsorship' => 'reason_for_sponsorship',
        'Appilicant bank balance' => 'applicant_bank_balance',
        'Source of fund' => 'source_of_fund',
        'Appilicant going to spend' => 'applicant_going_to_spend',
        'Relationship' => 'relationship',
        'Need to talk with whom' => 'need_to_talk_with_whom',
        'Asset Certificate Required?' => 'asset_certificate_required',
        'Audit and charted report' => 'audit_and_charted_report',
        'Number of Assets Required' => 'number_of_assets_required',
        'Refusal Letter Provided?' => 'refusal_letter_provided',
        'If you donâ€™t have refusal provide date' => 'refusal_provide_date',
        'Last 6 Month Bank Statement Provided?' => 'last_6_month_bank_statement_provided',
    ];

    public function __construct(private FolderService $folders)
    {
    }

    public function generate(Client $client, ClientAdminSummary $summary, int $userId, ?int $folderId = null, ?string $fileName = null): File
    {
        $template = Storage::path(self::TEMPLATE_PATH);
        if (! is_file($template)) {
            throw ValidationException::withMessages([
                'template' => ['The Admin Summary template (Summary.docx) is missing on this server. Upload it to storage/app/private/'.self::TEMPLATE_PATH.'.'],
            ]);
        }

        $folder = $folderId ? Folder::findOrFail($folderId) : $this->adminSummaryFolder($client, $userId);
        $safeReference = Str::slug($client->reference_no) ?: 'client-'.$client->id;
        $storedName = $safeReference.'-admin-summary-'.now()->format('YmdHis').'.docx';
        $relativePath = 'generated/client-'.$client->id.'/'.$storedName;
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        copy($template, $absolutePath);
        $this->fillDocx($absolutePath, $this->values($client, $summary));

        $displayName = $fileName ? preg_replace('/\.docx$/i', '', trim($fileName)).'.docx' : $client->reference_no.' Admin Summary.docx';

        $file = File::create([
            'folder_id' => $folder->id,
            'client_id' => $client->id,
            'name' => $storedName,
            'original_name' => $displayName,
            'disk' => 'local',
            'path' => $relativePath,
            'extension' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => filesize($absolutePath) ?: 0,
            'uploaded_by' => $userId,
            'verified' => true,
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);

        $summary->forceFill(['generated_file_id' => $file->id])->save();

        return $file;
    }

    private function values(Client $client, ClientAdminSummary $summary): array
    {
        $data = $summary->form_data ?? [];

        return [
            ...$data,
            'client_name' => $data['client_name'] ?? $client->full_name,
            'travel_country' => $data['travel_country'] ?? $client->country,
            'purpose_of_visit' => $data['purpose_of_visit'] ?? $client->visa_type,
            'application_type' => $data['application_type'] ?? $client->service_category,
        ];
    }

    private function fillDocx(string $docxPath, array $values): void
    {
        $zip = new ZipArchive();
        $zip->open($docxPath);
        $xml = $zip->getFromName('word/document.xml');

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        $document->loadXML($xml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xpath->query('//w:tr') as $row) {
            $cells = $xpath->query('./w:tc', $row);
            if ($cells->length < 2) {
                continue;
            }

            $label = $this->cellText($xpath, $cells->item(0));
            $key = self::LABEL_MAP[$label] ?? null;
            if (! $key) {
                continue;
            }

            $value = trim((string) ($values[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            if (str_contains(strtolower($label), 'provided?') || str_contains(strtolower($label), 'required?') || $label === 'Audit and charted report') {
                $value = $this->yesNoValue($value);
            }

            $this->replaceCellText($xpath, $cells->item(1), $value);
        }

        $zip->addFromString('word/document.xml', $document->saveXML());
        $zip->close();
    }

    private function cellText(DOMXPath $xpath, mixed $cell): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t', $cell) as $textNode) {
            $parts[] = $textNode->nodeValue;
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    private function replaceCellText(DOMXPath $xpath, mixed $cell, string $value): void
    {
        $textNodes = $xpath->query('.//w:t', $cell);
        if ($textNodes->length > 0) {
            foreach ($textNodes as $index => $textNode) {
                $textNode->nodeValue = $index === 0 ? $value : '';
            }

            return;
        }

        $document = $cell->ownerDocument;
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $paragraph = $document->createElementNS($namespace, 'w:p');
        $run = $document->createElementNS($namespace, 'w:r');
        $text = $document->createElementNS($namespace, 'w:t');
        $text->setAttribute('xml:space', 'preserve');
        $text->nodeValue = $value;

        $run->appendChild($text);
        $paragraph->appendChild($run);
        $cell->appendChild($paragraph);
    }

    private function yesNoValue(string $value): string
    {
        $normalized = strtolower($value);

        return str_starts_with($normalized, 'y') || in_array($normalized, ['1', 'true'], true)
            ? '☑ Yes    ☐ No'
            : '☐ Yes    ☑ No';
    }

    private function adminSummaryFolder(Client $client, int $userId): Folder
    {
        return $this->folders->clientSubfolder($client, 'Admin Summary', $userId);
    }
}
