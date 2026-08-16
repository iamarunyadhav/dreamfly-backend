<?php

namespace Modules\Clients\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientApplicationUnit;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use ZipArchive;

class ApplicationUnitDocumentService
{
    private const TEMPLATE_PATH = 'templates/application-data-template.docx';

    /** @var array<string, string> template label => form_data key */
    private const LABEL_MAP = [
        'Destination Country' => 'destination_country',
        'Visa Category â˜ Tourist â˜ Family Visit â˜ Business â˜ Event â˜ Conference' => 'visa_category',
        'Type Of Application â˜ New â˜ Re-Apply' => 'type_of_application',
        'Number Of Applicants' => 'number_of_applicants',
        'Purpose Of Travel' => 'purpose_of_travel',
        'Departure Date and Time From Home Country to Destination Country' => 'departure_from_home_at',
        'Arrival Date and Time at Destination' => 'arrival_at_destination_at',
        'Departure Date and Time From Destination Country to Home Country (Return)' => 'return_departure_at',
        'Arrival Date and Time at Home Country' => 'return_arrival_at',
        'Length Of Stay (Days)' => 'length_of_stay_days',
        'Inviter / Sponsor â˜ Yes â˜ No' => 'inviter_sponsor',
        'Inviter / Sponsor Relationship' => 'inviter_sponsor_relationship',
        'Proof Of Relationship B/W Applicant And Inviter/Sponsor (Provided/Not Provided)' => 'relationship_proof_status',
        'Accommodation â˜ Hotel â˜ Inviterâ€™s Home â˜ Other' => 'accommodation',
        'Who Will Pay For The Trip? â˜ Self â˜ Sponsor â˜ Shared' => 'who_will_pay_for_the_trip',
        'Full Name (As Per Passport)' => 'full_name_as_per_passport',
        'Other Names (If Any)' => 'other_names_if_any',
        'Given Name' => 'given_name',
        'Surname' => 'surname',
        'Gender â˜ Male â˜ Female' => 'gender',
        'Date Of Birth' => 'date_of_birth',
        'Place Of Birth' => 'place_of_birth',
        'Nationality' => 'nationality',
        'National Identity Card No. Issued Date' => 'national_identity_card_no',
        'Marital Status â˜ Single â˜ Married â˜ Divorced â˜ Widowed' => 'marital_status',
        'Marriage Date' => 'marriage_date',
        'Divorce Date' => 'divorce_date',
        'Current Address' => 'current_address',
        'City' => 'city',
        'Country' => 'country',
        'Phone Number' => 'phone_number',
        'Email Address' => 'email_address',
        'Gs Division' => 'gs_division',
        'Family Card with Translation (Provided/Not Provided)' => 'family_card_translation_status',
        'Birth Certificate with Translation (Provided/Not Provided)' => 'birth_certificate_translation_status',
        'NIC with Translation (Provided/Not Provided)' => 'nic_translation_status',
        'Marriage with Translation (Provided/Not Provided)' => 'marriage_translation_status',
        'Divorce Doc with Translation (Provided/Not Provided)' => 'divorce_doc_translation_status',
        'Death with Translation (Provided/Not Provided)' => 'death_translation_status',
        'Passport Number' => 'passport_number',
        'Issue Date' => 'passport_issue_date',
        'Expiry Date' => 'passport_expiry_date',
        'Previous Passport No. (If Any)' => 'previous_passport_no_if_any',
        'Observation / Amendment Pages (2 nd Page In Passport) â˜ Yes â˜ No' => 'observation_pages_available',
        'Have You Given Biometrics Before? â˜ Yes â˜ No' => 'biometrics_before',
        'Country & Dd/Mm/ Yyy' => 'biometrics_country_date',
        'Have You Had Any Visa Refusals? â˜ Yes â˜ No' => 'visa_refusals',
        'If Yes, Country' => 'refusal_country',
        'Date' => 'refusal_date',
        'Reason (As Per Refusal Letter)' => 'refusal_reason_as_per_refusal_letter',
        'Refusal Letter (Available / Not Available)' => 'refusal_letter_status',
        'B. Spouse Details (If Married ) Field Details Full Name' => 'spouse_full_name',
        'Will Your Spouse Travel With You? â˜ Yes â˜ No' => 'spouse_traveling_with_applicant',
        'Level From To Course Institution With Address Primary Secondary Higher Studies' => 'education_details',
        'Job Title' => 'job_title',
        'Company Name' => 'company_name',
        'Company Address' => 'company_address',
        'Company / Phone' => 'company_phone',
        'Company Email' => 'company_email',
        'Employer Name' => 'employer_name',
        'Employment Start Date' => 'employment_start_date',
        'Monthly Salary (Also Convert in Destination Currency)' => 'monthly_salary',
        'Annual Income (Also Convert in Destination Currency)' => 'annual_income',
        'Leave Approved From' => 'leave_approved_from',
        'Leave Approved To' => 'leave_approved_to',
        'Work Confirmation Letter (Provided/Not Provided)' => 'work_confirmation_status',
        'Leave Approval Letter (Provided/Not Provided)' => 'leave_approval_status',
        'Company Logo (Provided/Not Provided/Consultant End)' => 'company_logo_status',
        'Company Letter Head (Provided/Not Provided/Consultant End)' => 'company_letterhead_status',
        'Employment Photos â€“ Proof Of Working (Provided/Not Provided)' => 'employment_photos_status',
        'Job ID (Provided/Not Provided/Consultant End)' => 'job_id_status',
        'Pay Slip (Provided/Not Provided/Consultant End)' => 'payslip_status',
        'Business / Company Name' => 'business_company_name',
        'Business Registration Number' => 'business_registration_number',
        'Business Address' => 'business_address',
        'Business Start Date' => 'business_start_date',
        'Tax / Tin Number' => 'tax_tin_number',
        'Annual Income Business ( Also Convert in Destination Currency)' => 'business_annual_income',
        'Annual Income Personal (As a Salary) (Also Convert in Destination Currency)' => 'personal_annual_income',
        'Please provide your 10-year history, including the following details: From Date, To Date, Activity/Job Role, Institution/Organization, City, Province/State, and Country.' => 'ten_year_history',
        'A. Bank Accounts Bank Account No. Type (Savings/ Current Account) Balance (Also Convert in Destination Currency)' => 'bank_accounts',
        'B. Fixed Deposits Bank Fd No. Value Maturity (Also Convert in Destination Currency)' => 'fixed_deposits',
        'C. Assets Asset Type Owner Details (Deed No./ Date) Estimated Value (Also Convert in Destination Currency) Photos (Provided/Not Provided) Deed Translation (Provided/Not Provided)' => 'assets',
        'D. Insurance Company Policy No. Maturity Date Maturity Amount ( Also Convert in Destination Currency)' => 'insurance_details',
        'E. Jewelry Weight Value (Also Convert in Destination Currency) Valuation / Affidavit ( Provided/Not Provided/Consultant End)' => 'jewelry_details',
        'F. Vehicle Vehicle Vehicle No. Value (Also Convert in Destination Currency)' => 'vehicle_details',
        'A. Financial Support Information Field Details Family Member Full Name' => 'family_financial_support',
        'Relationship To Applicant' => 'inviter_sponsor_relationship',
        'Provide Relationship Proof Or Explanation' => 'relationship_proof_status',
        'Status In Destination Country (Citizen / Pr / Work Permit / Student)' => 'inviter_status_destination_country',
        'Passport No.' => 'inviter_passport_no',
        'Residential Address' => 'inviter_residential_address',
        'Support Provided â˜ Flights â˜ Accommodation â˜ Living Expenses' => 'support_provided',
        'Event Name' => 'event_name',
        'Event Date' => 'event_date',
        'Event Time' => 'event_time',
        'Venue Address' => 'venue_address',
        'Expected Date Of Delivery ( EDD )' => 'hospital_expected_delivery_date',
        'Hospital / Medical Facility Name' => 'hospital_details',
        'Name Of Institution' => 'temple_church_details',
        'Association Name' => 'association_details',
        'Have You Ever Lived In The Destination Country As A PR Or Landed Immigrant? â˜ Yes â˜ No' => 'lived_in_destination_as_pr',
        'Do You Have A Family Member In The Destination Country Who Is A Citizen Or Pr Holder And Over 18 Years Old? â˜ Yes â˜ No' => 'family_member_in_destination',
        'Have You Been Previously Married? â˜ Yes â˜ No' => 'previously_married',
        'Have You Ever Overstayed Or Violated Visa Conditions In Any Country? â˜ Yes â˜ No' => 'overstayed_or_violated_visa',
        'Have You Ever Been Refused Entry Or Deported From Any Country? â˜ Yes â˜ No' => 'refused_entry_or_deported',
        'Do You Currently Hold Any Valid Visas For Other Countries? â˜ Yes â˜ No' => 'valid_visas_other_countries',
        'Have You Traveled Outside Your Home Country In The Last 5 Years? â˜ Yes â˜ No' => 'traveled_last_5_years',
        'Have You Ever Changed Your Name Legally? â˜ Yes â˜ No' => 'changed_name_legally',
        'Are You Currently Facing Any Legal Or Court Cases? â˜ Yes â˜ No' => 'legal_or_court_cases',
        'Have You Served In The Military, Police, Or Armed Forces? â˜ Yes â˜ No' => 'military_police_service',
        'Do You Have Any Medical Conditions That May Affect Your Travel? â˜ Yes â˜ No' => 'medical_conditions',
        'Expense Chart As Per Dd/Mm/ Yyyy Expenses L KR Flight Ticket With Return Airport Expenses Travel Insurance Emergency Expenses Available Personal Fund Visa Fee Sri Lanka Airport Transport From Home Town With Rooms Sim Total' => 'expense_chart_date',
        'Amount planned to spend for the trip (Also Convert in Destination Currency)' => 'amount_planned_to_spend',
        'Additional Applicant Information For United Kingdom' => 'uk_additional_information',
        'Additional Applicant Information For New Zealand' => 'new_zealand_additional_information',
        'Additional Applicant Information For Canada' => 'canada_additional_information',
        'Reason For Return: (Employment Commitment, Family Responsibility, Property / Assets, Community Involvement)' => 'home_country_ties',
        'Family Photo (Provided/Not Provided)' => 'family_photo_status',
        'Video Call Screenshots (Provided/Not Provided)' => 'video_call_screenshots_status',
        'Visa Photo (Provided/Not Provided)' => 'visa_photo_status',
        'Job Id Photo With Blue Background (Provided/Not Provided)' => 'job_id_photo_blue_background_status',
        'Land/House Photos (Provided/Not Provided)' => 'land_house_photos_status',
        'Portfolio Photos (Provided/Not Provided)' => 'portfolio_photos_status',
    ];

