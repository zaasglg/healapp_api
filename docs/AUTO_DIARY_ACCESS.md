# Автоматический доступ к дневникам для сотрудников пансионата

## Описание изменений

Реализован функционал автоматического предоставления доступа к дневникам пациентов при добавлении сотрудника в организацию типа "пансионат".

## Что было сделано

### 1. Обновлен метод `Organization::addEmployee()`
**Файл:** `app/Models/Organization.php`

Теперь при добавлении сотрудника в пансионат автоматически предоставляется доступ уровня "view" ко всем существующим дневникам пациентов организации.

```php
public function addEmployee(User $user, string $role): void
{
    $user->organization_id = $this->id;
    $user->save();
    
    // Убираем старые организационные роли и назначаем новую
    $user->syncRoles([$role]);
    
    // Если это пансионат - автоматически даём доступ ко всем дневникам организации
    if ($this->isBoardingHouse()) {
        $this->grantAccessToDiariesForEmployee($user);
    }
}
```

### 2. Добавлен метод `Organization::grantAccessToDiariesForEmployee()`
**Файл:** `app/Models/Organization.php`

Вспомогательный метод для предоставления доступа к дневникам:

```php
public function grantAccessToDiariesForEmployee(User $user): void
{
    // Получаем все дневники пациентов организации
    $diaries = \App\Models\Diary::whereHas('patient', function ($query) {
        $query->where('organization_id', $this->id);
    })->get();
    
    // Даём доступ "view" к каждому дневнику
    foreach ($diaries as $diary) {
        $diary->grantAccess($user, 'view');
    }
}
```

### 3. Обновлен `InvitationController::accept()`
**Файл:** `app/Http/Controllers/Api/v1/InvitationController.php`

Теперь при принятии приглашения используется метод `addEmployee()` вместо прямого присваивания:

```php
if ($invitation->isEmployeeInvite()) {
    // Привязываем к организации и назначаем роль через метод addEmployee
    $organization = Organization::find($invitation->organization_id);
    if ($organization) {
        $organization->addEmployee($user, $invitation->role);
    }
}
```

### 4. Создан `PatientObserver`
**Файл:** `app/Observers/PatientObserver.php`

Observer автоматически создает дневник и предоставляет доступ всем сотрудникам при:
- Создании нового пациента в пансионате
- Перемещении пациента в пансионат

```php
public function created(Patient $patient): void
{
    if ($patient->organization_id) {
        $organization = $patient->organization;
        
        if ($organization && $organization->isBoardingHouse()) {
            // Создаём дневник для пациента
            $diary = Diary::firstOrCreate(
                ['patient_id' => $patient->id],
                [
                    'pinned_parameters' => [],
                    'settings' => null,
                ]
            );
            
            // Даём доступ всем сотрудникам организации
            $employees = $organization->employees()->get();
            foreach ($employees as $employee) {
                $diary->grantAccess($employee, 'view');
            }
        }
    }
}
```

### 5. Зарегистрирован `PatientObserver`
**Файл:** `app/Providers/AppServiceProvider.php`

```php
Patient::observe(PatientObserver::class);
```

## API Endpoint

### GET `/api/v1/diary/{id}/access`

Получить список пользователей с доступом к дневнику.

**Параметры:**
- `id` - ID дневника

**Ответ:**
```json
[
    {
        "id": 1,
        "first_name": "Иван",
        "last_name": "Иванов",
        "phone": "+79001234567",
        "permission": "view",
        "status": "active",
        "granted_at": "2025-01-09T10:00:00.000000Z"
    }
]
```

## Как это работает

### Сценарий 1: Добавление сотрудника в пансионат
1. Owner/Admin пансионата создает приглашение для сотрудника через API
2. Сотрудник регистрируется по ссылке с токеном
3. `InvitationController::accept()` вызывает `Organization::addEmployee()`
4. Метод `addEmployee()` проверяет тип организации
5. Если это пансионат, вызывается `grantAccessToDiariesForEmployee()`
6. Сотрудник получает доступ уровня "view" ко всем существующим дневникам

### Сценарий 2: Создание пациента в пансионате
1. Owner/Admin создает нового пациента в пансионате
2. `PatientObserver::created()` автоматически срабатывает
3. Создается дневник для пациента (если его нет)
4. Всем сотрудникам пансионата предоставляется доступ уровня "view" к дневнику

### Сценарий 3: Перемещение пациента в пансионат
1. Пациент перемещается в другую организацию (изменяется `organization_id`)
2. `PatientObserver::updated()` автоматически срабатывает
3. Если новая организация - пансионат, создается дневник (если его нет)
4. Всем сотрудникам нового пансионата предоставляется доступ

## Уровни доступа

- `view` - просмотр и добавление записей в дневник
- `edit` - редактирование настроек дневника
- `full` - полный доступ к управлению дневником

По умолчанию сотрудникам пансионата предоставляется доступ уровня `view`.

## Проверка

После внедрения изменений:

1. Создайте приглашение для сотрудника в пансионате
2. Зарегистрируйте сотрудника по ссылке
3. Проверьте доступ через API: `GET /api/v1/diary/{id}/access`
4. Убедитесь, что сотрудник появился в списке с `permission: "view"` и `status: "active"`

## Важно

- Функционал работает **только для пансионатов** (`type = 'boarding_house'`)
- Для агентств (`type = 'agency'`) сохраняется прежняя логика с явным назначением доступа
- Доступ предоставляется автоматически, но может быть отозван вручную через API
- При удалении сотрудника из организации доступ к дневникам не удаляется автоматически (сохраняется история)
