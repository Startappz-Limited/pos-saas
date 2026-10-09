# Authorization Helper

This document provides a helper class for managing user permissions in Flutter applications. The permission system uses string-based permissions returned from the authentication API.

## Overview

After login, the API returns user data including:
- `roles`: Array of role names (e.g., `["manager", "cashier"]`)
- `permissions`: Array of permission strings (e.g., `["sales.create", "sales.view"]`)

Store these in your auth state and use the helper below to check permissions throughout your app.

## Permission Helper Class

```dart
/// Authorization helper for permission-based access control.
/// 
/// Usage:
/// ```dart
/// final authHelper = AuthorizationHelper(user.permissions);
/// if (authHelper.can('sales.create')) {
///   // Show create sale button
/// }
/// ```
class AuthorizationHelper {
  final Set<String> _permissions;

  AuthorizationHelper(List<String> permissions)
      : _permissions = permissions.toSet();

  /// Check if user has a specific permission.
  bool can(String permission) {
    // Check for full-access permission first
    final module = permission.split('.').first;
    if (_permissions.contains('$module.full-access')) {
      return true;
    }
    return _permissions.contains(permission);
  }

  /// Check if user has ANY of the given permissions.
  bool canAny(List<String> permissions) {
    return permissions.any((p) => can(p));
  }

  /// Check if user has ALL of the given permissions.
  bool canAll(List<String> permissions) {
    return permissions.every((p) => can(p));
  }

  /// Check if user has view permission for a module.
  bool canView(String module) => can('$module.view');

  /// Check if user has create permission for a module.
  bool canCreate(String module) => can('$module.create');

  /// Check if user has update permission for a module.
  bool canUpdate(String module) => can('$module.update');

  /// Check if user has delete permission for a module.
  bool canDelete(String module) => can('$module.delete');

  /// Check if user has full access to a module.
  bool hasFullAccess(String module) => _permissions.contains('$module.full-access');

  /// Check if user can view all items across shops (cross-shop access).
  bool canViewAll(String module) =>
      can('$module.view-all') || hasFullAccess(module);

  /// Check if user can edit all items across shops (cross-shop access).
  bool canEditAll(String module) =>
      can('$module.edit-all') || hasFullAccess(module);

  /// Check if user can approve in a module.
  bool canApprove(String module) => can('$module.approve');
}
```

## Integration with Provider/State Management

### Riverpod Example

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';

// User state provider
final userProvider = StateProvider<User?>((ref) => null);

// Authorization helper provider
final authHelperProvider = Provider<AuthorizationHelper?>((ref) {
  final user = ref.watch(userProvider);
  if (user == null) return null;
  return AuthorizationHelper(user.permissions);
});

// Usage in widgets
class SalesScreen extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authHelperProvider);
    
    return Scaffold(
      floatingActionButton: auth?.can('sales.create') == true
          ? FloatingActionButton(
              onPressed: () => _createSale(context),
              child: Icon(Icons.add),
            )
          : null,
    );
  }
}
```

### GetX Example

```dart
import 'package:get/get.dart';

class AuthController extends GetxController {
  final Rxn<User> user = Rxn<User>();
  
  AuthorizationHelper? get auth {
    if (user.value == null) return null;
    return AuthorizationHelper(user.value!.permissions);
  }
  
  bool can(String permission) => auth?.can(permission) ?? false;
  bool canAny(List<String> permissions) => auth?.canAny(permissions) ?? false;
}

// Usage
final authController = Get.find<AuthController>();
if (authController.can('sales.create')) {
  // Show create button
}
```

## UI Gating Widgets

### PermissionGate Widget

```dart
/// Widget that only renders its child if the user has the required permission.
class PermissionGate extends ConsumerWidget {
  final String permission;
  final Widget child;
  final Widget? fallback;

  const PermissionGate({
    required this.permission,
    required this.child,
    this.fallback,
    super.key,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authHelperProvider);
    
    if (auth?.can(permission) == true) {
      return child;
    }
    return fallback ?? const SizedBox.shrink();
  }
}

// Usage
PermissionGate(
  permission: 'sales.create',
  child: ElevatedButton(
    onPressed: _createSale,
    child: Text('Create Sale'),
  ),
)
```

### MultiPermissionGate Widget

```dart
/// Widget that renders based on multiple permissions.
class MultiPermissionGate extends ConsumerWidget {
  final List<String> permissions;
  final bool requireAll;
  final Widget child;
  final Widget? fallback;

  const MultiPermissionGate({
    required this.permissions,
    required this.child,
    this.requireAll = false,
    this.fallback,
    super.key,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authHelperProvider);
    
    final hasAccess = requireAll
        ? auth?.canAll(permissions) == true
        : auth?.canAny(permissions) == true;
    
    if (hasAccess) {
      return child;
    }
    return fallback ?? const SizedBox.shrink();
  }
}

// Show if user can view OR create sales
MultiPermissionGate(
  permissions: ['sales.view', 'sales.create'],
  child: SalesSection(),
)

// Show only if user has BOTH permissions
MultiPermissionGate(
  permissions: ['sales.view', 'sales.refund'],
  requireAll: true,
  child: RefundSection(),
)
```

## Navigation Guard