    /**
     * The real template re-uses short column headers (Full Name, Address,
     * Email, Nic, Position...) across several unrelated tables (Spouse,
     * Inviter, Accompanying Person, Temple, Association, Authorized Person).
     * A single global LABEL_MAP can't tell those apart - whichever section
     * happened to match first would leak its value into every other section
     * sharing that label. This maps the table's own heading paragraph text
     * to a *section-scoped* label => form_data key table, checked before the
     * global LABEL_MAP so these specific collisions resolve correctly.
     *
     * @var array<string, array<string, string>>
     */
    private const SECTION_LABEL_MAP = [
        'B. Spouse Details (If Married )' => [
            'Full Name' => 'spouse_full_name',
            'National Identity Card No.' => 'spouse_nic',
            'Dob' => 'spouse_dob',
            'Occupation' => 'spouse_occupation',
            'Contact Number' => 'spouse_contact_number',
            'Address' => 'spouse_address',
            'Email' => 'spouse_email',
            'Nationality' => 'spouse_nationality',
        ],
        'Authorized Person (Who Will Manage Your Work During Your Leave) (If Self-Employed / Business Owner)' => [
            'Name' => 'authorized_person_name',
            'Address' => 'authorized_person_address',
            'Phone Number' => 'authorized_person_phone',
            'Nic' => 'authorized_person_nic',
            'Position' => 'authorized_person_position',
            'Employment Start Date' => 'authorized_person_start_date',
        ],
        'Section 11 – Inviter / Sponsor Details (If Any)' => [
            'Full Name' => 'inviter_full_name',
            'Occupation / Job Title' => 'inviter_occupation',
            'Employer / Company Name' => 'inviter_employer_name',
            'Workplace Address' => 'inviter_workplace_address',
            'Phone Number' => 'inviter_phone',
            'Email Address' => 'inviter_email',
            'Employment Start Date' => 'inviter_employment_start_date',
            'Annual Income (Also Convert in Destination Currency)' => 'inviter_annual_income',
        ],
        'Section 14 - Accompanying Person Details (If Traveling Together)' => [
            'Full Name' => 'accompanying_person_full_name',
            'Relationship To Applicant' => 'accompanying_person_relationship',
            'Date Of Birth' => 'accompanying_person_dob',
            'Passport Number' => 'accompanying_person_passport_number',
            'Place Of Birth' => 'accompanying_person_place_of_birth',
            'Passport Issue Date' => 'accompanying_person_passport_issue_date',
            'Passport Expiry Date' => 'accompanying_person_passport_expiry_date',
            'National Identity Card No.' => 'accompanying_person_nic',
            'Nationality' => 'accompanying_person_nationality',
            'Contact Number' => 'accompanying_person_contact_number',
            'Email Address' => 'accompanying_person_email',
        ],
        'A. Temple / Church Details (Must be above 18+ Years)' => [
            'Name Of Institution' => 'temple_name',
            'Address' => 'temple_address',
            'Telephone' => 'temple_telephone',
            'Email' => 'temple_email',
            'Authorized Person Name' => 'temple_authorized_person_name',
            'Nic' => 'temple_authorized_person_nic',
            'Position' => 'temple_authorized_person_position',
            'Member Since (Year)' => 'temple_member_since',
            'Applicant’s Role / Position' => 'temple_applicant_role',
        ],
        'B. Association / Community Group Membership (Must be above 18+ Years)' => [
            'Association Name' => 'association_name',
            'Address' => 'association_address',
            'Telephone' => 'association_telephone',
            'Email' => 'association_email',
            'Authorized Person Name' => 'association_authorized_person_name',
            'Nic' => 'association_authorized_person_nic',
            'Position' => 'association_authorized_person_position',
            'Member Since (Year)' => 'association_member_since',
            'Applicant’s Role / Position' => 'association_applicant_role',
        ],
        'Hospital Details (If Applicable)' => [
            'Hospital / Medical Facility Name' => 'hospital_details',
            'Hospital / Medical Facility Address' => 'hospital_address',
            'Hospital / Medical Facility Contact' => 'hospital_contact',
            'Patient Name' => 'hospital_patient_name',
            'Medical Proof Available' => 'hospital_medical_proof_available',
        ],
        'Section 12 – Event Details (If Applicable)' => [
            'Host / Organizing Body' => 'event_host_organisation',
            'Names Of Key Persons (Name Of The Person Being Celebrated Or Honored)' => 'event_key_persons',
            'Relationship With Host (Inviter)' => 'event_relationship_with_host',
            'Additional Notes' => 'event_additional_notes',
        ],
    ];

