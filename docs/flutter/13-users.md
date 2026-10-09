# 13. Users

Manage system users including creation, role assignment, and status management.

---

## List Users

`GET /api/users`

Returns paginated list of users with their roles.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | Filter by status: `active`, `inactive`, `suspended` |
| `role` | string | Filter by role name |
| `search` | string | Search in name, email, phone |
| `per_page` | int | Items per page (default: 15) |
| `page` | int | Page number |

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "uuid": "a1b2c3d4-...",
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+254712345678",
        "status": "active",
        "shop_id": 1,
        "email_verified_at": "2026-03-15T10:00:00.000000Z",
        "created_at": "2026-03-01T08:00:00.000000Z",
        "roles": [
          {
            "id": 2,
            "name": "manager"
          }
        ]
      }
    ],
    "last_page": 3,
    "per_page": 15,
    "total": 45
  }
}
```

---

## Get User

`GET /api/users/{user}`

Get a single user's details with roles and shop.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "a1b2c3d4-...",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+254712345678",
    "status": "active",
    "shop_id": 1,
    "email_verified_at": "2026-03-15T10:00:00.000000Z",
    "created_at": "2026-03-01T08:00:00.000000Z",
    "roles": [
      {
        "id": 2,
        "name": "manager"
      }
    ],
    "shop": {
      "id": 1,
      "name": "Main Store",
      "uuid": "shop-uuid-..."
    }
  }
}
```

---

## Create User

`POST /api/users`

Create a new user account.

### Request Body

```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+254712345678",
  "password": "SecurePassword123!",
  "shop_id": 1,
  "status": "active",
  "roles": ["cashier", "inventory-clerk"]
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `name` | required, max 255 chars |
| `email` | required, valid email, unique |
| `phone` | optional, max 20 chars |
| `password` | required, meets password policy |
| `shop_id` | optional, must exist if provided |
| `status` | optional: `active`, `inactive`, `suspended` |
| `roles` | optional, array of existing role names |

### Success Response `201`

```json
{
  "success": true,
  "message": "User created successfully.",
  "data": {
    "id": 5,
    "uuid": "new-uuid-...",
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+254712345678",
    "status": "active",
    "shop_id": 1,
    "roles": [
      {
        "id": 3,
        "name": "cashier"
      },
      {
        "id": 4,
        "name": "inventory-clerk"
      }
    ]
  }
}
```

---

## Update User

`PUT /api/users/{user}`

Update an existing user's details.

### Request Body

```json
{
  "name": "Jane Smith Updated",
  "email": "jane.updated@example.com",
  "phone": "+254712345679",
  "password": "NewPassword123!",
  "shop_id": 2,
  "status": "active",
  "roles": ["manager"]
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `name` | optional, max 255 chars |
| `email` | optional, valid email, unique (except self) |
| `phone` | optional, max 20 chars |
| `password` | optional, meets password policy |
| `shop_id` | optional, must exist if provided |
| `status` | optional: `active`, `inactive`, `suspended` |
| `roles` | optional, array of existing role names |

### Success Response `200`

```json
{
  "success": true,
  "message": "User updated successfully.",
  "data": {
    "id": 5,
    "uuid": "user-uuid-...",
    "name": "Jane Smith Updated",
    "email": "jane.updated@example.com",
    "roles": [
      {
        "id": 2,
        "name": "manager"
      }
    ]
  }
}
```

---

## Delete User

`DELETE /api/users/{user}`

Delete a user account. Users cannot delete themselves.

### Success Response `200`

```json
{
  "success": true,
  "message": "User deleted successfully."
}
```

### Error Response `422` (Self-deletion)

```json
{
  "success": false,
  "message": "You cannot delete your own account."
}
```

---

## Get Available Roles

`GET /api/users/roles`

Get list of all available roles for user assignment.

### Success Response `200`

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "super-admin" },
    { "id": 2, "name": "manager" },
    { "id": 3, "name": "cashier" },
    { "id": 4, "name": "inventory-clerk" }
  ]
}
```

---

## Get Available Shops

`GET /api/users/shops`

Get list of shops for user assignment.

### Success Response `200`

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Main Store", "uuid": "shop-uuid-1" },
    { "id": 2, "name": "Branch Office", "uuid": "shop-uuid-2" }
  ]
}
```

---

## Get User Statuses

`GET /api/users/statuses`

Get available user status options.

### Success Response `200`

```json
{
  "success": true,
  "data": {
    "active": "Active",
    "inactive": "Inactive",
    "suspended": "Suspended"
  }
}
```

---

## Flutter Model

