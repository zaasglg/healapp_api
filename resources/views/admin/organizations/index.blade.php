@extends('admin.layouts.app')

@section('title', 'Организации')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Организации</h1>
        <p class="text-slate-500 mt-1">Управление организациями</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-5 border-b border-slate-100 bg-slate-50/50">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="hidden" name="token" value="{{ session('admin_token') }}">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Поиск по названию..."
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5 text-sm">
            </div>
            <select name="type" class="rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5 text-sm min-w-[160px]">
                <option value="">Все типы</option>
                <option value="agency" {{ request('type') == 'agency' ? 'selected' : '' }}>Агентство</option>
                <option value="boarding_house" {{ request('type') == 'boarding_house' ? 'selected' : '' }}>Пансионат</option>
            </select>
            <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm">Найти</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Организация</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Тип</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Владелец</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Дата</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($organizations as $org)
                <tr class="table-row hover:bg-slate-50/50">
                    <td class="px-6 py-4 text-sm text-slate-500">#{{ $org->id }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-200 to-violet-400 flex items-center justify-center text-white font-medium text-sm">
                                {{ mb_substr($org->name, 0, 2) }}
                            </div>
                            <div class="font-medium text-slate-800">{{ $org->name }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium {{ $org->type?->value == 'agency' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">
                            {{ $org->type?->value == 'agency' ? 'Агентство' : 'Пансионат' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $org->owner?->full_name ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-slate-500">{{ $org->created_at->format('d.m.Y') }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.organizations.show', ['organization' => $org, 'token' => session('admin_token')]) }}" 
                               class="p-2 text-slate-400 hover:text-primary-500 hover:bg-primary-50 rounded-lg transition" title="Просмотр">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('admin.organizations.edit', ['organization' => $org, 'token' => session('admin_token')]) }}" 
                               class="p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition" title="Редактировать">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.organizations.destroy', ['organization' => $org, 'token' => session('admin_token')]) }}" class="inline" onsubmit="return confirm('Удалить?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Удалить">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400">Организации не найдены</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($organizations->hasPages())<div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">{{ $organizations->links() }}</div>@endif
</div>
@endsection
