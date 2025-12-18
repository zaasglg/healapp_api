# 📖 API Дневника подопечного

## Описание

Дневник подопечного — это цифровой журнал для фиксации показателей здоровья пациента. Каждый пациент имеет один дневник, который содержит записи показателей и закреплённые параметры с таймерами.

---

## Базовая информация

**Base URL**: `https://your-api-url.com/api/v1`

**Аутентификация**: Bearer Token
```
Authorization: Bearer <token>
```

**Content-Type**: `application/json`

---

## Endpoints

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `POST` | `/diary/create` | Создать дневник |
| `GET` | `/diary` | Получить дневник с записями |
| `POST` | `/diary` | Добавить запись в дневник |
| `PATCH` | `/diary/pinned` | Обновить закреплённые показатели |
| `GET` | `/stats/chart` | Получить данные для графика |

---

## 1. Создать дневник

Создаёт новый дневник для пациента. Если дневник уже существует — возвращает ошибку 409.

### Request

```http
POST /api/v1/diary/create
Authorization: Bearer <token>
Content-Type: application/json
```

### Body

```json
{
  "patient_id": 1,
  "pinned_parameters": [
    {
      "key": "blood_pressure",
      "interval_minutes": 60
    },
    {
      "key": "temperature",
      "interval_minutes": 120
    }
  ],
  "settings": null
}
```

| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| patient_id | integer | ✅ | ID пациента |
| pinned_parameters | array | ❌ | Массив закреплённых показателей |
| pinned_parameters[].key | string | ✅ | Ключ показателя |
| pinned_parameters[].interval_minutes | integer | ✅ | Интервал замера (минуты) |
| settings | object | ❌ | Настройки дневника |

### Response 201 (Created)

```json
{
  "id": 1,
  "patient_id": 1,
  "pinned_parameters": [
    {
      "key": "blood_pressure",
      "interval_minutes": 60
    },
    {
      "key": "temperature",
      "interval_minutes": 120
    }
  ],
  "settings": null,
  "entries": [],
  "created_at": "2024-12-18T10:00:00.000000Z",
  "updated_at": "2024-12-18T10:00:00.000000Z"
}
```

### Response 409 (Conflict)

```json
{
  "message": "Diary already exists for this patient",
  "diary_id": 1
}
```

### Response 403 (Forbidden)

```json
{
  "message": "You do not have access to this patient."
}
```

---

## 2. Получить дневник

Возвращает дневник пациента с записями за указанный период.

### Request

```http
GET /api/v1/diary?patient_id=1&from_date=2024-12-01&to_date=2024-12-18
Authorization: Bearer <token>
```

### Query Parameters

| Параметр | Тип | Обязательно | Описание |
|----------|-----|-------------|----------|
| patient_id | integer | ✅ | ID пациента |
| from_date | string | ❌ | Начальная дата (YYYY-MM-DD) |
| to_date | string | ❌ | Конечная дата (YYYY-MM-DD) |

### Response 200

```json
{
  "id": 1,
  "patient_id": 1,
  "pinned_parameters": [
    {
      "key": "blood_pressure",
      "interval_minutes": 60,
      "last_recorded_at": "2024-12-18T14:30:00.000000Z"
    }
  ],
  "settings": null,
  "entries": [
    {
      "id": 1,
      "diary_id": 1,
      "author_id": 1,
      "type": "physical",
      "key": "blood_pressure",
      "value": {
        "systolic": 120,
        "diastolic": 80
      },
      "notes": "После обеда",
      "recorded_at": "2024-12-18T14:30:00.000000Z",
      "created_at": "2024-12-18T14:30:00.000000Z",
      "updated_at": "2024-12-18T14:30:00.000000Z"
    },
    {
      "id": 2,
      "diary_id": 1,
      "author_id": 1,
      "type": "care",
      "key": "meal",
      "value": {
        "type": "breakfast",
        "eaten": true,
        "amount": "full"
      },
      "notes": null,
      "recorded_at": "2024-12-18T08:00:00.000000Z",
      "created_at": "2024-12-18T08:00:00.000000Z",
      "updated_at": "2024-12-18T08:00:00.000000Z"
    }
  ],
  "created_at": "2024-12-18T10:00:00.000000Z",
  "updated_at": "2024-12-18T14:30:00.000000Z"
}
```

### Response 404 (Not Found)

```json
{
  "message": "No diary found for this patient. Create one first."
}
```

---

## 3. Добавить запись

Добавляет новую запись показателя в дневник. Если дневник не существует — создаёт автоматически.

