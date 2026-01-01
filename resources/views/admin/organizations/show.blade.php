@extends('admin.layouts.app')

@section('title', 'Организация: ' . $organization->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.organizations.index', ['token' => session('admin_token')]) }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-500 transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Назад к списку
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <div class="p-6 border-b border-slate-100 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-200 to-violet-400 flex items-center justify-center text-white font-bold text-xl">
                {{ mb_substr($organization->name, 0, 2) }}
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">{{ $organization->name }}</h1>
                <p class="text-slate-500">ID: #{{ $organization->id }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.organizations.edit', ['organization' => $organization, 'token' => session('admin_token')]) }}" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 text-amber-600 rounded-xl text-sm font-medium hover:bg-amber-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Редактировать
            </a>
            <form method="POST" action="{{ route('admin.organizations.destroy', ['organization' => $organization, 'token' => session('admin_token')]) }}" onsubmit="return confirm('Удалить?')">
                @csrf @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-xl text-sm font-medium hover:bg-red-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Удалить
                </button>
            </form>
        </div>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Тип</div>
                <div class="text-slate-800 font-medium">{{ $organization->type?->value == 'agency' ? 'Агентство' : 'Пансионат' }}</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Владелец</div>
                <div class="text-slate-800 font-medium">
                    @if($organization->owner)
                        <div class="flex items-center gap-2">
                            @if($organization->owner->avatar)
                                <img src="{{ asset('storage/' . $organization->owner->avatar) }}" alt="" class="w-6 h-6 rounded-full object-cover">
                            @endif
                            <a href="{{ route('admin.users.show', ['user' => $organization->owner, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600">{{ $organization->owner->full_name }}</a>
                        </div>
                    @else - @endif
                </div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Адрес</div>
                <div class="text-slate-800 font-medium">{{ $organization->address ?? '-' }}</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-4">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Создана</div>
                <div class="text-slate-800 font-medium">{{ $organization->created_at->format('d.m.Y H:i') }}</div>
            </div>
        </div>
    </div>
</div>

@if($organization->employees->count())
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-lg font-semibold text-slate-800">Сотрудники ({{ $organization->employees->count() }})</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-slate-50 border-b border-slate-100">
                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Имя</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Телефон</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Роли</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($organization->employees as $employee)
                <tr class="hover:bg-slate-50/50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($employee->avatar)
                                <img src="{{ asset('storage/' . $employee->avatar) }}" alt="" class="w-8 h-8 rounded-full object-cover">
                            @else
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-200 to-primary-400 flex items-center justify-center text-white font-medium text-xs">
                                    {{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}
                                </div>
                            @endif
                            <a href="{{ route('admin.users.show', ['user' => $employee, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600 font-medium">{{ $employee->full_name }}</a>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $employee->phone }}</td>
                    <td class="px-6 py-4 text-sm text-slate-500">{{ $employee->roles->pluck('name')->join(', ') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
