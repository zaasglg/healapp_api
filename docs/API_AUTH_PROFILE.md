# API: Обновить профиль

## Endpoint

```http
PATCH /api/v1/auth/profile
```

## Описание

Обновляет основную информацию профиля текущего аутентифицированного пользователя (имя, фамилия, отчество).

## Аутентификация

✅ **Требуется**: Bearer Token (Laravel Sanctum)

## Заголовки запроса

```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

## Тело запроса

### Параметры

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `first_name` | string | ❌ | Имя пользователя |
| `last_name` | string | ❌ | Фамилия пользователя |
| `middle_name` | string | ❌ | Отчество пользователя |

**Примечание**: Все поля опциональны. Обновляются только переданные поля.

### Пример запроса

```json
{
  "first_name": "Иван",
  "last_name": "Иванов",
  "middle_name": "Петрович"
}
```

## Пример запроса

### cURL

```bash
curl -X PATCH https://api.healapp.kz/api/v1/auth/profile \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "first_name": "Иван",
    "last_name": "Иванов",
    "middle_name": "Петрович"
  }'
```

### JavaScript (Fetch)

```javascript
const response = await fetch('https://api.healapp.kz/api/v1/auth/profile', {
  method: 'PATCH',
  headers: {
    'Authorization': `Bearer ${accessToken}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    first_name: 'Иван',
    last_name: 'Иванов',
    middle_name: 'Петрович'
  })
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
  "updated_at": "2024-12-23T09:15:00.000000Z",
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
| `type` | string | Тип пользователя |
| `account_type` | string | Тип аккаунта |
| `role` | string\|null | Роль в организации |
| `phone_verified_at` | datetime\|null | Дата и время подтверждения телефона |
| `created_at` | datetime | Дата создания аккаунта |
| `updated_at` | datetime | Дата последнего обновления |
| `organization` | object\|null | Информация об организации |

## Ошибки

### 401 Unauthorized

Токен отсутствует, недействителен или истёк.

```json
{
  "message": "Unauthenticated."
}
```

### 422 Unprocessable Entity

Ошибка валидации данных.

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "first_name": [
      "Поле имя должно быть строкой."
    ]
  }
}
```

## Примеры использования

### Обновление профиля

```javascript
async function updateProfile(profileData) {
  try {
    const response = await fetch('https://api.healapp.kz/api/v1/auth/profile', {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        first_name: profileData.firstName,
        last_name: profileData.lastName,
        middle_name: profileData.middleName
      })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Ошибка обновления профиля');
    }

    const updatedUser = await response.json();
    console.log('Профиль обновлён:', updatedUser);
    
    // Обновляем информацию о пользователе в локальном хранилище
    localStorage.setItem('user', JSON.stringify(updatedUser));
    
    return updatedUser;
  } catch (error) {
    console.error('Ошибка обновления профиля:', error);
    throw error;
  }
}

// Использование
updateProfile({
  firstName: 'Иван',
  lastName: 'Иванов',
  middleName: 'Петрович'
})
  .then(user => {
    console.log('Профиль успешно обновлён!', user);
  })
  .catch(error => {
    console.error('Ошибка:', error);
  });
```

### Частичное обновление (только имя)

```javascript
async function updateFirstName(newFirstName) {
  try {
    const response = await fetch('https://api.healapp.kz/api/v1/auth/profile', {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        first_name: newFirstName
      })
    });

    if (!response.ok) {
      throw new Error('Ошибка обновления имени');
    }

    const updatedUser = await response.json();
    return updatedUser;
  } catch (error) {
    console.error('Ошибка:', error);
    throw error;
  }
}
```

### React Hook пример

```javascript
import { useState } from 'react';

function ProfileForm() {
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('https://api.healapp.kz/api/v1/auth/profile', {
        method: 'PATCH',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          first_name: firstName,
          last_name: lastName,
          middle_name: middleName
        })
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Ошибка обновления профиля');
      }

      const updatedUser = await response.json();
      console.log('Профиль обновлён:', updatedUser);
      // Обновить состояние приложения
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="text"
        value={firstName}
        onChange={(e) => setFirstName(e.target.value)}
        placeholder="Имя"
      />
      <input
        type="text"
        value={lastName}
        onChange={(e) => setLastName(e.target.value)}
        placeholder="Фамилия"
      />
      <input
        type="text"
        value={middleName}
        onChange={(e) => setMiddleName(e.target.value)}
        placeholder="Отчество"
      />
      {error && <div className="error">{error}</div>}
      <button type="submit" disabled={loading}>
        {loading ? 'Сохранение...' : 'Сохранить'}
      </button>
    </form>
  );
}
```

## Примечания

- Все поля опциональны — можно обновить только одно или несколько полей
- Поле `updated_at` автоматически обновляется при сохранении
- Информация об аватаре (`avatar`) не изменяется через этот endpoint — используйте `/api/v1/auth/avatar` для загрузки аватара
- Номер телефона нельзя изменить через этот endpoint — используйте `/api/v1/auth/change-phone/request` и `/api/v1/auth/change-phone/confirm`

