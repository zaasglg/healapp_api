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

        if ($search = $request->get('search')) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $diaries = $query->orderBy('created_at', 'desc')->paginate(20);
        $diaries->appends($request->query());

        return view('admin.diaries.index', compact('diaries'));
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