### Request

```http
POST /api/v1/diary
Authorization: Bearer <token>
Content-Type: application/json
```

### Body

```json
{
  "patient_id": 1,
  "type": "physical",
  "key": "blood_pressure",
  "value": {
    "systolic": 120,
    "diastolic": 80
  },
  "notes": "Измерение после обеда",
  "recorded_at": "2024-12-18T14:30:00Z"
}
```

| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| patient_id | integer | ✅ | ID пациента |
| type | string | ✅ | Тип записи: `care`, `physical`, `excretion`, `symptom` |
| key | string | ✅ | Ключ показателя (см. таблицу ниже) |
| value | object | ✅ | Значение показателя (JSON) |
| notes | string | ❌ | Заметки |
| recorded_at | datetime | ✅ | Время записи (ISO 8601) |

### Response 201 (Created)

```json
{
  "id": 1,
  "diary_id": 1,
  "author_id": 1,
  "type": "physical",
  "key": "blood_pressure",
  "value": {
    "systolic": 120,
    "diastolic": 80
  },
  "notes": "Измерение после обеда",
  "recorded_at": "2024-12-18T14:30:00.000000Z",
  "created_at": "2024-12-18T14:30:00.000000Z",
  "updated_at": "2024-12-18T14:30:00.000000Z"
}
```

---

## 4. Обновить закреплённые показатели

Обновляет список закреплённых показателей с таймерами.

### Request

```http
PATCH /api/v1/diary/pinned
Authorization: Bearer <token>
Content-Type: application/json
```

### Body

```json
{
  "patient_id": 1,
  "pinned_parameters": [
    {
      "key": "blood_pressure",
      "interval_minutes": 30
    },
    {
      "key": "temperature",
      "interval_minutes": 60
    },
    {
      "key": "pulse",
      "interval_minutes": 120
    }
  ]
}
```

### Response 200

```json
{
  "message": "Pinned parameters updated successfully",
  "diary": {
    "id": 1,
    "patient_id": 1,
    "pinned_parameters": [
      {
        "key": "blood_pressure",
        "interval_minutes": 30
      },
      {
        "key": "temperature",
        "interval_minutes": 60
      },
      {
        "key": "pulse",
        "interval_minutes": 120
      }
    ],
    "settings": null,
    "created_at": "2024-12-18T10:00:00.000000Z",
    "updated_at": "2024-12-18T15:00:00.000000Z"
  }
}
```

---

## 5. Получить данные для графика

Возвращает данные для построения графика динамики показателя.

### Request

```http
GET /api/v1/stats/chart?patient_id=1&key=blood_pressure&period=7_days
Authorization: Bearer <token>
```

### Query Parameters

| Параметр | Тип | Обязательно | Описание |
|----------|-----|-------------|----------|
| patient_id | integer | ✅ | ID пациента |
| key | string | ✅ | Ключ показателя |
| period | string | ❌ | `7_days` (по умолчанию) или `30_days` |

### Response 200

```json
{
  "patient_id": 1,
  "key": "blood_pressure",
  "period": "7_days",
  "data": [
    {
      "id": 1,
      "recorded_at": "2024-12-12T08:00:00Z",
      "value": {
        "systolic": 118,
        "diastolic": 78
      },
      "notes": null
    },
    {
      "id": 2,
      "recorded_at": "2024-12-13T09:00:00Z",
      "value": {
        "systolic": 120,
        "diastolic": 80
      },
      "notes": null
    },
    {
      "id": 3,
      "recorded_at": "2024-12-14T08:30:00Z",
      "value": {
        "systolic": 122,
        "diastolic": 82
      },
      "notes": "После кофе"
    }
  ]
}
```

---

## Типы записей (type)

| Тип | Описание |
|-----|----------|
| `care` | Параметры ухода |
| `physical` | Физикальные параметры |
| `excretion` | Выделения |
| `symptom` | Тягостные симптомы |

---

## Показатели (key) и структура value

### physical — Физикальные параметры

| key | value | Описание |
|-----|-------|----------|
| `temperature` | `{"value": 36.6}` | Температура (°C) |
| `blood_pressure` | `{"systolic": 120, "diastolic": 80}` | Артериальное давление (мм рт.ст.) |
| `pulse` | `{"value": 75}` | Пульс (уд/мин) |
| `saturation` | `{"value": 98}` | Сатурация SpO2 (%) |
| `blood_sugar` | `{"value": 5.5}` | Глюкоза крови (ммоль/л) |
| `respiratory_rate` | `{"value": 16}` | Частота дыхания (в мин) |
| `weight` | `{"value": 70}` | Вес (кг) |

