# API: Подтверждение телефона

## Endpoint

```http
POST /api/v1/auth/verify-phone
```

## Описание

Подтверждает номер телефона пользователя с помощью кода верификации, полученного при регистрации. После успешной верификации создаётся токен доступа и пользователь может войти в систему.

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
| `code` | string | ✅ | Код верификации (4 цифры) |

### Пример запроса

```json
{
  "phone": "79001234567",
  "code": "1234"
}
```

## Пример запроса

### cURL

```bash
curl -X POST https://api.healapp.kz/api/v1/auth/verify-phone \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "phone": "79001234567",
    "code": "1234"
  }'
```

### JavaScript (Fetch)

```javascript
const response = await fetch('https://api.healapp.kz/api/v1/auth/verify-phone', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    phone: '79001234567',
    code: '1234'
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
    "avatar": null,
    "phone": "79001234567",
    "type": "client",
    "account_type": "client",
    "phone_verified_at": "2024-12-20T10:30:00.000000Z",
    "created_at": "2024-12-20T10:00:00.000000Z",
    "updated_at": "2024-12-20T10:30:00.000000Z"
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
| `user.avatar` | string\|null | URL аватара пользователя (обычно null при регистрации) |
| `user.phone` | string | Номер телефона |
| `user.type` | string | Тип пользователя |
| `user.account_type` | string | Тип аккаунта |
| `user.phone_verified_at` | datetime | Дата и время подтверждения телефона |
| `user.created_at` | datetime | Дата создания аккаунта |
| `user.updated_at` | datetime | Дата последнего обновления |

## Ошибки

### 401 Unauthorized

Неверный код верификации или пользователь не найден.

```json
{
  "message": "Неверный код"
}
```

## Процесс верификации

```
┌─────────────────────────────────┐
│  POST /api/v1/auth/verify-phone │
│   {"phone": "...", "code": "..."} │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│   Поиск user по phone           │
│   Проверка verification_code    │
└────────────────┬────────────────┘
                 │
            Совпадает?
         ┌───────┴───────┐
         ▼               ▼
       ┌─────┐        ┌──────┐
       │ Нет │        │  Да  │
       └──┬──┘        └───┬──┘
          │               │
          ▼               ▼
┌────────────────┐ ┌─────────────────────────┐
│ Ошибка 401:    │ │ Обновление:             │
│ "Неверный код" │ │ - phone_verified_at=now │
└────────────────┘ │ - verification_code=null│
                   └───────────┬─────────────┘
                               │
                               ▼
                   ┌─────────────────────────┐
                   │ Создание токена Sanctum │
                   └───────────┬─────────────┘
                               │
                               ▼
                   ┌─────────────────────────┐
                   │ Response:               │
                   │ {"access_token": "...", │
                   │  "user": {...}}         │
                   └─────────────────────────┘
```

## Примеры использования

### Подтверждение телефона после регистрации

```javascript
async function verifyPhone(phone, code) {
  try {
    const response = await fetch('https://api.healapp.kz/api/v1/auth/verify-phone', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        phone: phone,
        code: code
      })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Ошибка верификации');
    }

    const data = await response.json();
    
    // Сохраняем токен
    localStorage.setItem('access_token', data.access_token);
    
    // Сохраняем информацию о пользователе
    localStorage.setItem('user', JSON.stringify(data.user));
    
    return data;
  } catch (error) {
    console.error('Ошибка верификации:', error);
    throw error;
  }
}

// Использование
verifyPhone('79001234567', '1234')
  .then(data => {
    console.log('Телефон подтверждён!', data);
    // Пользователь теперь может использовать приложение
  })
  .catch(error => {
    console.error('Ошибка:', error);
    // Показать сообщение об ошибке пользователю
  });
```

### Полный процесс регистрации и верификации

```javascript
async function registerAndVerify() {
  // Шаг 1: Регистрация
  const registerResponse = await fetch('https://api.healapp.kz/api/v1/auth/register', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      phone: '79001234567',
      password: 'secret123',
      password_confirmation: 'secret123',
      account_type: 'client',
      first_name: 'Иван',
      last_name: 'Иванов'
    })
  });

  const registerData = await registerResponse.json();
  console.log('SMS отправлен на:', registerData.phone);

  // Шаг 2: Верификация (после получения кода от пользователя)
  const code = prompt('Введите код из SMS:'); // В реальном приложении использовать форму ввода
  
  const verifyResponse = await fetch('https://api.healapp.kz/api/v1/auth/verify-phone', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      phone: '79001234567',
      code: code
    })
  });

  if (verifyResponse.ok) {
    const verifyData = await verifyResponse.json();
    console.log('Регистрация завершена!', verifyData);
    return verifyData;
  } else {
    const error = await verifyResponse.json();
    throw new Error(error.message);
  }
}
```

## Примечания

- ⚠️ **Важно**: В тестовом окружении (`APP_ENV != production`) код верификации всегда `1234`
- В production окружении код генерируется случайно и отправляется через SMS
- После успешной верификации поле `verification_code` удаляется из базы данных
- Поле `phone_verified_at` устанавливается в текущее время
- Токен доступа создаётся автоматически после успешной верификации
- Информация об аватаре (`avatar`) обычно `null` при первой регистрации, так как пользователь ещё не загрузил аватар

