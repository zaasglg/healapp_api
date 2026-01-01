@extends('admin.layouts.app')

@section('title', 'Редактирование: ' . $organization->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.organizations.show', ['organization' => $organization, 'token' => session('admin_token')]) }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-500 transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Назад
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden max-w-3xl">
    <div class="p-6 border-b border-slate-100">
        <h1 class="text-xl font-bold text-slate-800">Редактирование организации</h1>
        <p class="text-slate-500 mt-1">{{ $organization->name }}</p>
    </div>

    <form method="POST" action="{{ route('admin.organizations.update', ['organization' => $organization, 'token' => session('admin_token')]) }}" class="p-6">
        @csrf @method('PUT')
        <div class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Название</label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('name')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Адрес</label>
                <input type="text" name="address" value="{{ old('address', $organization->address) }}" 
                       class="w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 px-4 py-2.5">
                @error('address')<p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-6 pt-6 border-t border-slate-100">
            <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-sm font-medium shadow-sm">Сохранить</button>
        </div>
    </form>
</div>
@endsection
