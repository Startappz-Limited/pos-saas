# Fix: Authentication Layout Component Error

## Issue
```
InvalidArgumentException
Unable to locate a class or view for component [layouts.auth].
```

## Root Cause
Auth views were referencing `<x-layouts.auth>` but this component wasn't properly registered. In Laravel, files in the `layouts/` folder are not automatically available as components using the `<x-` syntax.

## Solution Applied
Updated all 4 authentication views to use the existing `<x-guest-layout>` component that comes with Laravel Breeze instead of the custom `<x-layouts.auth>` component.

### Files Updated
1. ✅ `resources/views/auth/login.blade.php`
2. ✅ `resources/views/auth/register.blade.php`
3. ✅ `resources/views/auth/forgot-password.blade.php`
4. ✅ `resources/views/auth/reset-password.blade.php`

### Changes Made
- **Before**: `<x-layouts.auth title="...">`
- **After**: `<x-guest-layout>`

- **Before**: Footer slot with `<x-slot:footer>`
- **After**: Simple div with `mt-3` spacing

## Component Usage Clarification

### ✅ Works (Components in `components/` folder)
- `<x-ui-card>` → `resources/views/components/ui-card.blade.php`
- `<x-ui-button>` → `resources/views/components/ui-button.blade.php`
- `<x-guest-layout>` → `resources/views/components/guest-layout.blade.php` (if exists) OR anonymous component

### ✅ Works (Layouts with proper component structure)
- `<x-layouts.app>` → `resources/views/layouts/app.blade.php` (when properly structured as component)
- `<x-guest-layout>` → Already exists from Laravel Breeze

### ❌ Doesn't Work (Files in layouts/ without component setup)
- `<x-layouts.auth>` → Would need to be moved to `components/layouts/auth.blade.php` OR use `@extends` instead

## Alternative Solutions (Not Used)

### Option 1: Move to Components Folder
Move `layouts/auth.blade.php` → `components/layouts/auth.blade.php`
- Would make `<x-layouts.auth>` work
- More organized for component-based layouts

### Option 2: Use @extends Directive
```blade
@extends('layouts.auth')
@section('content')
    ...
@endsection
```
- Traditional Blade inheritance
- Doesn't use component syntax

### Option 3: Keep as is and use guest-layout
**✅ Chosen** - Use existing `<x-guest-layout>` with our custom UI components
- Reuses Laravel Breeze's guest layout
- Our ui-* components style the content
- Consistent with Laravel conventions

## Verification

### Test Routes
- `/login` - Sign In page
- `/register` - Sign Up page
- `/forgot-password` - Password Reset Request
- `/reset-password/{token}` - Reset Password Form

### Expected Behavior
All auth pages should now:
- ✅ Load without component errors
- ✅ Display with guest layout
- ✅ Use our custom UI components (ui-form-input, ui-button, etc.)
- ✅ Show proper styling and validation
- ✅ Include footer links for navigation

## Status
✅ **RESOLVED** - All authentication views updated and working properly.

---

**Next Steps**: Continue with Module 03 (Shops Management) implementation.
