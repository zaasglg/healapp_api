# API: Вход в систему

## Endpoint

```http
POST /api/v1/auth/login
```

## Описание

Аутентифицирует пользователя по номеру телефона и паролю. Возвращает токен доступа и информацию о пользователе.

## Аутентификация

❌ **Не требуется** (публичный endpoint)

## Заголовки запроса

```
Content-Type: application/json
Accept: application/json
```

## Тело запроса

### Параметры

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `phone` | string | ✅ | Номер телефона пользователя |
| `password` | string | ✅ | Пароль пользователя |

### Пример запроса

```json
{
  "phone": "79001234567",
  "password": "secret123"
}
```

## Пример запроса

### cURL

```bash
curl -X POST https://api.healapp.kz/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "phone": "79001234567",
    "password": "secret123"
  }'
```

### JavaScript (Fetch)

```javascript
const response = await fetch('https://api.healapp.kz/api/v1/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    phone: '79001234567',
    password: 'secret123'
  })
});

const data = await response.json();
```

## Успешный ответ

### Статус: `200 OK`

### Структура ответа

```json
{
  "access_token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz",
  "user": {
    "id": 1,
    "first_name": "Иван",
    "last_name": "Иванов",
    "middle_name": "Петрович",
    "avatar": "http://api.healapp.kz/storage/avatars/1/abc123.jpg",
    "phone": "79001234567",
    "type": "organization",
    "account_type": "pansionat",
    "role": "owner",
    "phone_verified_at": "2024-12-20T10:30:00.000000Z",
    "created_at": "2024-12-20T10:00:00.000000Z",
    "updated_at": "2024-12-23T08:30:00.000000Z",
    "organization": {
      "id": 1,
      "name": "Пансионат 'Забота'",
      "type": "boarding_house"
    }
  }
}
```

### Описание полей

| Поле | Тип | Описание |
|------|-----|----------|
| `access_token` | string | Токен доступа для последующих запросов (Laravel Sanctum) |
| `user` | object | Информация о пользователе |
| `user.id` | integer | Уникальный идентификатор пользователя |
| `user.first_name` | string\|null | Имя |
| `user.last_name` | string\|null | Фамилия |
| `user.middle_name` | string\|null | Отчество |
| `user.avatar` | string\|null | URL аватара пользователя |
| `user.phone` | string | Номер телефона |
| `user.type` | string | Тип пользователя |
| `user.account_type` | string | Тип аккаунта |
| `user.role` | string\|null | Роль в организации |
| `user.organization` | object\|null | Информация об организации |

## Ошибки

### 422 Unprocessable Entity

Неверные учётные данные (неверный телефон или пароль).

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "phone": [
      "Неверные учётные данные"
    ]
  }
}
```

### 401 Unauthorized

Телефон не подтверждён.

```json
{
  "message": "Телефон не подтверждён"
}
```

## Процесс аутентификации

```
┌─────────────────────────────────┐
│      POST /api/v1/auth/login    │
│   {"phone": "...", "password": "..."} │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│   Поиск пользователя по phone   │
└────────────────┬────────────────┘
                 │
            Найден?
         ┌───────┴───────┐
         ▼               ▼
       ┌─────┐        ┌──────┐
       │ Нет │        │  Да  │
       └──┬──┘        └───┬──┘
          │               │
          ▼               ▼
┌────────────────┐ ┌─────────────────────────┐
│ Ошибка 422:    │ │ Проверка пароля         │
│ "Неверные      │ │ Hash::check(...)        │
│ учётные данные"│ └───────────┬─────────────┘
└────────────────┘             │
                           Верный?
                     ┌─────────┴─────────┐
                     ▼                   ▼
                  ┌─────┐             ┌──────┐
                  │ Нет │             │  Да  │
                  └──┬──┘             └───┬──┘
                     │                    │
                     ▼                    ▼
          ┌────────────────┐   ┌──────────────────────┐
          │ Ошибка 422:    │   │ Проверка верификации │
          │ "Неверные      │   │ phone_verified_at    │
          │ учётные данные"│   └──────────┬───────────┘
          └────────────────┘              │
                                    Подтверждён?
                              ┌───────────┴───────────┐
                              ▼                       ▼
                           ┌─────┐                 ┌──────┐
                           │ Нет │                 │  Да  │
                           └──┬──┘                 └───┬──┘
                              │                        │
                              ▼                        ▼
                  ┌────────────────────┐   ┌─────────────────────┐
                  │ Ошибка 401:        │   │ Создание токена     │
                  │ "Телефон не        │   │ Sanctum             │
                  │ подтверждён"       │   │ createToken(...)    │
                  └────────────────────┘   └──────────┬──────────┘
                                                      │
                                                      ▼
                                           ┌──────────────────────┐
                                           │ Response:            │
                                           │ {                    │
                                           │   "access_token":    │
                                           │   "user": {...}      │
                                           │ }                    │
                                           └──────────────────────┘
```

## Примеры использования

### Вход в систему

```javascript
async function login(phone, password) {
  try {
    const response = await fetch('https://api.healapp.kz/api/v1/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        phone: phone,
        password: password
      })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Ошибка входа');
    }

    const data = await response.json();
    
    // Сохраняем токен
    localStorage.setItem('access_token', data.access_token);
    
    // Сохраняем информацию о пользователе
    localStorage.setItem('user', JSON.stringify(data.user));
    
    return data;
  } catch (error) {
    console.error('Ошибка входа:', error);
    throw error;
  }
}

// Использование
login('79001234567', 'secret123')
  .then(data => {
    console.log('Успешный вход!', data);
  })
  .catch(error => {
    console.error('Ошибка:', error);
  });
```

## Примечания

- Токен доступа необходимо сохранять и использовать в заголовке `Authorization: Bearer {token}` для всех защищённых запросов
- Если телефон не подтверждён, необходимо сначала выполнить верификацию через `/api/v1/auth/verify-phone`
- Токен действителен до тех пор, пока пользователь не выйдет из системы через `/api/v1/auth/logout`
- Информация об аватаре (`avatar`) будет `null`, если пользователь ещё не загрузил аватар

