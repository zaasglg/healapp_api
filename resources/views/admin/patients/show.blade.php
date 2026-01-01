@extends('admin.layouts.app')

@section('title', 'Подопечный: ' . $patient->first_name . ' ' . $patient->last_name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.patients.index', ['token' => session('admin_token')]) }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-500 transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Назад к списку
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <div class="p-6 border-b border-slate-100 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-rose-200 to-rose-400 flex items-center justify-center text-white font-bold text-xl">
                {{ mb_substr($patient->first_name, 0, 1) }}{{ mb_substr($patient->last_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">{{ $patient->first_name }} {{ $patient->last_name }}</h1>
                <p class="text-slate-500">ID: #{{ $patient->id }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.patients.destroy', ['patient' => $patient, 'token' => session('admin_token')]) }}" onsubmit="return confirm('Удалить?')">
            @csrf @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-xl text-sm font-medium hover:bg-red-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Удалить
            </button>
        </form>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Дата рождения</div>
                <div class="text-slate-800 font-medium">{{ $patient->birth_date ?? '-' }}</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Владелец</div>
                <div class="text-slate-800 font-medium">
                    @if($patient->owner)
                        <div class="flex items-center gap-2">
                            @if($patient->owner->avatar)
                                <img src="{{ asset('storage/' . $patient->owner->avatar) }}" alt="" class="w-6 h-6 rounded-full object-cover">
                            @endif
                            <a href="{{ route('admin.users.show', ['user' => $patient->owner, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600">{{ $patient->owner->full_name }}</a>
                        </div>
                    @else - @endif
                </div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Организация</div>
                <div class="text-slate-800 font-medium">
                    @if($patient->organization)
                        <a href="{{ route('admin.organizations.show', ['organization' => $patient->organization, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600">{{ $patient->organization->name }}</a>
                    @else - @endif
                </div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Создан</div>
                <div class="text-slate-800 font-medium">{{ $patient->created_at->format('d.m.Y H:i') }}</div>
            </div>
        </div>
    </div>
</div>

@if($patient->diaries->count())
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-lg font-semibold text-slate-800">Дневники ({{ $patient->diaries->count() }})</h2>
    </div>
    <div class="divide-y divide-slate-100">
        @foreach($patient->diaries as $diary)
        <div class="px-6 py-4 flex justify-between items-center hover:bg-slate-50/50">
            <div>
                <div class="font-medium text-slate-800">Дневник #{{ $diary->id }}</div>
                <div class="text-sm text-slate-500">{{ $diary->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <a href="{{ route('admin.diaries.show', ['diary' => $diary, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600 text-sm font-medium">Просмотр →</a>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
