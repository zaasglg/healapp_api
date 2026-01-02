# 🔧 Исправление доступа сиделки к маршрутным листам

## 📋 Проблема

### Описание
Приглашённая сиделка от Пансионата:
- ✅ Получает доступ к дневнику автоматически
- ✅ Видит показатели и будильники
- ❌ **НЕ видит маршрутные листы** (ошибка 403: "У вас нет доступа к этому пациенту")

### Причина

Проблема была в методе `canAccessPatient()` в контроллерах. Логика проверки была неправильной:

```php
// ❌ НЕПРАВИЛЬНО (старая логика)
private function canAccessPatient($user, Patient $patient): bool
{
    // Сначала проверяли isClient()
    if ($user->isClient()) {
        return $patient->owner_id === $user->id;  // ← Возвращало false для сиделки!
    }
    
    // Эта проверка никогда не выполнялась для приглашённой сиделки
    if ($user->organization_id) {
        // ...
    }
}
```

**Почему это не работало:**

1. Приглашённая сиделка имеет `type = 'client'` (как мы обсуждали ранее)
2. Метод `$user->isClient()` возвращал `true`
3. Проверка `$patient->owner_id === $user->id` возвращала `false`
4. Метод возвращал `false` **ДО** проверки `organization_id`
5. Результат: 403 ошибка

---

## ✅ Решение

### Исправленная логика

Изменили порядок проверок - **сначала проверяем принадлежность к организации**:

```php
// ✅ ПРАВИЛЬНО (новая логика)
private function canAccessPatient($user, Patient $patient): bool
{
    // ВАЖНО: Сначала проверяем принадлежность к организации
    // Сотрудник организации (приоритет выше, чем type)
    if ($user->organization_id) {
        $organization = $user->organization;
        
        if (!$organization) {
            return false;
        }

        // Пациент должен принадлежать той же организации
        if ($patient->organization_id !== $organization->id) {
            return false;
        }

        // Владельцы и админы организации имеют доступ ко всем пациентам организации
        if ($user->hasAnyRole(['owner', 'admin', 'manager'])) {
            return true;
        }

        // Пансионат: ВСЕ сотрудники (включая врачей и сиделок) видят ВСЕХ пациентов организации
        if ($organization->isBoardingHouse()) {
            return true;  // ← Теперь сиделка получает доступ!
        }

        // Агентство: только назначенные пациенты (для врачей и сиделок)
        if ($organization->isAgency()) {
            // Admin и Manager видят всех
            if ($user->hasAnyRole(['admin', 'manager'])) {
                return true;
            }
            // Врачи и сиделки - только назначенных
            return $patient->assignedUsers()->where('user_id', $user->id)->exists();
        }
        
        return true;
    }

    // Частная сиделка (без организации) может видеть только назначенных пациентов
    if ($user->isPrivateCaregiver()) {
        return $patient->assignedUsers()->where('user_id', $user->id)->exists();
    }

    // Обычный клиент (без организации) может видеть только своих пациентов
    if ($user->isClient() && !$user->organization_id) {
        return $patient->owner_id === $user->id;
    }

    return false;
}
```

---

## 📁 Исправленные файлы

Метод `canAccessPatient()` был исправлен в следующих контроллерах:

1. ✅ `app/Http/Controllers/Api/v1/RouteSheetController.php`
2. ✅ `app/Http/Controllers/Api/v1/PatientController.php`
3. ✅ `app/Http/Controllers/Api/v1/DiaryController.php`

---

## 🎯 Ключевые изменения

### 1. Порядок проверок

**Было:**
```
1. isClient() → return false для сиделки
2. isPrivateCaregiver()
3. organization_id (никогда не выполнялось)
```

**Стало:**
```
1. organization_id (выполняется первым!) ← Ключевое изменение
2. isPrivateCaregiver()
3. isClient() && !organization_id
```

### 2. Проверка типа клиента

**Было:**
```php
if ($user->isClient()) {
    return $patient->owner_id === $user->id;
}
```

**Стало:**
```php
if ($user->isClient() && !$user->organization_id) {
    return $patient->owner_id === $user->id;
}
```

Добавили проверку `!$user->organization_id`, чтобы отличить:
- Обычного клиента (без организации)
- Сиделку организации (с `organization_id`)

---

## 🧪 Тестирование

### Сценарий 1: Сиделка Пансионата

