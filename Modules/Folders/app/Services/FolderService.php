<?php

namespace Modules\Folders\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Folders\Models\Folder;
use Modules\Folders\Repositories\Contracts\FolderRepositoryInterface;

class FolderService extends BaseService
{
    private const CLIENTS_ROOT = 'Clients';

    private const COMMON_USERS_ROOT = 'Common Users';

    private const UNSPECIFIED_COUNTRY = 'Unspecified';

    private const ARCHIVED_LEADS_FOLDER = 'Archived';

    private const ARCHIVED_LEADS_DESCRIPTION = 'Leads that have been converted to clients. Their documents now live '
        .'in the client\'s own folder tree - this archive is kept for reference only and is never deleted automatically.';

    public function __construct(FolderRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Nested tree (children arrays) built from the flat folder list.
     */
    public function tree(): array
    {
        $folders = collect($this->repository->tree());
        $fileCounts = $this->directFileCounts();

        return $this->buildBranch($folders, null, $fileCounts);
    }

    /**
     * Current (non-superseded) file count per folder, in one query - the
     * building block for each folder's own-plus-descendants total below.
     *
     * @return Collection<int, int>
     */
    private function directFileCounts(): Collection
    {
        return DB::table('files')
            ->whereNull('deleted_at')
            ->where('is_current', true)
            ->selectRaw('folder_id, count(*) as cnt')
            ->groupBy('folder_id')
            ->pluck('cnt', 'folder_id');
    }

    public function create(array $attributes): Folder
    {
        return DB::transaction(function () use ($attributes) {
            $propagateExisting = (bool) ($attributes['propagate_existing'] ?? false);
            unset($attributes['propagate_existing']);

            if (($attributes['is_general'] ?? false) && ! array_key_exists('auto_create_for_clients', $attributes)) {
                $attributes['auto_create_for_clients'] = true;
            }

            $attributes['scope'] = $attributes['scope'] ?? 'global';
            $folder = parent::create($attributes);

            if ($folder->is_general && $propagateExisting) {
                $this->propagateTemplateToExisting($folder);
            }

            return $folder;
        });
    }

    /**
     * Clone a general folder template into every active client's AND every
     * not-yet-converted lead's folder tree - used both right after a template
     * is marked general/propagate-existing and from the manual "Propagate" action.
     */
    public function propagateTemplateToExisting(Folder $template): int
    {
        $count = 0;

        Client::query()->where('status', 'active')->each(function (Client $client) use ($template, &$count) {
            $clientRoot = $this->ensureClientRoot($client, $template->created_by);
            $this->ensureTemplateInstance($template, $clientRoot, $client->id, null, $template->created_by);
            $count++;
        });

        CommonUser::query()->where('status', '!=', 'converted')->each(function (CommonUser $lead) use ($template, &$count) {
            $leadRoot = $this->ensureCommonUserRoot($lead, $template->created_by);
            $this->ensureTemplateInstance($template, $leadRoot, null, $lead->id, $template->created_by);
            $count++;
        });

        return $count;
    }

    public function createClientFolderTree(Client $client, int $userId): int
    {
        return DB::transaction(function () use ($client, $userId) {
            $clientRoot = $this->ensureClientRoot($client, $userId);

            return $this->fillOwnedSubfolders($clientRoot, $client->id, null, $userId);
        });
    }

    public function createLeadFolderTree(CommonUser $lead, int $userId): int
    {
        return DB::transaction(function () use ($lead, $userId) {
            $leadRoot = $this->ensureCommonUserRoot($lead, $userId);

            return $this->fillOwnedSubfolders($leadRoot, null, $lead->id, $userId);
        });
    }

    /**
     * Relocate a converted lead's own folder tree into "Common Users >
     * Archived" instead of leaving it sitting under its country folder as if
     * it were still an active lead - its documents have already been re-filed
     * into the new client's tree by this point, this just keeps the empty
     * shell around for reference rather than deleting it.
     */
    public function archiveLeadFolderTree(CommonUser $lead, ?int $userId = null): ?Folder
    {
        $leadRoot = Folder::where('common_user_id', $lead->id)
            ->whereHas('parent', fn ($q) => $q->whereNull('client_id')->whereNull('common_user_id'))
            ->first();

        if (! $leadRoot) {
            return null;
        }

        $archivedRoot = $this->ensureArchivedLeadsRoot($userId ?? $leadRoot->created_by);

        if ($leadRoot->parent_id !== $archivedRoot->id) {
            $leadRoot->update(['parent_id' => $archivedRoot->id]);
        }

        return $leadRoot;
    }

    private function ensureArchivedLeadsRoot(?int $userId): Folder
    {
        $commonUsersRoot = $this->ensureNamedGlobalRoot(self::COMMON_USERS_ROOT, $userId);

        return Folder::firstOrCreate(
            ['name' => self::ARCHIVED_LEADS_FOLDER, 'parent_id' => $commonUsersRoot->id],
            [
                'description' => self::ARCHIVED_LEADS_DESCRIPTION,
                'slug' => 'common-users-archived',
                'scope' => 'global',
                'is_active' => true,
                'created_by' => $userId,
            ],
        );
    }

    /**
     * Resolve (creating if needed) a named subfolder inside a client's folder,
     * e.g. "Final Documents". Shared by the services that file generated or
     * uploaded documents so there is one client-folder resolution rule.
     */
    public function clientSubfolder(Client $client, string $name, ?int $userId = null): Folder
    {
        $clientRoot = $this->ensureClientRoot($client, $userId);

        return $this->ensureOwnedChildFolder($clientRoot, $name, $client->id, null, (int) $userId);
    }

    /** Same resolution rule as clientSubfolder(), but for a lead's own tree. */
    public function leadSubfolder(CommonUser $lead, string $name, ?int $userId = null): Folder
    {
        $leadRoot = $this->ensureCommonUserRoot($lead, $userId);

        return $this->ensureOwnedChildFolder($leadRoot, $name, null, $lead->id, (int) $userId);
    }

    private function fillOwnedSubfolders(Folder $ownerRoot, ?int $clientId, ?int $commonUserId, int $userId): int
    {
        $applicantDocumentsFolderId = null;

        foreach ($this->defaultSubfolders() as $name) {
            $folder = $this->ensureOwnedChildFolder($ownerRoot, $name, $clientId, $commonUserId, $userId);
            if ($name === 'Applicant Documents') {
                $applicantDocumentsFolderId = $folder->id;
            }
        }

        $this->generalTemplates()->each(function (Folder $template) use ($ownerRoot, $clientId, $commonUserId, $userId) {
            $this->ensureTemplateInstance($template, $ownerRoot, $clientId, $commonUserId, $userId);
        });

        return $applicantDocumentsFolderId ?? $ownerRoot->id;
    }

    private function buildBranch(Collection $folders, ?int $parentId, Collection $fileCounts): array
    {
        return $folders
            ->where('parent_id', $parentId)
            ->map(function ($folder) use ($folders, $fileCounts) {
                $children = $this->buildBranch($folders, $folder->id, $fileCounts);

                // Own directly-filed documents plus everything nested under
                // this folder, so a parent that holds only subfolders (no
                // direct files) still reflects whether there is any data
                // underneath it at a glance, without expanding the tree.
                $filesCount = (int) ($fileCounts->get($folder->id) ?? 0)
                    + collect($children)->sum('files_count');

                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'description' => $folder->description,
                    'slug' => $folder->slug,
                    'client_id' => $folder->client_id,
                    'common_user_id' => $folder->common_user_id,
                    'template_id' => $folder->template_id,
                    'scope' => $folder->scope,
                    'is_general' => $folder->is_general,
                    'auto_create_for_clients' => $folder->auto_create_for_clients,
                    'is_active' => $folder->is_active,
                    'public_download' => $folder->public_download,
                    'files_count' => $filesCount,
                    'children' => $children,
                ];
            })
            ->values()
            ->all();
    }

