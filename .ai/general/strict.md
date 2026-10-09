### 🚀 Development Workflow

1. **Start with base layout**: Convert main admin layout first
2. **Create reusable components**: Navigation and common elements
3. **Progress systematically**: Follow phased approach
4. **Test continuously**: Catch issues early in development
5. **Maintain git history**: Clean commits for easy rollback

### 📁 Key File Locations

```
Source (Theme):             Target (Laravel):
/designs/theme/src/         /resources/views/
├── index.php              ├── layouts/admin.blade.php
├── partials/               ├── admin/partials/
│   ├── topbar.php         │   ├── topbar.blade.php
│   ├── startbar.php       │   ├── sidebar.blade.php
│   └── head-css.php       │   └── head.blade.php
└── [pages].php            └── admin/[pages].blade.php
```

## CRITICAL DATABASE WARNING

NEVER EVER run any command that drops tables or wipes local, production data.

DO NOT RUN:

php artisan migrate:fresh

php artisan migrate --fresh

php artisan migrate --force (on production without review)

php artisan migrate --no-\* (e.g. --no-interaction when you don’t fully understand the impact)

php artisan db:seed only run seeder if requested and only run specic class

Running these commands will delete ALL existing tables and data in the database.

✅ Required Safe Practice

ALWAYS take a full database backup before performing migrations, schema changes, or any destructive operation.

Use php artisan migrate only for incremental migrations that have been reviewed and tested.

For major changes, coordinate with the team and run migrations in a controlled staging environment first.

Backups must be verified (restore test on staging) before proceeding.

🔥 If you run migrate:fresh on production, you will destroy live data and cause irreversible damage.

DON'T RUN TESTS IF THE TESTS ARE GOING TO DELETE THE DATABASE OR LATER THE DATA, TRUNCATE A TABLE. ALL TEST THAT REQUIRE THIS KIND OF ACTION SHOULD BE IGNORED AND THE PROCESS TERMINATED BEFORE IT BEGINS.

ALl tests to be unit test