```dart
class User {
  final int id;
  final String uuid;
  final String name;
  final String email;
  final String? phone;
  final String status;
  final int? shopId;
  final DateTime? emailVerifiedAt;
  final DateTime createdAt;
  final List<Role> roles;
  final Shop? shop;

  User({
    required this.id,
    required this.uuid,
    required this.name,
    required this.email,
    this.phone,
    required this.status,
    this.shopId,
    this.emailVerifiedAt,
    required this.createdAt,
    required this.roles,
    this.shop,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'],
      uuid: json['uuid'],
      name: json['name'],
      email: json['email'],
      phone: json['phone'],
      status: json['status'] ?? 'active',
      shopId: json['shop_id'],
      emailVerifiedAt: json['email_verified_at'] != null
          ? DateTime.parse(json['email_verified_at'])
          : null,
      createdAt: DateTime.parse(json['created_at']),
      roles: (json['roles'] as List<dynamic>?)
              ?.map((r) => Role.fromJson(r))
              .toList() ??
          [],
      shop: json['shop'] != null ? Shop.fromJson(json['shop']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'email': email,
      'phone': phone,
      'status': status,
      'shop_id': shopId,
      'roles': roles.map((r) => r.name).toList(),
    };
  }

  bool get isActive => status == 'active';
  bool get isSuspended => status == 'suspended';

  bool hasRole(String roleName) {
    return roles.any((r) => r.name == roleName);
  }

  bool hasAnyRole(List<String> roleNames) {
    return roles.any((r) => roleNames.contains(r.name));
  }
}

class Role {
  final int id;
  final String name;

  Role({required this.id, required this.name});

  factory Role.fromJson(Map<String, dynamic> json) {
    return Role(
      id: json['id'],
      name: json['name'],
    );
  }
}
```

---

## Flutter Service

```dart
class UserService {
  final ApiClient _api;

  UserService(this._api);

  Future<PaginatedResponse<User>> getUsers({
    int page = 1,
    int perPage = 15,
    String? status,
    String? role,
    String? search,
  }) async {
    final params = {
      'page': page.toString(),
      'per_page': perPage.toString(),
      if (status != null) 'status': status,
      if (role != null) 'role': role,
      if (search != null) 'search': search,
    };

    final response = await _api.get('/users', queryParameters: params);
    return PaginatedResponse.fromJson(
      response.data['data'],
      (json) => User.fromJson(json),
    );
  }

  Future<User> getUser(int id) async {
    final response = await _api.get('/users/$id');
    return User.fromJson(response.data['data']);
  }

  Future<User> createUser({
    required String name,
    required String email,
    required String password,
    String? phone,
    int? shopId,
    String status = 'active',
    List<String> roles = const [],
  }) async {
    final response = await _api.post('/users', data: {
      'name': name,
      'email': email,
      'password': password,
      'phone': phone,
      'shop_id': shopId,
      'status': status,
      'roles': roles,
    });
    return User.fromJson(response.data['data']);
  }

  Future<User> updateUser(
    int id, {
    String? name,
    String? email,
    String? password,
    String? phone,
    int? shopId,
    String? status,
    List<String>? roles,
  }) async {
    final data = <String, dynamic>{};
    if (name != null) data['name'] = name;
    if (email != null) data['email'] = email;
    if (password != null) data['password'] = password;
    if (phone != null) data['phone'] = phone;
    if (shopId != null) data['shop_id'] = shopId;
    if (status != null) data['status'] = status;
    if (roles != null) data['roles'] = roles;

    final response = await _api.put('/users/$id', data: data);
    return User.fromJson(response.data['data']);
  }

  Future<void> deleteUser(int id) async {
    await _api.delete('/users/$id');
  }

  Future<List<Role>> getRoles() async {
    final response = await _api.get('/users/roles');
    return (response.data['data'] as List)
        .map((r) => Role.fromJson(r))
        .toList();
  }

  Future<List<Shop>> getShops() async {
    final response = await _api.get('/users/shops');
    return (response.data['data'] as List)
        .map((s) => Shop.fromJson(s))
        .toList();
  }

  Future<Map<String, String>> getStatuses() async {
    final response = await _api.get('/users/statuses');
    return Map<String, String>.from(response.data['data']);
  }
}
```

---

## User Status Enum

```dart
enum UserStatus {
  active('active', 'Active'),
  inactive('inactive', 'Inactive'),
  suspended('suspended', 'Suspended');

  final String value;
  final String label;

  const UserStatus(this.value, this.label);

  static UserStatus fromString(String value) {
    return UserStatus.values.firstWhere(
      (s) => s.value == value,
      orElse: () => UserStatus.active,
    );
  }

  Color get color {
    switch (this) {
      case UserStatus.active:
        return Colors.green;
      case UserStatus.inactive:
        return Colors.grey;
      case UserStatus.suspended:
        return Colors.red;
    }
  }

  IconData get icon {
    switch (this) {
      case UserStatus.active:
        return Icons.check_circle;
      case UserStatus.inactive:
        return Icons.remove_circle;
      case UserStatus.suspended:
        return Icons.cancel;
    }
  }
}
```

---

## Required Permissions

| Action | Permission |
|--------|------------|
| List users | `users.view` |
| View user details | `users.view` |
| Create user | `users.create` |
| Update user | `users.update` |
| Delete user | `users.delete` |
| Full access | `users.full-access` |
