---
title: Security Audit
description: Comprehensive security review checking authorization, injection vulnerabilities, and Laravel security best practices
---

Perform a security audit on:

{{target|prompt:Specify file, feature, or area to audit}}

## Security Checklist

### 1. Authentication & Authorization

**Policy Checks:**

```php
// ✅ Always check authorization
$this->authorize('update', $product);

// ✅ Super-admin bypass in policies
if ($user->hasRole('super-admin')) {
    return true;
}
```

**Route Protection:**

```php
// ✅ Protected routes
Route::middleware(['auth'])->group(function () {
    // Routes
});

// ❌ Exposed admin routes
Route::get('/admin/dashboard', ...); // Missing auth
```

### 2. Multi-Tenancy Isolation

**Shop Scoping:**

```php
// ❌ Missing shop scope
$products = Product::all();

// ✅ Shop-scoped query
$products = Product::where('shop_id', auth()->user()->shop_id)->get();

// ✅ Global scope (preferred)
protected static function booted()
{
    if (!auth()->user()?->hasRole('super-admin')) {
        static::addGlobalScope('shop', function ($query) {
            $query->where('shop_id', auth()->user()->shop_id);
        });
    }
}
```

### 3. SQL Injection Prevention

**Use Query Builder:**

```php
// ❌ Raw SQL with user input
DB::select("SELECT * FROM users WHERE name = '$name'");

// ✅ Parameterized query
DB::select('SELECT * FROM users WHERE name = ?', [$name]);

// ✅ Query builder (preferred)
User::where('name', $name)->get();
```

### 4. Mass Assignment Protection

**Define Fillable:**

```php
// ❌ Unprotected
protected $guarded = [];

// ✅ Protected
protected $fillable = ['name', 'email', 'price'];

// ✅ Or explicitly guarded
protected $guarded = ['id', 'shop_id', 'is_admin'];
```

### 5. XSS Prevention

**Blade Escaping:**

```blade
{{-- ✅ Auto-escaped --}}
{{ $user->name }}

{{-- ❌ Unescaped (dangerous) --}}
{!! $user->bio !!}

{{-- ✅ Safe HTML rendering --}}
{!! Str::markdown($user->bio) !!}
```

### 6. CSRF Protection

**Forms:**

```blade
<form method="POST">
    @csrf {{-- Required! --}}
    <!-- form fields -->
</form>
```

**AJAX:**

```javascript
axios.defaults.headers.common["X-CSRF-TOKEN"] = document.querySelector(
    'meta[name="csrf-token"]',
).content;
```

### 7. File Upload Security

```php
// ✅ Validate file uploads
$request->validate([
    'avatar' => 'required|image|max:2048|mimes:jpg,png',
    'document' => 'required|file|max:10240|mimes:pdf,docx',
]);

// ✅ Store safely
$path = $request->file('avatar')->store('avatars', 'public');

// ❌ Never trust file extensions
// ❌ Never execute uploaded files
```

### 8. Sensitive Data

**Environment Variables:**

```php
// ✅ Use config
config('services.stripe.key')

// ❌ Direct env access in code
env('STRIPE_KEY')
```

**Hide Attributes:**

```php
protected $hidden = ['password', 'remember_token', 'api_token'];
```

### 9. Rate Limiting

```php
// Apply to sensitive routes
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/login', ...);
});
```

### 10. Input Validation

```php
// ✅ Always validate user input
$validated = $request->validate([
    'email' => 'required|email|max:255',
    'amount' => 'required|numeric|min:0|max:999999.99',
    'status' => 'required|in:active,inactive',
]);
```

## Output Format

**🔴 Critical Vulnerabilities**

- SQL injection risks
- Missing authorization checks
- XSS vulnerabilities
- Multi-tenancy leaks

**🟡 Security Concerns**

- Weak validation
- Missing CSRF protection
- Insecure file handling
- Missing rate limiting

**✅ Security Features Found**

- Proper authorization
- Input validation
- Secure practices

**📋 Recommendations**

- Specific fixes with code examples
- Additional security measures
- Testing recommendations
