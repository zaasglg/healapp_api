@extends('admin.layouts.app')

@section('title', 'Пользователи')

@section('content')
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Пользователи</h1>
            <p class="text-slate-500 mt-1">Управление пользователями системы</p>
        </div>
        <div>
            <form method="POST" action="{{ route('admin.users.destroy-all-users', ['token' => session('admin_token')]) }}"
                onsubmit="return confirm('ВНИМАНИЕ! Вы собираетесь удалить ВСЕХ пользователей (кроме себя) и ВСЕ их данные (пациенты, организации, записи). Это действие НЕОБРАТИМО. Вы уверены?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Удалить всех
                </button>
            </form>
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
                <a href="{{ route('admin.users.index', array_merge($baseTabQuery, ['tab' => 'normal'])) }}"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $tab === 'normal' ? 'bg-primary-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                    Обычные
                </a>
                <a href="{{ route('admin.users.index', array_merge($baseTabQuery, ['tab' => 'test'])) }}"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $tab === 'test' ? 'bg-primary-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                    Тестовые
                </a>
            </div>

            <form method="GET" class="flex flex-wrap gap-3">
                <input type="hidden" name="token" value="{{ session('admin_token') }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Поиск по имени или телефону..."
                        class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5 text-sm">
                </div>
                <select name="type"
                    class="rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5 text-sm min-w-[160px]">
                    <option value="">Все типы</option>
                    <option value="client" {{ request('type') == 'client' ? 'selected' : '' }}>Клиент</option>
                    <option value="private_caregiver" {{ request('type') == 'private_caregiver' ? 'selected' : '' }}>Частная
                        сиделка</option>
                    <option value="organization" {{ request('type') == 'organization' ? 'selected' : '' }}>Организация
                    </option>
                </select>
                <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm">
                    Найти
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Пользователь</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Телефон</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Тип
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Организация</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Согласие</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Дата
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $hasFirstName = filled($user->first_name);
                            $hasLastName = filled($user->last_name);
                            $shouldUseOrganizationName = ! $hasFirstName || ! $hasLastName;
                            $displayName = $shouldUseOrganizationName
                                ? ($user->organization?->name ?? '-')
                                : $user->full_name;

                            $avatarInitials = mb_strtoupper(mb_substr((string) $user->first_name, 0, 1).mb_substr((string) $user->last_name, 0, 1));

                            if ($avatarInitials === '' && filled($user->organization?->name)) {
                                $avatarInitials = mb_strtoupper(mb_substr($user->organization->name, 0, 2));
                            }

                            if ($avatarInitials === '') {
                                $avatarInitials = '?';
                            }
                        @endphp
                        <tr class="table-row hover:bg-slate-50/50">
                            <td class="px-6 py-4 text-sm text-slate-500">#{{ $user->id }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" alt=""
                                            class="w-10 h-10 rounded-full object-cover shrink-0" style="border-radius: 50%;">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-200 to-primary-400 flex items-center justify-center text-white font-medium text-sm shrink-0"
                                            style="border-radius: 50%;">
                                            {{ $avatarInitials }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-medium text-slate-800">{{ $displayName }}</div>
                                        <div class="text-xs text-slate-400">{{ $user->city ?? 'Город не указан' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $user->phone }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $typeColors = [
                                        'client' => 'bg-blue-50 text-blue-600',
                                        'private_caregiver' => 'bg-violet-50 text-violet-600',
                                        'organization' => 'bg-amber-50 text-amber-600',
                                    ];
                                    $typeLabels = [
                                        'client' => 'Клиент',
                                        'private_caregiver' => 'Сиделка',
                                        'organization' => 'Организация',
                                    ];
                                    $type = $user->type?->value;
                                @endphp
                                <span
                                    class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium {{ $typeColors[$type] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ $typeLabels[$type] ?? $type ?? '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $user->organization?->name ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium {{ $user->is_agree ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $user->is_agree ? 'Да' : 'Нет' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ $user->created_at->format('d.m.Y') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.show', ['user' => $user, 'token' => session('admin_token')]) }}"
                                        class="p-2 text-slate-400 hover:text-primary-500 hover:bg-primary-50 rounded-lg transition"
                                        title="Просмотр">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.users.edit', ['user' => $user, 'token' => session('admin_token')]) }}"
                                        class="p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition"
                                        title="Редактировать">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <form method="POST"
                                        action="{{ route('admin.users.destroy', ['user' => $user, 'token' => session('admin_token')]) }}"
                                        class="inline" onsubmit="return confirm('Удалить пользователя?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                            title="Удалить">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="text-slate-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p>Пользователи не найдены</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