```http
GET /api/v1/route-sheet?patient_id=1
Authorization: Bearer {caregiver_token}
```

**Результат:**
- ✅ Доступ разрешён
- ✅ Возвращаются задачи пациента (только назначенные на сиделку)

### Сценарий 2: Обычный клиент

```http
GET /api/v1/route-sheet?patient_id=1
Authorization: Bearer {client_token}
```

**Результат:**
- ✅ Доступ разрешён только к своим пациентам (`owner_id = user.id`)
- ❌ 403 для чужих пациентов

### Сценарий 3: Сиделка Агентства

```http
GET /api/v1/route-sheet?patient_id=1
Authorization: Bearer {agency_caregiver_token}
```

**Результат:**
- ✅ Доступ разрешён только к назначенным пациентам
- ❌ 403 для неназначенных пациентов

---

## 📊 Таблица доступа

| Тип пользователя | `type` | `organization_id` | Доступ к пациентам |
|------------------|--------|-------------------|-------------------|
| **Обычный клиент** | `client` | `null` | Только свои (`owner_id`) |
| **Частная сиделка** | `private_caregiver` | `null` | Только назначенные |
| **Сиделка Пансионата** | `client` | `1` (Pension) | **ВСЕ пациенты организации** ✅ |
| **Сиделка Агентства** | `client` | `2` (Agency) | Только назначенные |
| **Врач Пансионата** | `client` | `1` (Pension) | **ВСЕ пациенты организации** ✅ |
| **Admin/Owner** | `organization` | `1` | **ВСЕ пациенты организации** ✅ |

---

## 🔍 Как это работает теперь

### Для Пансионата:

```
Приглашённая сиделка регистрируется
    ↓
user.type = 'client'
user.organization_id = 1 (Pension)
user.role = 'caregiver'
    ↓
Запрос к /route-sheet?patient_id=1
    ↓
canAccessPatient() проверяет:
    1. user.organization_id? → ✅ Да (1)
    2. patient.organization_id === user.organization_id? → ✅ Да (1)
    3. organization.isBoardingHouse()? → ✅ Да
    ↓
return true ✅
    ↓
Сиделка видит маршрутные листы!
```

### Для обычного клиента:

```
Клиент регистрируется
    ↓
user.type = 'client'
user.organization_id = null
user.role = null
    ↓
Запрос к /route-sheet?patient_id=1
    ↓
canAccessPatient() проверяет:
    1. user.organization_id? → ❌ Нет (null)
    2. user.isPrivateCaregiver()? → ❌ Нет
    3. user.isClient() && !user.organization_id? → ✅ Да
    4. patient.owner_id === user.id? → Проверяем
    ↓
return (patient.owner_id === user.id)
```

---

## ⚠️ Важные моменты

### 1. Видимость задач для сиделки Пансионата

Сиделка **видит пациента**, но **видит только свои задачи**:

```php
// RouteSheetController@index (строка 144-148)
if ($isPensionCaregiver) {
    // Сиделка видит ТОЛЬКО задачи, назначенные именно ей
    $query->where('assigned_to', $user->id);
}
```

**Это означает:**
- ✅ Доступ к пациенту разрешён
- ✅ Видит дневник
- ✅ Видит показатели
- ⚠️ Видит **только свои задачи** в маршрутном листе

### 2. Назначение задач

Чтобы сиделка увидела задачи, их нужно **назначить на неё**:

```http
PUT /api/v1/route-sheet/{task_id}
Authorization: Bearer {admin_token}
```

```json
{
  "assigned_to": 5  // ← ID сиделки
}
```

Или использовать метод массового назначения (если реализован):

```http
POST /api/v1/route-sheet/assign-caregiver
```

```json
{
  "patient_id": 1,
  "caregiver_id": 5
}
```

---

## 🎯 Итого

### Что исправили:
1. ✅ Изменили порядок проверок в `canAccessPatient()`
2. ✅ Добавили проверку `!$user->organization_id` для клиентов
3. ✅ Исправили в 3 контроллерах

### Результат:
- ✅ Сиделка Пансионата теперь видит пациентов
- ✅ Сиделка видит дневник
- ✅ Сиделка видит маршрутные листы (свои задачи)
- ✅ Сиделка может заполнять задачи
- ❌ Сиделка НЕ может создавать/редактировать задачи (как и требуется)

---

**Дата исправления:** 03.01.2026

