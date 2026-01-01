@extends('admin.layouts.app')

@section('title', 'Редактирование: ' . $user->full_name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.users.show', ['user' => $user, 'token' => session('admin_token')]) }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-500 transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Назад
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden max-w-3xl">
    <div class="p-6 border-b border-slate-100">
        <h1 class="text-xl font-bold text-slate-800">Редактирование пользователя</h1>
        <p class="text-slate-500 mt-1">{{ $user->full_name }}</p>
    </div>

    <form method="POST" action="{{ route('admin.users.update', ['user' => $user, 'token' => session('admin_token')]) }}" class="p-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Фамилия</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('last_name')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Имя</label>
                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('first_name')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Отчество</label>
                <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('middle_name')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Телефон</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('phone')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-2">Город</label>
                <input type="text" name="city" value="{{ old('city', $user->city) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('city')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-100">
            <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm">
                Сохранить изменения
            </button>
        </div>
    </form>
</div>
@endsection