    public function __construct(private FolderService $folders)
    {
    }

    public function generate(Client $client, ClientApplicationUnit $applicationUnit, int $userId, ?int $folderId = null, ?string $fileName = null): File
    {
        $template = Storage::path(self::TEMPLATE_PATH);
        if (! is_file($template)) {
            $template = storage_path('app/'.self::TEMPLATE_PATH);
        }
        if (! is_file($template)) {
            throw ValidationException::withMessages([
                'template' => ['The Application Unit template (NEW INTERNATIONAL VISA APPLICATION DATA English FORM.docx) is missing on this server. Upload it to storage/app/private/'.self::TEMPLATE_PATH.'.'],
            ]);
        }
        $folder = $folderId ? Folder::findOrFail($folderId) : $this->applicationUnitFolder($client, $userId);
        $storedName = (Str::slug($client->reference_no) ?: 'client-'.$client->id).'-application-data-'.now()->format('YmdHis').'.docx';
        $relativePath = 'generated/client-'.$client->id.'/'.$storedName;
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        copy($template, $absolutePath);
        $this->fillDocx($absolutePath, $this->values($client, $applicationUnit));

        $displayName = $fileName ? preg_replace('/\.docx$/i', '', trim($fileName)).'.docx' : $client->reference_no.' Application Data Form.docx';

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

        $applicationUnit->forceFill(['generated_file_id' => $file->id])->save();

        return $file;
    }

