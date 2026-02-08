@extends('admin.layouts.app')

@section('title', 'Дневники')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Дневники</h1>
        <p class="text-slate-500 mt-1">Управление дневниками</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    @php
        $baseTabQuery = array_merge(
            request()->except(['page', 'tab', 'token']),
            ['token' => session('admin_token')]
        );
    @endphp

    <div class="p-5 border-b border-slate-100 bg-slate-50/50">
        <div class="mb-4 inline-flex rounded-xl border border-slate-200 bg-white p-1">
            <a href="{{ route('admin.diaries.index', array_merge($baseTabQuery, ['tab' => 'normal'])) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $tab === 'normal' ? 'bg-primary-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Обычные
            </a>
            <a href="{{ route('admin.diaries.index', array_merge($baseTabQuery, ['tab' => 'test'])) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $tab === 'test' ? 'bg-primary-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Тестовые
            </a>
        </div>

        <form method="GET" class="flex flex-wrap gap-3">
            <input type="hidden" name="token" value="{{ session('admin_token') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по имени подопечного..."
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5 text-sm">
            </div>
            <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm">Найти</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase">ID</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase">Подопечный</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase">Записей</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase">Дата</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($diaries as $diary)
                <tr class="table-row hover:bg-slate-50/50">
                    <td class="px-6 py-4 text-sm text-slate-500">#{{ $diary->id }}</td>
                    <td class="px-6 py-4">
                        @if($diary->patient)
                            <a href="{{ route('admin.patients.show', ['patient' => $diary->patient, 'token' => session('admin_token')]) }}" class="text-primary-500 hover:text-primary-600 font-medium">{{ $diary->patient->first_name }} {{ $diary->patient->last_name }}</a>
                        @else - @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-600">{{ $diary->entries->count() }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500">{{ $diary->created_at->format('d.m.Y') }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.diaries.show', ['diary' => $diary, 'token' => session('admin_token')]) }}" class="p-2 text-slate-400 hover:text-primary-500 hover:bg-primary-50 rounded-lg transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.diaries.destroy', ['diary' => $diary, 'token' => session('admin_token')]) }}" class="inline" onsubmit="return confirm('Удалить?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400">Дневники не найдены</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($diaries->hasPages())<div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">{{ $diaries->links() }}</div>@endif
</div>
@endsection
