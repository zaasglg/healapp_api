<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::with(['owner', 'organization', 'creator']);
        $tab = $request->get('tab');

        if (! in_array($tab, ['normal', 'test'], true)) {
            $tab = 'normal';
        }

        if ($tab === 'test') {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '0%')
                    ->orWhere('last_name', 'like', '0%')
                    ->orWhereHas('organization', function ($orgQuery) {
                        $orgQuery->where('name', 'like', '0%');
                    })
                    ->orWhereHas('owner', function ($ownerQuery) {
                        $ownerQuery->where('first_name', 'like', '0%')
                            ->orWhere('last_name', 'like', '0%')
                            ->orWhereHas('organization', function ($orgQuery) {
                                $orgQuery->where('name', 'like', '0%');
                            });
                    });
            });
        } else {
            $query->where(function ($q) {
                $q->where(function ($nameQuery) {
                    $nameQuery->whereNull('first_name')
                        ->orWhere('first_name', 'not like', '0%');
                })->where(function ($lastNameQuery) {
                    $lastNameQuery->whereNull('last_name')
                        ->orWhere('last_name', 'not like', '0%');
                })->whereDoesntHave('organization', function ($orgQuery) {
                    $orgQuery->where('name', 'like', '0%');
                })->whereDoesntHave('owner', function ($ownerQuery) {
                    $ownerQuery->where('first_name', 'like', '0%')
                        ->orWhere('last_name', 'like', '0%')
                        ->orWhereHas('organization', function ($orgQuery) {
                            $orgQuery->where('name', 'like', '0%');
                        });
                });
            });
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('created_at', 'desc')->paginate(20);
        $patients->appends($request->query());

        return view('admin.patients.index', compact('patients', 'tab'));
    }

    public function show(Patient $patient)
    {
        $patient->load(['owner', 'organization', 'creator', 'diaries']);

        return view('admin.patients.show', compact('patient'));
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();

        return redirect()->route('admin.patients.index')
            ->with('success', 'Подопечный удалён');
    }
}
