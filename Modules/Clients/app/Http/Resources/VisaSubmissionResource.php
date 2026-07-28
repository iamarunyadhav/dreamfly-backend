<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Http\Resources\FileResource;

class VisaSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'submitted_at' => optional($this->submitted_at)->toDateString(),
            'lodgement_reference' => $this->lodgement_reference,
            'tracking_reference' => $this->tracking_reference,
            'submitted_to' => $this->submitted_to,
            'submission_method' => $this->submission_method,
            'appointment_at' => $this->appointment_at,
            'appointment_location' => $this->appointment_location,
            'biometrics_at' => optional($this->biometrics_at)->toDateString(),
            'expected_decision_at' => optional($this->expected_decision_at)->toDateString(),
            'receipt_file_id' => $this->receipt_file_id,
            'receipt_file' => $this->whenLoaded('receiptFile', fn () => $this->receiptFile ? new FileResource($this->receiptFile) : null),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
