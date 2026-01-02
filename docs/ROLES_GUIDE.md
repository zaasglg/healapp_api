# 🎭 Полное руководство по ролям в HealApp API

## 📋 Содержание

1. [Обзор системы ролей](#обзор-системы-ролей)
2. [Роли в организации](#роли-в-организации)
3. [Таблица прав доступа](#таблица-прав-доступа)
4. [Особенности для Пансионата](#особенности-для-пансионата)
5. [Особенности для Агентства](#особенности-для-агентства)
6. [Как проверять роли в коде](#как-проверять-роли-в-коде)
7. [Примеры использования](#примеры-использования)

---

## Обзор системы ролей

HealApp использует **Spatie Laravel Permission** для управления ролями и правами доступа.

### Основные концепции:

```
User (Пользователь)
  ├─ type (UserType) - тип аккаунта
  │   ├─ CLIENT - клиент/родственник
  │   ├─ PRIVATE_CAREGIVER - частная сиделка
  │   └─ ORGANIZATION - сотрудник организации
  │
  ├─ organization_id - привязка к организации
  │
  └─ roles (Spatie) - роли внутри организации
      ├─ owner - владелец
      ├─ admin - администратор
      ├─ manager - менеджер
      ├─ doctor - врач
      └─ caregiver - сиделка
```

---

## Роли в организации

### 🔴 OWNER (Владелец)

**Кто это:**
- Создатель организации (Пансионат или Агентство)
- Назначается автоматически при регистрации организации

**Права:**
- ✅ **ВСЕ** права на организацию
- ✅ Управление сотрудниками (приглашение, удаление, изменение ролей)
- ✅ Редактирование настроек организации
- ✅ Создание, редактирование, удаление пациентов
- ✅ Создание, редактирование, удаление задач
- ✅ Управление доступом к дневникам
- ✅ Просмотр всей статистики

**Код проверки:**
```php
$user->hasRole('owner')
$user->isOwner()
```

**Пример использования:**
```php
if ($user->isOwner()) {
    // Только владелец может удалить организацию
    $organization->delete();
}
```

---

### 🟠 ADMIN (Администратор)

**Кто это:**
- Доверенное лицо владельца
- Назначается владельцем через приглашение

**Права:**
- ✅ Почти все права (кроме удаления организации)
- ✅ Управление сотрудниками
- ✅ Создание и редактирование пациентов
- ✅ Создание и редактирование задач
- ✅ Управление доступом к дневникам
- ✅ Просмотр статистики
- ❌ Не может удалить организацию
- ❌ Не может изменить роль владельца

**Код проверки:**
```php
$user->hasRole('admin')
$user->isAdmin() // возвращает true для owner И admin
$user->canManageEmployees() // true для owner и admin
```

**Пример использования:**
```php
if ($user->canManageEmployees()) {
    // Admin может приглашать сотрудников
    Invitation::create([...]);
}
```

---

### 🟡 MANAGER (Менеджер)

**Кто это:**
- Координатор работы (обычно в Агентстве)
- Назначается через приглашение

**Права:**
- ✅ Создание и редактирование пациентов
- ✅ Создание и редактирование задач
- ✅ Назначение сотрудников на пациентов (в Агентстве)
- ✅ Просмотр всех дневников своей организации
- ❌ Не может управлять сотрудниками
- ❌ Не может изменять настройки организации

**Код проверки:**
```php
$user->hasRole('manager')
```

**Пример использования:**
```php
if ($user->hasRole('manager')) {
    // Менеджер может назначить сиделку на пациента
    $patient->assignedUsers()->attach($caregiverId);
}
```

---

### 🟢 DOCTOR (Врач)

**Кто это:**
- Медицинский специалист в организации
- Назначается через приглашение

**Права:**
- ✅ Просмотр всех пациентов организации
- ✅ Просмотр всех дневников
- ✅ Заполнение дневников
- ✅ **Создание** маршрутных листов (задач)
- ✅ **Редактирование** маршрутных листов
- ❌ **Не может** создавать/редактировать карточки пациентов (только чтение)
- ❌ **Не может** удалять задачи
- ❌ Не может управлять сотрудниками

**Код проверки:**
```php
$user->hasRole('doctor')
$user->canCreateTasks() // true для owner, admin, doctor
```

**Пример использования:**
```php
// В Пансионате: врач видит всех пациентов, но не может их редактировать
if ($user->hasRole('doctor') && $user->organization->isBoardingHouse()) {
    // Только чтение
    return response()->json(['message' => 'У вас нет прав на редактирование пациентов'], 403);
}
```

---

### 🔵 CAREGIVER (Сиделка)

**Кто это:**
- Исполнитель задач по уходу
- Назначается через приглашение

**Права:**
- ✅ Просмотр назначенных пациентов
- ✅ Просмотр дневников (своих пациентов)
- ✅ Заполнение дневников
- ✅ **Выполнение** задач (отметка как выполненных)
- ✅ Просмотр задач (в Пансионате - только своих)
- ❌ **Не может** создавать задачи
- ❌ **Не может** редактировать задачи
- ❌ **Не может** удалять задачи
- ❌ **Не может** создавать/редактировать пациентов (только чтение)
- ❌ Не может управлять сотрудниками

**Код проверки:**
```php
$user->hasRole('caregiver')
$user->canCompleteTasks() // true для caregiver
```

**Пример использования:**
```php
// Сиделка может только выполнить задачу
if ($user->hasRole('caregiver')) {
    $task->markAsCompleted($user);
} else {
    // Owner/Admin/Doctor могут редактировать
    $task->update($request->validated());
}
```

---

## Таблица прав доступа

### 📊 Пациенты (Patients)

| Действие | Owner | Admin | Manager | Doctor | Caregiver |
|----------|:-----:|:-----:|:-------:|:------:|:---------:|
| **Просмотр** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Создание** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Редактирование** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Удаление** | ✅ | ✅ | ❌ | ❌ | ❌ |

**Код:**
```php
// В PatientPolicy
public function create(User $user): bool
{
    // Врач и сиделка в Пансионате НЕ могут создавать
    if ($user->organization && $user->organization->isBoardingHouse()) {
        if ($user->hasAnyRole(['doctor', 'caregiver'])) {
            return false;
        }
    }
    return $user->hasAnyRole(['owner', 'admin', 'manager']);
}
```

---

### 📖 Дневники (Diaries)

| Действие | Owner | Admin | Manager | Doctor | Caregiver |
|----------|:-----:|:-----:|:-------:|:------:|:---------:|
| **Просмотр** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Создание** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Редактирование настроек** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Заполнение записей** | ✅ | ✅ | ✅ | ✅ | ✅ |

**Код:**
```php
// Все могут заполнять дневники
if ($user->hasPermission('diaries.fill')) {
    $diary->entries()->create([...]);
}

// Только admin может изменить настройки дневника
if ($user->hasPermission('diaries.edit')) {
    $diary->update(['name' => 'Новое название']);
}
```

---

### 📋 Маршрутные листы (Tasks / Route Sheets)

| Действие | Owner | Admin | Manager | Doctor | Caregiver |
|----------|:-----:|:-----:|:-------:|:------:|:---------:|
| **Просмотр** | ✅ | ✅ | ✅ | ✅ | ✅ (только свои в Пансионате) |
| **Создание** | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Редактирование** | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Удаление** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Выполнение** | ✅ | ✅ | ✅ | ✅ | ✅ |

**Код:**
```php
// В TaskPolicy
public function create(User $user): bool
{
    // Сиделка НЕ может создавать задачи
    if ($user->hasRole('caregiver')) {
        return false;
    }
    return $user->hasAnyRole(['doctor', 'admin', 'owner', 'manager']);
}

public function delete(User $user, Task $task): bool
{
    // Врач и сиделка НЕ могут удалять задачи
    if ($user->hasAnyRole(['doctor', 'caregiver'])) {
        return false;
    }
    return $user->hasAnyRole(['admin', 'owner', 'manager']);
}
```

---

### 👥 Управление сотрудниками

| Действие | Owner | Admin | Manager | Doctor | Caregiver |
|----------|:-----:|:-----:|:-------:|:------:|:---------:|
| **Приглашение** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Изменение роли** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Удаление** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Просмотр списка** | ✅ | ✅ | ✅ | ❌ | ❌ |

**Код:**
```php
if ($user->canManageEmployees()) {
    // Создание приглашения
    Invitation::create([
        'organization_id' => $user->organization_id,
        'role' => 'caregiver',
        // ...
    ]);
}
```

---

## Особенности для Пансионата

### 🏠 Boarding House (Пансионат)

**Философия:** Все сотрудники работают вместе, видят всех пациентов.

#### Доступ к пациентам:
```php
// ВСЕ сотрудники видят ВСЕХ пациентов
if ($user->organization->isBoardingHouse()) {
    $patients = Patient::where('organization_id', $user->organization_id)->get();
}
```

#### Доступ к задачам (Route Sheets):
```php
// ВАЖНО: Сиделка видит ТОЛЬКО свои задачи
if ($user->hasRole('caregiver') && $user->organization->isBoardingHouse()) {
    $tasks = Task::where('assigned_to', $user->id)->get();
    // НЕТ orWhereNull('assigned_to')!
}

// Врач, Admin, Owner видят все задачи
if ($user->hasAnyRole(['doctor', 'admin', 'owner'])) {
    $tasks = Task::whereHas('patient', function($q) use ($user) {
        $q->where('organization_id', $user->organization_id);
    })->get();
}
```

#### Права на редактирование:
```php
// Врач и сиделка: ТОЛЬКО ЧТЕНИЕ пациентов
if ($user->hasAnyRole(['doctor', 'caregiver']) && $user->organization->isBoardingHouse()) {
    // Могут просматривать
    $patient = Patient::find($id);
    
    // НЕ могут редактировать
    return response()->json(['message' => 'У вас нет прав на редактирование'], 403);
}
```

---

## Особенности для Агентства

### 🏢 Agency (Патронажное агентство)

**Философия:** Сотрудники назначаются на конкретных пациентов.

#### Доступ к пациентам:
```php
// Сиделка видит только назначенных пациентов
if ($user->hasRole('caregiver') && $user->organization->isAgency()) {
    $patients = $user->assignedPatients; // через pivot-таблицу patient_user
}

// Admin/Manager видят всех пациентов организации
if ($user->hasAnyRole(['admin', 'manager', 'owner'])) {
    $patients = Patient::where('organization_id', $user->organization_id)->get();
}
```

#### Назначение сотрудников:
```php
// Только Admin/Manager могут назначать
if ($user->hasAnyRole(['admin', 'manager', 'owner'])) {
    $patient->assignedUsers()->attach($caregiverId);
}
```

#### Доступ к задачам:
```php
// Сиделка видит задачи назначенных пациентов + неназначенные
if ($user->hasRole('caregiver') && $user->organization->isAgency()) {
    $assignedPatientIds = $user->assignedPatients()->pluck('patients.id');
    
    $tasks = Task::where(function($q) use ($user) {
        $q->where('assigned_to', $user->id)
          ->orWhereNull('assigned_to');
    })
    ->whereIn('patient_id', $assignedPatientIds)
    ->get();
}
```

---

## Как проверять роли в коде

### 1. Проверка конкретной роли

```php
// Spatie метод
if ($user->hasRole('owner')) {
    // Только для владельца
}

// Хелпер в User модели
if ($user->isOwner()) {
    // То же самое
}
```

### 2. Проверка нескольких ролей (ИЛИ)

```php
if ($user->hasAnyRole(['owner', 'admin'])) {
    // Для владельца ИЛИ администратора
}

// Хелпер
if ($user->canManageEmployees()) {
    // То же самое
}
```

### 3. Проверка всех ролей (И)

```php
if ($user->hasAllRoles(['admin', 'doctor'])) {
    // Только если у пользователя ОБЕ роли одновременно
    // (редко используется)
}
```

### 4. Проверка permissions

```php
if ($user->hasPermissionTo('patients.create')) {
    // Может создавать пациентов
}

// Или через can()
if ($user->can('patients.create')) {
    // То же самое
}
```

### 5. В контроллерах

```php
public function store(Request $request)
{
    $user = $request->user();
    
    // Вариант 1: Ручная проверка
    if (!$user->hasAnyRole(['owner', 'admin'])) {
        return response()->json(['message' => 'Недостаточно прав'], 403);
    }
    
    // Вариант 2: Через Policy
    $this->authorize('create', Patient::class);
    
    // Вариант 3: Через middleware (в routes)
    // Route::post('/patients')->middleware('role:owner|admin');
}
```

### 6. В Blade (если используете)

```blade
@role('owner')
    <button>Удалить организацию</button>
@endrole

@hasanyrole('owner|admin')
    <a href="/employees">Управление сотрудниками</a>
@endhasanyrole

@can('patients.create')
    <button>Создать пациента</button>
@endcan
```

---

## Примеры использования

### Пример 1: Создание пациента

```php
// PatientController@store
public function store(StorePatientRequest $request): JsonResponse
{
    $user = $request->user();
    
    // Проверка прав
    if ($user->organization_id) {
        $organization = $user->organization;
        
        // В Пансионате: врач и сиделка НЕ могут создавать
        if ($organization && $organization->isBoardingHouse()) {
            if ($user->hasAnyRole(['doctor', 'caregiver'])) {
                return response()->json([
                    'message' => 'У вас нет прав на создание пациентов. Доступ только для чтения.',
                ], 403);
            }
        }
    }
    
    // Создание пациента
    $patient = Patient::create([
        'organization_id' => $user->organization_id,
        'creator_id' => $user->id,
        ...$request->validated(),
    ]);
    
    return response()->json($patient, 201);
}
```

### Пример 2: Просмотр задач

```php
// RouteSheetController@index
public function index(Request $request): JsonResponse
{
    $user = $request->user();
    $query = Task::query();
    
    if ($user->hasRole('caregiver')) {
        $isPensionCaregiver = $user->organization_id 
            && $user->organization 
            && $user->organization->isBoardingHouse();
        
        if ($isPensionCaregiver) {
            // Пансионат: СТРОГО только свои задачи
            $query->where('assigned_to', $user->id);
        } else {
            // Агентство: свои + неназначенные
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereNull('assigned_to');
            });
            
            // Только пациенты, к которым привязан
            $assignedPatientIds = $user->assignedPatients()->pluck('patients.id');
            $query->whereIn('patient_id', $assignedPatientIds);
        }
    } elseif ($user->hasAnyRole(['doctor', 'admin', 'owner', 'manager'])) {
        // Видят все задачи своей организации
        $query->whereHas('patient', function($q) use ($user) {
            $q->where('organization_id', $user->organization_id);
        });
    }
    
    $tasks = $query->with(['patient', 'assignedUser'])->get();
    return response()->json($tasks);
}
```

### Пример 3: Приглашение сотрудника

```php
// InvitationController@createEmployeeInvite
public function createEmployeeInvite(Request $request): JsonResponse
{
    $user = $request->user();
    
    // Проверка прав
    if (!$user->canManageEmployees()) {
        return response()->json(['message' => 'Недостаточно прав'], 403);
    }
    
    if (!$user->organization_id) {
        return response()->json(['message' => 'У вас нет организации'], 404);
    }
    
    // Создание приглашения
    $invitation = Invitation::create([
        'organization_id' => $user->organization_id,
        'inviter_id' => $user->id,
        'token' => Invitation::generateToken(),
        'type' => Invitation::TYPE_EMPLOYEE,
        'role' => $request->role, // admin, manager, doctor, caregiver
        'status' => Invitation::STATUS_PENDING,
        'expires_at' => now()->addDays(7),
    ]);
    
    return response()->json([
        'message' => 'Приглашение создано',
        'invitation' => $invitation,
        'invite_url' => config('app.frontend_url') . '/register?invite_token=' . $invitation->token,
    ], 201);
}
```

### Пример 4: Регистрация по приглашению

```php
// AuthController@register
public function register(RegisterRequest $request): JsonResponse
{
    // ... создание пользователя ...
    
    // Обработка приглашения
    if ($request->has('invite_token')) {
        $invitation = Invitation::where('token', $request->invite_token)->first();
        
        if ($invitation && $invitation->isValid()) {
            // Привязка к организации
            $user->organization_id = $invitation->organization_id;
            $user->save();
            
            // Назначение роли
            $user->assignRole($invitation->role);
            
            // Отметка приглашения как принятого
            $invitation->markAsAccepted($user);
        }
    }
    
    return response()->json([
        'message' => 'SMS отправлен',
        'phone' => $user->phone,
    ]);
}
```

---

## 🎯 Краткая шпаргалка

### Кто что может:

| Действие | Owner | Admin | Manager | Doctor | Caregiver |
|----------|:-----:|:-----:|:-------:|:------:|:---------:|
| Управлять сотрудниками | ✅ | ✅ | ❌ | ❌ | ❌ |
| Создавать пациентов | ✅ | ✅ | ✅ | ❌ | ❌ |
| Редактировать пациентов | ✅ | ✅ | ✅ | ❌ | ❌ |
| Просматривать пациентов | ✅ | ✅ | ✅ | ✅ | ✅ |
| Создавать задачи | ✅ | ✅ | ✅ | ✅ | ❌ |
| Редактировать задачи | ✅ | ✅ | ✅ | ✅ | ❌ |
| Удалять задачи | ✅ | ✅ | ✅ | ❌ | ❌ |
| Выполнять задачи | ✅ | ✅ | ✅ | ✅ | ✅ |
| Заполнять дневники | ✅ | ✅ | ✅ | ✅ | ✅ |
| Создавать дневники | ✅ | ✅ | ✅ | ❌ | ❌ |

### Методы проверки:

```php
// Роли
$user->hasRole('owner')
$user->hasAnyRole(['owner', 'admin'])
$user->isOwner()
$user->isAdmin()

// Хелперы
$user->canManageEmployees()      // owner, admin
$user->canManageAccess()          // owner, admin
$user->canCreatePatients()        // owner, admin, manager
$user->canCreateTasks()           // owner, admin, manager, doctor
$user->canCompleteTasks()         // все

// Permissions
$user->hasPermissionTo('patients.create')
$user->can('patients.create')
```

---

**Документация актуальна на 03.01.2026**

*Для вопросов и предложений: [GitHub Issues](https://github.com/your-repo/issues)*