    private function ensureNamedGlobalRoot(string $name, ?int $userId): Folder
    {
        return Folder::firstOrCreate(
            ['name' => $name, 'parent_id' => null],
            ['slug' => Str::slug($name), 'is_active' => true, 'scope' => 'global', 'created_by' => $userId],
        );
    }

    /**
     * A shared, reusable bucket for a destination country under a given root
     * ("Clients" or "Common Users") - firstOrCreate so every client/lead
     * headed to the same country nests under the same folder node.
     */
    private function ensureCountryFolder(Folder $root, ?string $country, ?int $userId): Folder
    {
        $name = trim((string) $country) !== '' ? trim($country) : self::UNSPECIFIED_COUNTRY;

        return Folder::firstOrCreate(
            ['name' => $name, 'parent_id' => $root->id],
            [
                'slug' => Str::slug($root->name.' '.$name) ?: 'country-'.Str::random(6),
                'scope' => 'global',
                'is_active' => true,
                'created_by' => $userId,
            ],
        );
    }

    private function ensureClientRoot(Client $client, ?int $userId): Folder
    {
        $root = $this->ensureNamedGlobalRoot(self::CLIENTS_ROOT, $userId);
        $countryFolder = $this->ensureCountryFolder($root, $client->country, $userId);

        // Matched by client_id + "parent isn't itself owned" rather than by
        // parent_id directly, so a client whose folder already exists from
        // before the country level was introduced gets relocated under the
        // right country folder instead of a duplicate node being created.
        $existing = Folder::where('client_id', $client->id)
            ->whereHas('parent', fn ($q) => $q->whereNull('client_id')->whereNull('common_user_id'))
            ->first();

        if ($existing) {
            if ($existing->parent_id !== $countryFolder->id) {
                $existing->update(['parent_id' => $countryFolder->id]);
            }

            return $existing;
        }

        $clientFolderName = trim($client->reference_no.' - '.$client->full_name);

        return Folder::create([
            'client_id' => $client->id,
            'parent_id' => $countryFolder->id,
            'name' => $clientFolderName,
            'slug' => Str::slug($clientFolderName) ?: 'client-'.$client->id,
            'scope' => 'client',
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    private function ensureCommonUserRoot(CommonUser $lead, ?int $userId): Folder
    {
        $root = $this->ensureNamedGlobalRoot(self::COMMON_USERS_ROOT, $userId);
        $countryFolder = $this->ensureCountryFolder($root, $lead->country, $userId);

        $existing = Folder::where('common_user_id', $lead->id)
            ->whereHas('parent', fn ($q) => $q->whereNull('client_id')->whereNull('common_user_id'))
            ->first();

        if ($existing) {
            if ($existing->parent_id !== $countryFolder->id) {
                $existing->update(['parent_id' => $countryFolder->id]);
            }

            return $existing;
        }

        $leadFolderName = trim('Lead #'.$lead->id.' - '.$lead->full_name);

        return Folder::create([
            'common_user_id' => $lead->id,
            'parent_id' => $countryFolder->id,
            'name' => $leadFolderName,
            'slug' => Str::slug($leadFolderName) ?: 'lead-'.$lead->id,
            'scope' => 'lead',
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    private function ensureTemplateInstance(Folder $template, Folder $ownerRoot, ?int $clientId, ?int $commonUserId, ?int $userId): Folder
    {
        $parent = $template->parent_id
            ? $this->ensureTemplateParentInstance($template->parent, $ownerRoot, $clientId, $commonUserId, $userId)
            : $ownerRoot;

        $slugSeed = ($clientId ? 'client-'.$clientId : 'lead-'.$commonUserId).' '.$template->name;

        $lookup = ['template_id' => $template->id, 'parent_id' => $parent->id];
        if ($clientId) {
            $lookup['client_id'] = $clientId;
        }
        if ($commonUserId) {
            $lookup['common_user_id'] = $commonUserId;
        }

        return Folder::firstOrCreate($lookup, [
            'name' => $template->name,
            'slug' => Str::slug($slugSeed) ?: 'folder-'.$template->id,
            'scope' => $clientId ? 'client' : 'lead',
            'is_active' => $template->is_active,
            'public_download' => $template->public_download,
            'created_by' => $userId,
        ]);
    }

    private function ensureTemplateParentInstance(?Folder $templateParent, Folder $ownerRoot, ?int $clientId, ?int $commonUserId, ?int $userId): Folder
    {
        $isRootName = in_array($templateParent?->name, [self::CLIENTS_ROOT, self::COMMON_USERS_ROOT], true);

        if (! $templateParent || $isRootName || ! $templateParent->is_general) {
            return $ownerRoot;
        }

        return $this->ensureTemplateInstance($templateParent, $ownerRoot, $clientId, $commonUserId, $userId);
    }

    private function ensureOwnedChildFolder(Folder $parent, string $name, ?int $clientId, ?int $commonUserId, int $userId): Folder
    {
        $lookup = ['name' => $name, 'parent_id' => $parent->id];
        if ($clientId) {
            $lookup['client_id'] = $clientId;
        }
        if ($commonUserId) {
            $lookup['common_user_id'] = $commonUserId;
        }

        return Folder::firstOrCreate($lookup, [
            'slug' => Str::slug($parent->name.' '.$name),
            'scope' => $clientId ? 'client' : 'lead',
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    private function generalTemplates(): EloquentCollection
    {
        return Folder::query()
            ->where('is_general', true)
            ->where('auto_create_for_clients', true)
            ->where('scope', 'global')
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();
    }

    private function defaultSubfolders(): array
    {
        return [
            'Agreements',
            'Payments',
            'Admin Summary',
            'Applicant Documents',
            'Inviter Documents',
            'Application Unit',
            'Documentation Unit',
            'Invoices',
            'Final Documents',
        ];
    }
}