    private function values(Client $client, ClientApplicationUnit $applicationUnit): array
    {
        $data = $applicationUnit->form_data ?? [];

        return [
            ...$data,
            'destination_country' => $data['destination_country'] ?? $client->country,
            'visa_category' => $data['visa_category'] ?? $client->visa_type,
            'full_name_as_per_passport' => $data['full_name_as_per_passport'] ?? $client->full_name,
            'passport_number' => $data['passport_number'] ?? $client->passport_no,
            'national_identity_card_no' => $data['national_identity_card_no'] ?? $client->nic,
            'phone_number' => $data['phone_number'] ?? $client->phone,
            'email_address' => $data['email_address'] ?? $client->email,
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

        // Walk the body in document order (not a flat //w:tr scan) so each
        // table's rows can be resolved against the section heading paragraph
        // that precedes it - several short column labels (Full Name, Address,
        // Email...) repeat across unrelated tables (Spouse, Inviter, Temple,
        // Association...) and only the heading tells them apart.
        $heading = '';
        foreach ($xpath->query('/w:document/w:body/*') as $node) {
            if ($node->nodeName === 'w:p') {
                $text = $this->cellText($xpath, $node);
                if ($text !== '') {
                    $heading = $text;
                }
                continue;
            }

            if ($node->nodeName !== 'w:tbl') {
                continue;
            }

            foreach ($xpath->query('.//w:tr', $node) as $row) {
                $cells = $xpath->query('./w:tc', $row);
                if ($cells->length < 2) {
                    continue;
                }

                $label = $this->cellText($xpath, $cells->item(0));
                $key = self::SECTION_LABEL_MAP[$heading][$label] ?? self::LABEL_MAP[$label] ?? Str::slug($label, '_');
                $value = trim((string) ($values[$key] ?? ''));
                if ($value === '') {
                    continue;
                }

                if (in_array(strtolower($value), ['yes', 'no', 'true', 'false', '1', '0'], true) && str_contains($label, "\xc3\xa2\xcb\x9c")) {
                    $value = $this->yesNoValue($value);
                }

                $this->replaceCellText($xpath, $cells->item(1), $value);
            }
        }

        $zip->addFromString('word/document.xml', $document->saveXML());
        $zip->close();
    }

    private function yesNoValue(string $value): string
    {
        $normalized = strtolower($value);

        return str_starts_with($normalized, 'y') || in_array($normalized, ['1', 'true'], true)
            ? 'â˜‘ Yes    â˜ No'
            : 'â˜ Yes    â˜‘ No';
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

    private function applicationUnitFolder(Client $client, int $userId): Folder
    {
        return $this->folders->clientSubfolder($client, 'Application Unit', $userId);
    }
}
