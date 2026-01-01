@extends('admin.layouts.app')

@section('title', 'Дневник #' . $diary->id)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.diaries.index', ['token' => session('admin_token')]) }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-500 transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Назад к списку
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <div class="p-6 border-b border-slate-100 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-200 to-amber-400 flex items-center justify-center text-white">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Дневник #{{ $diary->id }}</h1>
                <p class="text-slate-500">{{ $diary->created_at->format('d.m.Y H:i') }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.diaries.destroy', ['diary' => $diary, 'token' => session('admin_token')]) }}" onsubmit="return confirm('Удалить?')">
            @csrf @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-xl text-sm font-medium hover:bg-red-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Удалить
            </button>
        </form>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Подопечный</div>
                <div class="text-slate-800 font-medium">
                    @if($diary->patient)
                        <a href="{{ route('admin.patients.show', ['patient' => $diary->patient, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600">{{ $diary->patient->first_name }} {{ $diary->patient->last_name }}</a>
                    @else - @endif
                </div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Записей</div>
                <div class="text-slate-800 font-medium">{{ $diary->entries->count() }}</div>
            </div>
        </div>
    </div>
</div>

@if($diary->accessUsers->count())
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-lg font-semibold text-slate-800">Доступ ({{ $diary->accessUsers->count() }})</h2>
    </div>
    <div class="divide-y divide-slate-100">
        @foreach($diary->accessUsers as $user)
        <div class="px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                @if($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-8 h-8 rounded-full object-cover">
                @else
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-200 to-primary-400 flex items-center justify-center text-white font-medium text-xs">
                        {{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}
                    </div>
                @endif
                <a href="{{ route('admin.users.show', ['user' => $user, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600 font-medium">{{ $user->full_name }}</a>
            </div>
            <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium {{ $user->pivot->status == 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600' }}">{{ $user->pivot->status ?? '-' }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($diary->entries->count())
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-lg font-semibold text-slate-800">Последние записи</h2>
    </div>
    <div class="divide-y divide-slate-100">
        @foreach($diary->entries as $entry)
        <div class="p-6">
            <div class="text-sm text-slate-400 mb-2">{{ $entry->created_at->format('d.m.Y H:i') }}</div>
            <div class="text-slate-700">{{ $entry->content ?? json_encode($entry->data) }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
