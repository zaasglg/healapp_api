<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diary;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => $this->applyNonTestUsersFilter(User::query())->count(),
            'organizations' => $this->applyNonTestOrganizationsFilter(Organization::query())->count(),
            'patients' => $this->applyNonTestPatientsFilter(Patient::query())->count(),
            'diaries' => $this->applyNonTestDiariesFilter(Diary::query())->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    private function applyTestUsersFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery) {
            $subQuery->where('first_name', 'like', '0%')
                ->orWhere('last_name', 'like', '0%')
                ->orWhereHas('organization', function (Builder $orgQuery) {
                    $orgQuery->where('name', 'like', '0%');
                });
        });
    }

    private function applyNonTestUsersFilter(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('first_name')
                    ->orWhere('first_name', 'not like', '0%');
            })
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('last_name')
                    ->orWhere('last_name', 'not like', '0%');
            })
            ->whereDoesntHave('organization', function (Builder $orgQuery) {
                $orgQuery->where('name', 'like', '0%');
            });
    }

    private function applyNonTestOrganizationsFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery) {
            $subQuery->whereNull('name')
                ->orWhere('name', 'not like', '0%');
        });
    }

    private function applyNonTestPatientsFilter(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('organization', function (Builder $orgQuery) {
                $orgQuery->where('name', 'like', '0%');
            })
            ->whereDoesntHave('owner', function (Builder $ownerQuery) {
                $this->applyTestUsersFilter($ownerQuery);
            });
    }

    private function applyNonTestDiariesFilter(Builder $query): Builder
    {
        return $query->whereHas('patient', function (Builder $patientQuery) {
            $this->applyNonTestPatientsFilter($patientQuery);
        });
    }
}