```dart
/// Protect routes based on permissions.
class PermissionGuard extends ConsumerWidget {
  final String permission;
  final Widget child;
  final String redirectRoute;

  const PermissionGuard({
    required this.permission,
    required this.child,
    this.redirectRoute = '/unauthorized',
    super.key,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authHelperProvider);
    
    if (auth?.can(permission) != true) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        Navigator.of(context).pushReplacementNamed(redirectRoute);
      });
      return const Center(child: CircularProgressIndicator());
    }
    
    return child;
  }
}

// Usage in routes
GoRoute(
  path: '/sales/create',
  builder: (context, state) => PermissionGuard(
    permission: 'sales.create',
    child: CreateSaleScreen(),
  ),
)
```

## Available Permissions

### Users Management
- `users.view` - View user list
- `users.create` - Create new users
- `users.update` - Edit user details
- `users.delete` - Delete users
- `users.activate` - Activate users
- `users.deactivate` - Deactivate users
- `users.suspend` - Suspend users
- `users.full-access` - Full access to users module

### Roles & Permissions
- `roles.view` - View roles
- `roles.create` - Create roles
- `roles.update` - Update roles
- `roles.delete` - Delete roles
- `roles.assign-permissions` - Assign permissions to roles
- `roles.full-access` - Full access to roles
- `permissions.view` - View permissions list

### Shops Management
- `shops.view` - View shops
- `shops.create` - Create shops
- `shops.update` - Update shops
- `shops.delete` - Delete shops
- `shops.activate` - Activate shops
- `shops.deactivate` - Deactivate shops
- `shops.full-access` - Full access to shops

### Products
- `products.view` - View products
- `products.create` - Create products
- `products.update` - Update products
- `products.delete` - Delete products
- `products.import` - Import products
- `products.export` - Export products
- `products.full-access` - Full access to products

### Sales
- `sales.view` - View own shop sales
- `sales.view-all` - View all shops' sales
- `sales.create` - Create sales
- `sales.update` - Update own shop sales
- `sales.edit-all` - Edit all shops' sales
- `sales.delete` - Delete own shop sales
- `sales.delete-all` - Delete all shops' sales
- `sales.void` - Void sales
- `sales.refund` - Process refunds
- `sales.restore` - Restore voided sales
- `sales.full-access` - Full access to sales

### Refunds
- `refunds.view` - View own shop refunds
- `refunds.view-all` - View all shops' refunds
- `refunds.create` - Create refunds
- `refunds.approve` - Approve refunds
- `refunds.full-access` - Full access to refunds

### Returns
- `returns.view` - View own shop returns
- `returns.view-all` - View all shops' returns
- `returns.create` - Create returns
- `returns.update` - Update returns
- `returns.delete` - Delete returns
- `returns.approve` - Approve returns
- `returns.full-access` - Full access to returns

### Ecommerce Orders
- `ecommerce-orders.view` - View own shop orders
- `ecommerce-orders.view-all` - View all shops' orders
- `ecommerce-orders.convert` - Convert to sales
- `ecommerce-orders.update` - Update own shop orders
- `ecommerce-orders.edit-all` - Edit all shops' orders
- `ecommerce-orders.full-access` - Full access to orders

### Inventory
- `inventory.view` - View inventory
- `inventory.adjust` - Adjust stock levels
- `inventory.transfer` - Transfer stock between shops
- `inventory.count` - Perform stock counts
- `inventory.full-access` - Full access to inventory

### Stock Adjustments
- `stock-adjustments.view` - View adjustments
- `stock-adjustments.create` - Create adjustments
- `stock-adjustments.update` - Update adjustments
- `stock-adjustments.delete` - Delete adjustments
- `stock-adjustments.approve` - Approve adjustments
- `stock-adjustments.full-access` - Full access to adjustments

### Expenses
- `expenses.view` - View expenses
- `expenses.create` - Create expenses
- `expenses.update` - Update expenses
- `expenses.delete` - Delete expenses
- `expenses.approve` - Approve expenses
- `expenses.full-access` - Full access to expenses

### Reports & Dashboard
- `reports.view` - View reports
- `reports.sales` - Access sales reports
- `reports.inventory` - Access inventory reports
- `reports.expenses` - Access expense reports
- `reports.profit-loss` - Access P&L reports
- `reports.export` - Export reports
- `reports.full-access` - Full access to reports
- `dashboard.view` - View dashboard
- `dashboard.analytics` - View dashboard analytics/statistics
- `dashboard.full-access` - Full access to dashboard

### Customers
- `customers.view` - View customers
- `customers.create` - Create customers
- `customers.update` - Update customers
- `customers.delete` - Delete customers
- `customers.manage-credit` - Manage customer credit
- `customers.full-access` - Full access to customers

## Best Practices

1. **Always check permissions before showing UI elements** - Don't show buttons/links users can't use.

2. **Use full-access pattern** - The helper automatically checks `module.full-access` first.

3. **Handle null auth state** - User might not be logged in:
   ```dart
   if (auth?.can('sales.create') == true) { ... }
   ```

4. **Cache the helper** - Create once per user session, not on every build.

5. **Server-side validation** - Always validate permissions on the API side too. Client-side checks are for UX only.

## Implementation Notes

- Permissions are fetched during login and stored with user data
- Refresh permissions when user data is refreshed
- The `full-access` permission grants all permissions within that module
- Cross-shop permissions (`view-all`, `edit-all`, `delete-all`) allow access across shop boundaries
