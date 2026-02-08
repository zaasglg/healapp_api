<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::with(['owner']);
        $tab = $request->get('tab');

        if (! in_array($tab, ['normal', 'test'], true)) {
            $tab = 'normal';
        }

        if ($tab === 'test') {
            $query->where('name', 'like', '0%');
        } else {
            $query->where(function ($q) {
                $q->whereNull('name')
                    ->orWhere('name', 'not like', '0%');
            });
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $organizations = $query->orderBy('created_at', 'desc')->paginate(20);
        $organizations->appends($request->query());

        return view('admin.organizations.index', compact('organizations', 'tab'));
    }

    public function show(Organization $organization)
    {
        $organization->load(['owner', 'employees', 'patients']);

        return view('admin.organizations.show', compact('organization'));
    }

    public function edit(Organization $organization)
    {
        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $organization->update($validated);

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', 'Организация обновлена');
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Организация удалена');
    }
}
