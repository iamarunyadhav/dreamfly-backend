<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Models\Country;

/**
 * The shared, growable country list behind the native-country and
 * visa-destination-country dropdowns on Common User / Client forms. Anyone who
 * can register a lead or client can add to it - typing a new country against
 * "Others" and marking it General posts here so it is available for the next
 * registration too.
 */
class CountriesController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->orderByRaw("name = 'Sri Lanka' desc")
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->ok($countries);
    }

    public function store(Request $request)
    {
        if (! ($request->user()?->can('common-users.create') || $request->user()?->can('clients.create'))) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);
        $name = trim($validated['name']);

        // Case-insensitive dedupe regardless of the database's collation - a
        // second "sri lanka" must not sit beside the seeded "Sri Lanka".
        if (Country::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages([
                'name' => ['This country already exists.'],
            ]);
        }

        $country = Country::create([
            'name' => $name,
            'created_by' => $request->user()->id,
        ]);

        return $this->created($country->only(['id', 'name']), 'Country added.');
    }
}
