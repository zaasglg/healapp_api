<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diary;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'organizations' => Organization::count(),
            'patients' => Patient::count(),
            'diaries' => Diary::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
