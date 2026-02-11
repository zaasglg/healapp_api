@extends('admin.layouts.app')

@section('title', 'Пользователь: ' . $user->full_name)

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.users.index', ['token' => session('admin_token')]) }}"
            class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-500 transition text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Назад к списку
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                @if($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-16 h-16 rounded-2xl object-cover">
                @else
                    <div
                        class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-200 to-primary-400 flex items-center justify-center text-white font-bold text-xl">
                        {{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-xl font-bold text-slate-800">{{ $user->full_name }}</h1>
                    <p class="text-slate-500">ID: #{{ $user->id }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.users.edit', ['user' => $user, 'token' => session('admin_token')]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 text-amber-600 rounded-xl text-sm font-medium hover:bg-amber-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Редактировать
                </a>
                <form method="POST"
                    action="{{ route('admin.users.destroy', ['user' => $user, 'token' => session('admin_token')]) }}"
                    onsubmit="return confirm('Удалить пользователя?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-xl text-sm font-medium hover:bg-red-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Удалить
                    </button>
                </form>
                <form method="POST"
                    action="{{ route('admin.users.destroy-all', ['user' => $user, 'token' => session('admin_token')]) }}"
                    onsubmit="return confirm('Вы уверены? Это удалит ВСЕ данные пользователя (заказы, записи и т.д.). Это действие необратимо!')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Удалить все
                    </button>
                </form>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Телефон</div>
                    <div class="text-slate-800 font-medium">{{ $user->phone }}</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Тип</div>
                    <div class="text-slate-800 font-medium">{{ $user->type?->value ?? '-' }}</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Город</div>
                    <div class="text-slate-800 font-medium">{{ $user->city ?? '-' }}</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Организация</div>
                    <div class="text-slate-800 font-medium">
                        @if($user->organization)
                            <a href="{{ route('admin.organizations.show', ['organization' => $user->organization, 'token' => session('admin_token')]) }}"
                                class="text-primary-500 hover:text-primary-600">{{ $user->organization->name }}</a>
                        @else - @endif
                    </div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Роли</div>
                    <div class="text-slate-800 font-medium">{{ $user->roles->pluck('name')->join(', ') ?: '-' }}</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Создан</div>
                    <div class="text-slate-800 font-medium">{{ $user->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Согласие</div>
                    <div class="text-slate-800 font-medium">{{ $user->is_agree ? 'Да' : 'Нет' }}</div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-100">
                <h2 class="text-lg font-semibold text-slate-800">Сброс пароля</h2>
                <p class="text-sm text-slate-500 mt-1">Укажите новый пароль для этого пользователя.</p>

                <form method="POST"
                    action="{{ route('admin.users.reset-password', ['user' => $user, 'token' => session('admin_token')]) }}"
                    class="mt-4 max-w-xl">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 mb-2">Новый пароль</label>
                            <input id="password" type="password" name="password"
                                class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                            @error('password')
                                <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password_confirmation"
                                class="block text-sm font-medium text-slate-700 mb-2">Повторите пароль</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                        </div>
                    </div>

                    <button type="submit"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-primary-50 text-primary-700 rounded-xl text-sm font-medium hover:bg-primary-100 transition">
                        Сбросить пароль
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
