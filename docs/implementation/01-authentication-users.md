# Module 01: Authentication & Users
## Stock Taking & Sales Management System

### Module Overview
Set up Laravel's built-in authentication system with user registration, login, logout, and password reset functionality. This is the foundation for all other modules.

**Priority:** P0 (Critical)  
**Dependencies:** None  
**Estimated Time:** 1-2 days

---

## 1. Database Schema

### Users Table (Existing - Extend)

```sql
-- Modify existing users table migration
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable()->after('email');
    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('phone');
    $table->string('avatar')->nullable()->after('status');
    $table->timestamp('last_login_at')->nullable();
    $table->softDeletes();
});
```

### Fields Specification

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint | PK, auto | Primary key |
| name | string(255) | required | Full name |
| email | string(255) | required, unique | Login email |
| email_verified_at | timestamp | nullable | Email verification |
| password | string(255) | required | Hashed password |
| phone | string(20) | nullable | Contact phone |
| status | enum | default: active | Account status |
| avatar | string(255) | nullable | Profile picture path |
| last_login_at | timestamp | nullable | Last login tracking |
| remember_token | string(100) | nullable | Remember me token |
| created_at | timestamp | auto | Record creation |
| updated_at | timestamp | auto | Record update |
| deleted_at | timestamp | nullable | Soft delete |

---

## 2. Models & Relationships

### User Model Updates

**File:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
        'avatar',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships (to be added in later modules)
    // public function sales(): HasMany
    // public function stockIntakes(): HasMany
    // public function shop(): BelongsTo

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get avatar URL or default
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : asset('assets/images/users/default-avatar.png');
    }
}
```

---

## 3. Controllers & Routes

### Auth Controllers (Using Laravel Breeze Pattern)

Create these controllers:

| Controller | Purpose |
|------------|---------|
| `LoginController` | Handle login/logout |
| `RegisterController` | Handle registration |
| `PasswordResetController` | Handle password reset |
| `ProfileController` | Handle profile management |

### Routes

**File:** `routes/web.php`

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);
    
    Route::get('forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Dashboard (placeholder for now)
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');
});
```

---

## 4. Form Requests (Validation)

### LoginRequest

**File:** `app/Http/Requests/Auth/LoginRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }
}
```

### RegisterRequest

**File:** `app/Http/Requests/Auth/RegisterRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Please create a password.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
```

### UpdateProfileRequest

**File:** `app/Http/Requests/Auth/UpdateProfileRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }
}
```

---

## 5. Views (UI Components)

### Larkon Template Reference

| View | Template Source |
|------|-----------------|
| Login | `design/src/auth-signin.php` |
| Register | `design/src/auth-signup.php` |
| Forgot Password | `design/src/auth-password.php` |
| Lock Screen | `design/src/auth-lock-screen.php` |
| Profile | `design/src/pages-profile.php` |

### View Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php          # Main authenticated layout
│   └── guest.blade.php        # Auth pages layout
├── auth/
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── forgot-password.blade.php
│   └── reset-password.blade.php
├── profile/
│   └── edit.blade.php
├── components/
│   ├── input-label.blade.php
│   ├── text-input.blade.php
│   ├── input-error.blade.php
│   └── button.blade.php
└── dashboard.blade.php
```

---

## 6. Tests (Pest)

### Feature Tests

**File:** `tests/Feature/Auth/AuthenticationTest.php`

```php
<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');
    
    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();
    
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();
    
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/logout');
    
    $this->assertGuest();
});

test('inactive users cannot login', function () {
    $user = User::factory()->create(['status' => 'inactive']);
    
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    
    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
```

**File:** `tests/Feature/Auth/RegistrationTest.php`

```php
<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');
    
    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('registration requires valid email', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'invalid-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    
    $response->assertSessionHasErrors('email');
});

test('registration requires password confirmation', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ]);
    
    $response->assertSessionHasErrors('password');
});
```

**File:** `tests/Feature/Auth/ProfileTest.php`

```php
<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get('/profile');
    
    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
    
    $response->assertSessionHasNoErrors()
        ->assertRedirect('/profile');
    
    $user->refresh();
    
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
});

test('email verification status is unchanged when email is unchanged', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated Name',
        'email' => $user->email,
    ]);
    
    $response->assertSessionHasNoErrors();
    
    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
```

---

## 7. Commands to Execute

Execute these commands in order:

```bash
# Step 1: Create migration to extend users table
php artisan make:migration add_fields_to_users_table --table=users --no-interaction

# Step 2: Create Auth Controllers
php artisan make:controller Auth/LoginController --no-interaction
php artisan make:controller Auth/RegisterController --no-interaction
php artisan make:controller Auth/PasswordResetController --no-interaction
php artisan make:controller ProfileController --no-interaction

# Step 3: Create Form Requests
php artisan make:request Auth/LoginRequest --no-interaction
php artisan make:request Auth/RegisterRequest --no-interaction
php artisan make:request Auth/UpdateProfileRequest --no-interaction

# Step 4: Run migration
php artisan migrate

# Step 5: Create tests
php artisan make:test Auth/AuthenticationTest --pest --no-interaction
php artisan make:test Auth/RegistrationTest --pest --no-interaction
php artisan make:test Auth/ProfileTest --pest --no-interaction

# Step 6: Run tests
php artisan test --compact --filter=Auth

# Step 7: Format code
vendor/bin/pint --dirty
```

---

## 8. Verification Checklist

Before proceeding to Module 02, verify:

- [ ] Users table migration runs without errors
- [ ] User model has all required fields and casts
- [ ] Login page renders correctly (matches `auth-signin.php`)
- [ ] Register page renders correctly (matches `auth-signup.php`)
- [ ] Users can register with valid data
- [ ] Users can login with valid credentials
- [ ] Invalid login attempts are rejected
- [ ] Users can logout
- [ ] Inactive users cannot login
- [ ] Profile page is accessible when authenticated
- [ ] Profile information can be updated
- [ ] All tests pass (`php artisan test --compact --filter=Auth`)
- [ ] Code formatted with Pint

---

## 9. Next Steps

Once this module is complete and verified, proceed to:
→ **[Module 02: Roles & Permissions](./02-roles-permissions.md)**
