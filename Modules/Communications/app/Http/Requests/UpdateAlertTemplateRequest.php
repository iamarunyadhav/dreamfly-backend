<?php

namespace Modules\Communications\Http\Requests;

class UpdateAlertTemplateRequest extends StoreAlertTemplateRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('communications.update') ?? false;
    }
}
