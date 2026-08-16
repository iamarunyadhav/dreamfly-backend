<?php

namespace Modules\Checklists\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Checklists\Models\ChecklistTemplate;

class ChecklistsDatabaseSeeder extends Seeder
{
    /**
     * Default document requirements for the Visit Visa Application Unit
     * checklist (applicant / inviter / internal). Mirrors the defaults that
     * used to live only as hardcoded arrays in ApplicationUnitPanel.tsx, so
     * this library is now the real, toggle-able source for new cases.
     */
    private const APPLICANT = [
        'Passport (Current)',
        'Passport Observation Page',
        'Previous Passports',
        'National Identity Card + English Translation',
        'Birth Certificate + English Translation',
        'Family Card + English Translation',
        'Marriage / Divorce / Death Certificate',
        'Bank Balance Confirmation Letter',
        'Personal Bank Statements (Last 6 Months)',
        'Fixed Deposit Certificates',
        'Property Deed + English Translation',
        'Property Valuation / Asset Certificate',
        'Asset Photos',
        'Visa Photo',
        'Police Clearance Certificate',
        'Work Confirmation Letter / Appointment Letter',
        'Last 6 Months Payslips',
        'Leave Approval Letter',
        'Company Logo',
        'Company Letter Head',
        'Employment Photos Proof',
        'Job ID',
        'Business Registration Certificate',
        'Statement Of Purpose',
        'Day-Wise Travel Itinerary',
        'Hotel Booking / Accommodation Letter',
        'Flight Ticket Reservation',
        'Travel Insurance',
        'Family Photo With Description',
        'Video Call Screenshots',
        'Job ID Photo With Blue Background',
        'Land/House Photos',
        'Previous Visa Refusal Explanation Letter',
        'Refusal Letter',
        'Portfolio',
    ];

    private const INVITER = [
        'Proof Of Legal Status',
        'Passport / PR / Citizenship / Permit',
        'Residential Address Proof',
        'Council Tax',
        'Utility Bill',
        'Mortgage / Land Registry / Rental Agreement',
        'Employment Letter',
        'Bank Statements',
        'Payslips',
        'Tax Assessment',
        'Business Registration Documents',
        'Relationship Proof Documents',
        'Event Proof',
        'Invitation Letter',
    ];

    /** title => category (internal rows are office tasks, not client documents). */
    private const INTERNAL = [
        'Application Form Filled' => 'application_processing',
        'Cover Letter Prepared' => 'application_processing',
        'Statement Of Purpose Drafted' => 'application_processing',
        'Itinerary Prepared' => 'application_processing',
        'Document Set Cross-Checked' => 'documentation_unit',
        'Appointment Booked' => 'submission',
        'Application Fee Paid' => 'submission',
        'File Copy Archived' => 'final_review',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::APPLICANT as $title) {
            $this->upsert($title, 'applicant', 'client_documents');
        }

        foreach (self::INVITER as $title) {
            $this->upsert($title, 'inviter', 'inviter');
        }

        foreach (self::INTERNAL as $title => $category) {
            // Internal rows are office-side prep steps, not documents the
            // client hands over, so they default to optional and are not
            // flagged as document-required (matches the long-standing
            // default in ApplicationUnitPanel.tsx).
            $this->upsert($title, 'internal', $category, required: false, documentRequired: false);
        }
    }

    private function upsert(string $title, string $owner, string $category, bool $required = true, bool $documentRequired = true): void
    {
        ChecklistTemplate::updateOrCreate(
            ['title' => $title, 'owner' => $owner],
            [
                'category' => $category,
                'is_required' => $required,
                'document_required' => $documentRequired,
                'status' => 'published',
                'version' => 1,
                'is_active' => true,
            ],
        );
    }
}
