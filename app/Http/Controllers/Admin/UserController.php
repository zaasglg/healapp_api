<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['organization', 'roles']);
        $tab = $request->get('tab');

        if (! in_array($tab, ['normal', 'test'], true)) {
            $tab = 'normal';
        }

        if ($tab === 'test') {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '0%')
                    ->orWhere('last_name', 'like', '0%')
                    ->orWhereHas('organization', function ($orgQuery) {
                        $orgQuery->where('name', 'like', '0%');
                    });
            });
        } else {
            $query->where(function ($q) {
                $q->where(function ($nameQuery) {
                    $nameQuery->whereNull('first_name')
                        ->orWhere('first_name', 'not like', '0%');
                })->where(function ($lastNameQuery) {
                    $lastNameQuery->whereNull('last_name')
                        ->orWhere('last_name', 'not like', '0%');
                })->whereDoesntHave('organization', function ($orgQuery) {
                    $orgQuery->where('name', 'like', '0%');
                });
            });
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);
        $users->appends($request->query());

        return view('admin.users.index', compact('users', 'tab'));
    }

    public function show(User $user)
    {
        $user->load(['organization', 'roles', 'patients', 'ownedPatients']);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'city' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Пользователь обновлён');
    }

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return redirect()->route('admin.users.show', [
            'user' => $user,
            'token' => session('admin_token'),
        ])->with('success', 'Пароль пользователя успешно сброшен');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь удалён');
    }

    public function destroyAll(User $user)
    {
        DB::transaction(function () use ($user) {
            $this->deleteUserFully($user);
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь и ВСЕ его данные полностью удалены');
    }

    public function destroyAllUsers()
    {
        $currentUserId = auth()->id();

        // Получаем всех пользователей, кроме текущего
        $users = User::where('id', '!=', $currentUserId)->get();
        $count = $users->count();

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $this->deleteUserFully($user);
            }
        });

        return redirect()->route('admin.users.index')
            ->with('success', "Удалено пользователей: {$count}");
    }

    private function deleteUserFully(User $user)
    {
        // Удаляем созданных/владеемых пациентов и их данные
        foreach ($user->ownedPatients as $patient) {
            // Удаляем дневник
            if ($patient->diary) {
                $patient->diary->entries()->delete();
                $patient->diary->alarms()->delete();
                // Удаляем доступы к дневнику
                $patient->diary->accessUsers()->detach();

                $patient->diary->delete();
            }

            // Удаляем задачи и шаблоны
            $patient->tasks()->delete();
            $patient->taskTemplates()->delete();

            $patient->delete();
        }

        // Удаляем организации, где пользователь владелец
        foreach ($user->ownedOrganizations as $org) {
            $org->delete();
        }

        // Отвязываем от пациентов (где сиделка/врач)
        $user->assignedPatients()->detach();

        // Удаляем приглашения
        $user->sentInvitations()->delete();

        // Удаляем самого пользователя
        $user->delete();
    }
}
