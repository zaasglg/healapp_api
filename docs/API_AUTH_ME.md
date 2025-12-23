# API: Получить текущего пользователя

## Endpoint

```http
GET /api/v1/auth/me
```

## Описание

Возвращает информацию о текущем аутентифицированном пользователе. Требует действительный токен доступа.

## Аутентификация

✅ **Требуется**: Bearer Token (Laravel Sanctum)

## Заголовки запроса

```
Authorization: Bearer {access_token}
```

## Параметры запроса

Нет параметров.

## Пример запроса

### cURL

```bash
curl -X GET https://api.healapp.kz/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json"
```

### JavaScript (Fetch)

```javascript
const response = await fetch('https://api.healapp.kz/api/v1/auth/me', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${accessToken}`,
    'Accept': 'application/json'
  }
});

const data = await response.json();
```

## Успешный ответ

### Статус: `200 OK`

### Структура ответа

```json
{
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
```

### Описание полей

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | integer | Уникальный идентификатор пользователя |
| `first_name` | string\|null | Имя |
| `last_name` | string\|null | Фамилия |
| `middle_name` | string\|null | Отчество |
| `avatar` | string\|null | URL аватара пользователя |
| `phone` | string | Номер телефона |
| `type` | string | Тип пользователя: `client`, `private_caregiver`, `organization` |
| `account_type` | string | Тип аккаунта: `client`, `specialist`, `pansionat`, `agency` |
| `role` | string\|null | Роль в организации (если есть): `owner`, `admin`, `doctor`, `caregiver` |
| `phone_verified_at` | datetime\|null | Дата и время подтверждения телефона |
| `created_at` | datetime | Дата создания аккаунта |
| `updated_at` | datetime | Дата последнего обновления |
| `organization` | object\|null | Информация об организации (если пользователь принадлежит организации) |
| `organization.id` | integer | ID организации |
| `organization.name` | string | Название организации |
| `organization.type` | string | Тип организации: `boarding_house`, `agency` |

## Ошибки

### 401 Unauthorized

Токен отсутствует, недействителен или истёк.

```json
{
  "message": "Unauthenticated."
}
```

## Примеры использования

### Получение информации о текущем пользователе

```javascript
async function getCurrentUser() {
  try {
    const response = await fetch('https://api.healapp.kz/api/v1/auth/me', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
        'Accept': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Ошибка получения данных пользователя');
    }

    const user = await response.json();
    console.log('Текущий пользователь:', user);
    return user;
  } catch (error) {
    console.error('Ошибка:', error);
  }
}
```

## Примечания

- Этот endpoint используется для проверки действительности токена и получения актуальной информации о пользователе
- Информация об аватаре (`avatar`) будет `null`, если пользователь ещё не загрузил аватар
- Поле `organization` присутствует только для пользователей, принадлежащих организации
- Поле `role` присутствует только для пользователей с назначенной ролью

