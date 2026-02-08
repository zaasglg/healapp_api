<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diary;
use Illuminate\Http\Request;

class DiaryController extends Controller
{
    public function index(Request $request)
    {
        $query = Diary::with(['patient', 'entries']);
        $tab = $request->get('tab');

        if (! in_array($tab, ['normal', 'test'], true)) {
            $tab = 'normal';
        }

        if ($tab === 'test') {
            $query->whereHas('patient', function ($patientQuery) {
                $patientQuery->where('first_name', 'like', '0%')
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
                $q->whereDoesntHave('patient')
                    ->orWhereHas('patient', function ($patientQuery) {
                        $patientQuery->where(function ($nameQuery) {
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
            });
        }

        if ($search = $request->get('search')) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $diaries = $query->orderBy('created_at', 'desc')->paginate(20);
        $diaries->appends($request->query());

        return view('admin.diaries.index', compact('diaries', 'tab'));
    }

    public function show(Diary $diary)
    {
        $diary->load(['patient', 'entries' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(50);
        }, 'accessUsers']);

        return view('admin.diaries.show', compact('diary'));
    }

    public function destroy(Diary $diary)
    {
        $diary->delete();

        return redirect()->route('admin.diaries.index')
            ->with('success', 'Дневник удалён');
    }
}