### care — Уход

| key | value | Описание |
|-----|-------|----------|
| `meal` | `{"type": "breakfast/lunch/dinner/snack", "eaten": true, "amount": "full/half/few"}` | Приём пищи |
| `medicine` | `{"name": "Название", "dose": "100mg", "taken": true}` | Приём лекарств |
| `vitamins` | `{"name": "Витамин D", "taken": true}` | Витамины |
| `diaper_change` | `{"done": true, "type": "wet/dirty/both"}` | Смена подгузника |
| `hygiene` | `{"type": "bath/shower/sponge", "done": true}` | Гигиена |
| `skin_moisturizing` | `{"done": true, "area": "body/face/hands"}` | Увлажнение кожи |
| `walk` | `{"duration_minutes": 30}` | Прогулка |
| `cognitive_games` | `{"type": "puzzle/memory/reading", "duration_minutes": 20}` | Когнитивные игры |
| `sleep` | `{"hours": 8, "quality": "good/fair/poor"}` | Сон |

### excretion — Выделения

| key | value | Описание |
|-----|-------|----------|
| `urination` | `{"occurred": true, "color": "normal/dark/light", "notes": ""}` | Мочеиспускание |
| `defecation` | `{"occurred": true, "consistency": "normal/hard/loose", "color": "brown/dark/light"}` | Дефекация |

### symptom — Симптомы

| key | value | Описание |
|-----|-------|----------|
| `pain_level` | `{"level": 3, "location": "head/chest/back/legs/..."}` | Боль (0-10) |
| `nausea` | `{"occurred": true, "severity": "mild/moderate/severe"}` | Тошнота |
| `vomiting` | `{"occurred": true, "times": 1}` | Рвота |
| `dyspnea` | `{"occurred": true, "severity": "mild/moderate/severe"}` | Одышка |
| `itching` | `{"occurred": true, "location": "arms/legs/body"}` | Зуд |
| `cough` | `{"type": "dry/wet", "intensity": "mild/moderate/severe"}` | Кашель |
| `dry_mouth` | `{"occurred": true}` | Сухость во рту |
| `hiccups` | `{"occurred": true, "duration_minutes": 5}` | Икота |
| `taste_disorder` | `{"occurred": true, "type": "metallic/bitter/none"}` | Нарушение вкуса |

---

## Модели данных

### Diary (Дневник)

```json
{
  "id": 1,
  "patient_id": 1,
  "pinned_parameters": [],
  "settings": null,
  "entries": [],
  "created_at": "2024-12-18T10:00:00.000000Z",
  "updated_at": "2024-12-18T10:00:00.000000Z"
}
```

### DiaryEntry (Запись)

```json
{
  "id": 1,
  "diary_id": 1,
  "author_id": 1,
  "type": "physical",
  "key": "blood_pressure",
  "value": {"systolic": 120, "diastolic": 80},
  "notes": "Заметка",
  "recorded_at": "2024-12-18T14:30:00.000000Z",
  "created_at": "2024-12-18T14:30:00.000000Z",
  "updated_at": "2024-12-18T14:30:00.000000Z"
}
```

### PinnedParameter (Закреплённый показатель)

```json
{
  "key": "blood_pressure",
  "interval_minutes": 60,
  "last_recorded_at": "2024-12-18T14:30:00.000000Z"
}
```

---

## Коды ошибок

| Код | Описание |
|-----|----------|
| 400 | Неверный запрос (отсутствуют обязательные параметры) |
| 401 | Не авторизован |
| 403 | Нет доступа к пациенту |
| 404 | Дневник не найден |
| 409 | Дневник уже существует |
| 422 | Ошибка валидации |

---

## Примеры cURL

### Создать дневник

```bash
curl -X POST https://api.example.com/api/v1/diary/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "patient_id": 1,
    "pinned_parameters": [
      {"key": "blood_pressure", "interval_minutes": 60}
    ]
  }'
```

### Получить дневник

```bash
curl -X GET "https://api.example.com/api/v1/diary?patient_id=1&from_date=2024-12-01" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Добавить запись

```bash
curl -X POST https://api.example.com/api/v1/diary \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "patient_id": 1,
    "type": "physical",
    "key": "temperature",
    "value": {"value": 36.6},
    "recorded_at": "2024-12-18T10:00:00Z"
  }'
```

---

**Версия API**: 1.0  
**Дата обновления**: 2024-12-18
