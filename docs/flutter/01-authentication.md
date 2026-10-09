# 1. Authentication

The Flutter app authenticates using **Laravel Sanctum** token-based auth. Users must provide their platform's base URL, email, and password.

---

## Login

`POST /api/login`

Authenticates the user and returns a Sanctum bearer token.

### Request Body

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `email` | required, email |
| `password` | required, string |

### Success Response `200`

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Doe",
      "email": "user@example.com",
      "phone": "+1234567890",
      "status": "active",
      "shop_id": 1,
      "roles": ["cashier"],
      "permissions": ["sales.create", "sales.view", "cash_registers.open", "cash_registers.close"]
    }
  }
}
```

### Error Response `401`

```json
{
  "success": false,
  "message": "Invalid credentials."
}
```

### Flutter Implementation Notes

- Store the `token` securely (e.g., `flutter_secure_storage`).
- Store the `base_url` provided by the user.
- Prepend all API calls with `{base_url}/api/`.
- Include `Authorization: Bearer {token}` in all subsequent requests.
- Check `user.status` — only `active` users should proceed.
- Use `user.roles` and `user.permissions` for UI feature gating.

---

## Get Current User

`GET /api/user`

Returns the currently authenticated user's profile.

### Success Response `200`

```json
{
  "id": 1,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "name": "John Doe",
  "email": "user@example.com",
  "phone": "+1234567890",
  "status": "active",
  "shop_id": 1,
  "roles": ["cashier"],
  "permissions": ["sales.create", "sales.view"]
}
```

---

## Logout

`POST /api/logout`

Revokes the current API token.

### Success Response `200`

```json
{
  "success": true,
  "message": "Logged out successfully."
}
```

### Flutter Implementation Notes

- Delete stored token and base URL on logout.
- Redirect to the login/setup screen.

---

## Token Validation

On app startup, call `GET /api/user` to verify the stored token is still valid. If a `401` is returned, redirect to login.
