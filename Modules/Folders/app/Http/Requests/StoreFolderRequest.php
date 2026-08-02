<?php

namespace Modules\Folders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Modules\Folders\Models\Folder;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('folders.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:folders,id'],
            'is_general' => ['sometimes', 'boolean'],
            'auto_create_for_clients' => ['sometimes', 'boolean'],
            'propagate_existing' => ['sometimes', 'boolean'],
            'public_download' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->boolean('is_general')) {
                    return;
                }

                if (! $this->filled('parent_id')) {
                    $validator->errors()->add('parent_id', 'Choose an existing client/common user folder as the parent.');

                    return;
                }

                $parent = Folder::with('parent.parent')->find($this->input('parent_id'));

                if (! $parent || $this->isManagedGlobalFolder($parent)) {
                    $validator->errors()->add('parent_id', 'Do not create folders directly under Clients/Common Users. Use the country > user folder.');
                }
            },
        ];
    }

    private function isManagedGlobalFolder(Folder $folder): bool
    {
        if ($folder->client_id || $folder->common_user_id || $folder->scope !== 'global') {
            return false;
        }

        $managedRoots = ['Clients', 'Common Users', 'Archived', 'Moved'];

        if ($folder->parent_id === null) {
            return in_array($folder->name, $managedRoots, true);
        }

        $ancestor = $folder->parent;
        while ($ancestor) {
            if ($ancestor->parent_id === null && in_array($ancestor->name, $managedRoots, true)) {
                return true;
            }

            $ancestor = $ancestor->parent;
        }

        return false;
    }
}
