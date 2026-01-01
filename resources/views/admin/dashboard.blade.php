@extends('admin.layouts.app')

@section('title', 'Дашборд')

@section('content')
<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-800">Дашборд</h1>
    <p class="text-slate-500 mt-1">Обзор системы</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
    <a href="{{ route('admin.users.index', ['token' => session('admin_token')]) }}" 
       class="card bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:shadow-md hover:border-primary-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                </svg>
            </div>
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Всего</span>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['users']) }}</div>
        <div class="text-sm text-slate-500 mt-1">Пользователей</div>
    </a>

    <a href="{{ route('admin.organizations.index', ['token' => session('admin_token')]) }}" 
       class="card bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:shadow-md hover:border-primary-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Всего</span>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['organizations']) }}</div>
        <div class="text-sm text-slate-500 mt-1">Организаций</div>
    </a>

    <a href="{{ route('admin.patients.index', ['token' => session('admin_token')]) }}" 
       class="card bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:shadow-md hover:border-primary-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-rose-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Всего</span>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['patients']) }}</div>
        <div class="text-sm text-slate-500 mt-1">Подопечных</div>
    </a>

    <a href="{{ route('admin.diaries.index', ['token' => session('admin_token')]) }}" 
       class="card bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:shadow-md hover:border-primary-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Всего</span>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['diaries']) }}</div>
        <div class="text-sm text-slate-500 mt-1">Дневников</div>
    </a>
</div>
@endsection
