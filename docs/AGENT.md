# AGENT.md — e-Disaster Laravel Application

## 🏗️ Project Overview

This project is a **Laravel 12 application** for the **e-Disaster** system — a disaster management platform that connects users, volunteers, and officers during natural and social disaster events.

The project uses a **dual-route architecture**:

-   **Web Admin Interface**: Laravel with Blade templates and Livewire for admin dashboard
-   **Mobile API**: RESTful API endpoints for Android mobile application

The database uses **UUIDs as primary keys** across all tables. Relationships follow strict foreign key integrity rules. The backend is structured for **clarity, scalability, and strong typing**.

---

## 🧩 Tech Stack

-   **Language:** PHP 8.4+
-   **Framework:** Laravel 12
-   **Database:** MySQL 8 (InnoDB)
-   **ORM:** Eloquent (UUID-based primary keys)
-   **Frontend:** Blade templates with Livewire 3 + Volt
-   **Testing:** Pest PHP
-   **Auth:** Laravel Fortify (with Two-Factor Authentication)
-   **Tools:** Laravel Sail / Artisan / Tinker / PHPUnit
-   **Migrations:** All primary keys use UUID instead of auto-increment integers
-   **Project Starter:** Laravel Livewire Starter Kit with built-in authentication

---

## ⚙️ Development Guidelines

### 1. Coding Style

-   Follow **PSR-12** and **Laravel best practices**.
-   Use **snake_case** for database columns and **camelCase** for model attributes.
-   Every migration must:
    -   Use `uuid('id')->primary()` for primary keys.
    -   Use `foreignUuid('foreign_key')` for foreign keys.
    -   Add `use Illuminate\Database\Eloquent\Concerns\HasUuids;` in models.
-   Always include timestamps and soft deletes when applicable.

### 2. Folder Structure

```
app/
├── Models/                 # Eloquent models with UUID support
├── Enums/                  # Centralized enum definitions
├── Http/
│    ├── Controllers/
│    │    ├── Api/          # API controllers for mobile
│    │    └── Web/          # Web controllers for admin
│    ├── Requests/
│    ├── Resources/
│    └── Middleware/        # Role-based access middleware
├── Livewire/               # Livewire components for web interface
├── Services/
└── Traits/
database/
├── migrations/             # UUID-based migrations
├── seeders/                # Test data seeders
└── factories/              # Model factories with role support
resources/
├── views/
│    ├── components/        # Blade components
│    ├── livewire/         # Livewire views
│    └── layouts/          # Layout templates
routes/
├── api.php                # Mobile API routes
├── web.php                # Web admin routes
└── auth.php               # Authentication routes
```

---

## 🧱 Database Rules

### Primary Keys

-   All tables use `uuid('id')->primary()` as the primary key.
-   Foreign keys referencing primary keys must also be of type `foreignUuid('foreign_key')`.

### Foreign Key Cascade

-   Use `->cascadeOnDelete()` and `->cascadeOnUpdate()` where logical.
-   Disable foreign keys for polymorphic or flexible relationships (like `pictures`).

### User Profile Fields

-   Users table also includes optional profile fields used across the admin UI:
    -   `nik` (string, 50)
    -   `phone` (string, 50)
    -   `address` (string, 255)
    -   `rejection_reason` (text) — set when a volunteer is rejected by admin

### User Types & Status

-   **User Types**:
    -   `admin` - Web-only access, full system control
    -   `officer` - Created by admin, web + mobile access
    -   `volunteer` - Self-registered, web + mobile access (default: `volunteer`)
-   **User Status**:
    -   `registered` - New volunteer awaiting approval (default: `registered`)
    -   `active` - Approved user with full permissions
    -   `inactive` - Suspended or deactivated user
-   **Disaster Types**: `gempa bumi`, `tsunami`, `gunung meletus`, `banjir`, `kekeringan`, `angin topan`, `tahan longsor`, `bencanan non alam`, `bencana sosial`
-   **Disaster Status**: `ongoing`, `completed` (default: `ongoing`)
-   **Disaster Source**: `BMKG`, `manual` (default: `BMKG`)
-   **Victim Status**: `luka ringan`, `luka berat`, `meninggal`, `hilang` (default: `luka ringan`)
-   **Picture Types**: `profile`, `disaster`, `report`, `victim`, `aid`

---

## 🧩 Enum Rules

Keep enums centralized in `app/Enums/`.

### Required Enums:

```php
// app/Enums/UserTypeEnum.php
namespace App\Enums;

enum UserTypeEnum: string {
    case ADMIN = 'admin';
    case OFFICER = 'officer';
    case VOLUNTEER = 'volunteer';
}

// app/Enums/UserStatusEnum.php
enum UserStatusEnum: string {
    case REGISTERED = 'registered';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

// app/Enums/DisasterTypeEnum.php
enum DisasterTypeEnum: string {
    case GEMPA_BUMI = 'gempa bumi';
    case TSUNAMI = 'tsunami';
    case GUNUNG_MELETUS = 'gunung meletus';
    case BANJIR = 'banjir';
    case KEKERINGAN = 'kekeringan';
    case ANGIN_TOPAN = 'angin topan';
    case TAHAN_LONGSOR = 'tahan longsor';
    case BENCANA_NON_ALAM = 'bencanan non alam';
    case BENCANA_SOSIAL = 'bencana sosial';
}

// app/Enums/DisasterStatusEnum.php
enum DisasterStatusEnum: string {
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
}

// app/Enums/DisasterSourceEnum.php
enum DisasterSourceEnum: string {
    case BMKG = 'BMKG';
    case MANUAL = 'manual';
}

// app/Enums/DisasterVictimStatusEnum.php
enum DisasterVictimStatusEnum: string {
    case LUKA_RINGAN = 'luka ringan';
    case LUKA_BERAT = 'luka berat';
    case MENINGGAL = 'meninggal';
    case HILANG = 'hilang';
}

// app/Enums/PictureTypeEnum.php
enum PictureTypeEnum: string {
    case PROFILE = 'profile';
    case DISASTER = 'disaster';
    case REPORT = 'report';
    case VICTIM = 'victim';
    case AID = 'aid';
}
```

Use them in models like:

```php
protected $casts = [
    'type' => UserTypeEnum::class,
    'status' => UserStatusEnum::class,
    'types' => DisasterTypeEnum::class,
];
```

---

## 🔐 Authentication & Authorization Rules

### User Types & Access Control

The application implements a **three-tier role-based access control system**:

#### **1. Admin Users**

-   **Access**: Web application only (admin dashboard)
-   **Creation**: Created directly by system administrators
-   **Status**: Always `active` upon creation
-   **Permissions**:
    -   Full system administration
    -   User management (create officers, approve volunteers)
    -   Disaster management and monitoring
    -   Report verification and validation
    -   System configuration and settings

#### **2. Officer Users**

-   **Access**: Both web application and mobile API
-   **Creation**: Added by admin users through admin dashboard
-   **Status**: Always `active` upon creation
-   **Permissions**:
    -   Disaster reporting and management
    -   Volunteer coordination
    -   Victim and aid management
    -   Real-time updates and monitoring
    -   Field operations support

#### **3. Volunteer Users**

-   **Access**: Both web application and mobile API
-   **Creation**: Self-registration through public registration page
-   **Status**: Starts as `registered`, requires admin approval to become `active`
-   **Permissions** (when `active`):
    -   Disaster reporting and management
    -   Volunteer coordination
    -   Victim and aid management
    -   Real-time updates and monitoring
    -   Field operations support

### Authentication Flow

#### **Web Application (Laravel + Livewire)**

-   **Admin**: Full access to admin dashboard
-   **Officer**: Limited access to operational features
-   **Volunteer**: Limited access to operational features (when `active`)

#### **Mobile Application (API)**

-   **Officer**: Full API access for mobile operations
-   **Volunteer**: Full API access for mobile operations (when `active`)
-   **Admin**: No mobile access (web-only role)

### User Status Management

#### **Status Types**

-   **`registered`**: New volunteer awaiting admin approval
-   **`active`**: Approved user with full permissions
-   **`inactive`**: Suspended or deactivated user

#### **Status Workflow**

1. **Volunteer Registration**: User registers → Status: `registered`
2. **Admin Review**: Admin reviews volunteer application in admin dashboard
3. **Approval**: Admin approves → Status: `active`
4. **Access Granted**: User can now access both web and mobile

#### **Volunteer Approval Process**

-   **Registration**: Volunteers self-register through `/register` page
-   **Initial Status**: Automatically set to `registered` status
-   **Admin Notification**: Admin receives notification of new volunteer registration
-   **Review Process**: Admin reviews volunteer details in admin dashboard
-   **Approval Action**: Admin can approve (`active`) or reject (`inactive`) volunteer
-   **Access Control**: Only `active` volunteers can access the system

### Technical Implementation

-   Use **Laravel Fortify** for login, registration, password reset, and 2FA.
-   JWT or Sanctum tokens will be implemented for API routes (`api.php`).
-   Only `active` users may log in to any system.
-   Role-based access controlled via `user->type` and `user->status`.
-   Middleware will enforce access restrictions based on user type and status.

### Middleware Implementation

#### **Implemented Middleware Classes**

-   `EnsureUserIsActive`: Verify user has `active` status
-   `EnsureUserIsAdmin`: Restrict access to admin users only
-   `EnsureUserIsOfficerOrVolunteer`: Allow officers and active volunteers
-   `EnsureUserCanAccessWeb`: Check web access permissions
-   `EnsureUserCanAccessAPI`: Check API access permissions

#### **Middleware Aliases Registered**

```php
// In bootstrap/app.php
$middleware->alias([
    'active' => \App\Http\Middleware\EnsureUserIsActive::class,
    'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    'officer_or_volunteer' => \App\Http\Middleware\EnsureUserIsOfficerOrVolunteer::class,
    'web_access' => \App\Http\Middleware\EnsureUserCanAccessWeb::class,
    'api_access' => \App\Http\Middleware\EnsureUserCanAccessAPI::class,
]);
```

#### **Middleware Usage Examples**

```php
// Unified dashboard for all roles (admin will be redirected to admin dashboard)
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'active']);

// Admin area without URL prefix (guarded by middleware)
Route::middleware(['auth', 'active', 'admin'])->group(function () {
    // Admin Dashboard (optional direct access besides redirect from /dashboard)
    Route::get('/admin-dashboard', [AdminController::class, 'dashboard']);

    // User Management
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/users/{user}', [AdminController::class, 'showUser']);
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);

    // Volunteer Management
    Route::get('/volunteers', [AdminController::class, 'volunteers']);
    Route::patch('/volunteers/{user}/approve', [AdminController::class, 'approveVolunteer']);
    Route::patch('/volunteers/{user}/reject', [AdminController::class, 'rejectVolunteer']);

    // Officer Management
    Route::get('/officers/create', [AdminController::class, 'createOfficer']);
    Route::post('/officers', [AdminController::class, 'storeOfficer']);
});

// Officer and Volunteer (Web)
Route::middleware(['auth', 'active', 'officer_or_volunteer'])->group(function () {
    Route::get('/operations', [OperationsController::class, 'index']);
    Route::get('/disasters', [DisasterController::class, 'index']);
});

// API (officer and volunteer)
Route::middleware(['auth:sanctum', 'active', 'api_access'])->group(function () {
    Route::get('/api/disasters', [DisasterController::class, 'index']);
    Route::post('/api/disasters', [DisasterController::class, 'store']);
});
```

#### **Test Routes Available**

-   `/test/active` - Tests active user middleware
-   `/test/admin` - Tests admin-only access
-   `/test/officer-volunteer` - Tests officer/volunteer access

---

## 📦 Models + Relationships

Each model should:

-   Use `HasFactory`, `HasUuids`, `SoftDeletes` (when logical).
-   Define inverse relations properly with `belongsTo` and `hasMany`.

Example (`DisasterVolunteer.php`):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DisasterVolunteer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['disaster_id', 'user_id'];

    public function disaster() {
        return $this->belongsTo(Disaster::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
```

---

## 🧰 Artisan Workflow

| Task                     | Command                                          |
| ------------------------ | ------------------------------------------------ |
| Create model + migration | `php artisan make:model Disaster -m`             |
| Refresh DB               | `php artisan migrate:fresh --seed`               |
| Generate UUID factory    | `php artisan make:factory DisasterFactory`       |
| Run tests                | `php artisan test`                               |
| Serve app                | `php artisan serve`                              |
| Create seeder            | `php artisan make:seeder UserSeeder`             |
| Run specific seeder      | `php artisan db:seed --class=UserSeeder`         |
| Create middleware        | `php artisan make:middleware EnsureUserIsActive` |
| List routes              | `php artisan route:list --name=test`             |

## 🧪 Testing & Development Setup

### Test Users Available

The application includes a comprehensive `UserSeeder` with predefined test users:

#### **Admin Users**

-   **admin@edisaster.test** (password: `password`)
    -   Type: `admin` | Status: `active`
    -   Location: Jakarta | Timezone: Asia/Jakarta (WIB)
    -   Access: Web-only (admin dashboard)

#### **Officer Users**

-   **fathur@edisaster.test** (password: `password`)
    -   Type: `officer` | Status: `active`
    -   Location: Padang | Timezone: Asia/Jakarta (WIB)
    -   Access: Web + Mobile

#### **Volunteer Users**

-   **ilham@edisaster.test** (password: `password`)

    -   Type: `volunteer` | Status: `active`
    -   Location: Jakarta | Timezone: Asia/Jakarta (WIB)
    -   Access: Web + Mobile

-   **nouval@edisaster.test** (password: `password`)

    -   Type: `volunteer` | Status: `active`
    -   Location: Padang | Timezone: Asia/Jakarta (WIB)
    -   Access: Web + Mobile

-   **fariz@edisaster.test** (password: `password`)
    -   Type: `volunteer` | Status: `active`
    -   Location: Batusangkar | Timezone: Asia/Jakarta (WIB)
    -   Access: Web + Mobile

### Factory States Available

The `UserFactory` includes role-specific states:

```php
// Create specific user types
User::factory()->admin()->create();
User::factory()->officer()->create();
User::factory()->volunteer()->create();           // registered status
User::factory()->activeVolunteer()->create();     // active status
User::factory()->inactive()->create();
User::factory()->withLocation()->create();        // includes location data
```

---

## 🛣️ Route Architecture

### Web Routes (`routes/web.php`)

-   **Purpose**: Admin dashboard and management interface
-   **Technology**: Laravel Blade templates + Livewire 3 + Volt
-   **Authentication**: Laravel Fortify (web session-based)
-   **Users**: Admin and officers for system management
-   **Features**:
    -   User management
    -   Disaster monitoring
    -   Report verification
    -   System administration

### API Routes (`routes/api.php`)

-   **Purpose**: Mobile application backend (Android)
-   **Technology**: RESTful API endpoints
-   **Authentication**: JWT or Laravel Sanctum tokens
-   **Users**: Mobile app users (volunteers, officers, public)
-   **Features**:
    -   Disaster reporting
    -   Real-time updates
    -   Volunteer coordination
    -   Victim and aid management

---

## 🧭 Application Features (Web & API)

### Web (Laravel + Livewire)

-   All Roles:
    -   Login
    -   Profile (built-in Laravel auth)
    -   Notifications list
-   User (Volunteer) only:
    -   Register (requires admin approval before logging in)
-   Admin:
    -   Manage Officers
    -   Approve Volunteers
    -   Reject Volunteers with mandatory reason (stored in `users.rejection_reason`)
-   All roles with role-specific views:
    -   Dashboard
    -   Disasters list
    -   Add new disaster (form)
    -   Disaster detail
    -   Update disaster (form)
    -   Add new disaster report (form)
    -   Update disaster report (form)
    -   Disaster victims list
    -   Add new disaster victim (form)
    -   Disaster victim detail
    -   Update disaster victim (form)
    -   Disaster aids list
    -   Add new disaster aid (form)
    -   Update disaster aid (form)

### API (RESTful for Mobile)

-   Volunteer registration (requires admin approval before logging in)
-   Login
-   Profile
-   Notifications (real-time with FCM)
-   Dashboard (home)
-   Disasters list
-   Add new disaster (form)
-   Disaster detail
-   Update disaster (form)
-   Add new disaster report (form)
-   Update disaster report (form)
-   Disaster victims list
-   Add new disaster victim (form)
-   Disaster victim detail
-   Update disaster victim (form)
-   Disaster aids list
-   Add new disaster aid (form)
-   Update disaster aid (form)
-   Map/location for disaster reports update (optional)

---

## 🧑‍💻 Agent Instructions (for Cursor / Copilot)

> Always follow these when writing code in this repository.

1. **Prefer UUIDs** — never use `$table->id()`; always use `$table->uuid('id')->primary();`.
2. **Link models correctly** — ensure all foreign key constraints match UUID column types.
3. **Avoid dummy data** — prefer seeders and factories for reproducible examples.
4. **Respect timestamps** — all models must include `created_at` and `updated_at`.
5. **Follow REST naming** — routes must be plural nouns (e.g. `/api/disasters`).
6. **Never modify base Laravel auth migrations** directly — extend instead.
7. **Document new migrations and models** with concise PHPDoc blocks.
8. **Avoid mass assignment issues** — always define `$fillable`.

---

## 🧪 Example Migration Template

```php
Schema::create('disasters', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('reported_by')->nullable()->constrained('users')->cascadeOnDelete();
    $table->string('title', 255);
    $table->text('description')->nullable();
    $table->enum('source', ['BMKG', 'manual'])->default('BMKG');
    $table->enum('types', ['gempa bumi','tsunami','gunung meletus','banjir','kekeringan','angin topan','tahan longsor','bencana non alam','bencana sosial']);
    $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
    $table->date('date')->nullable();
    $table->time('time')->nullable();
    $table->string('location', 255)->nullable();
    $table->text('coordinate')->nullable();
    $table->float('lat')->nullable();
    $table->float('long')->nullable();
    $table->float('magnitude')->nullable();
    $table->float('depth')->nullable();
    $table->timestamps();
});
```

---

## 🧭 API Structure

| Resource           | Endpoint                      | Method | Auth      | Description            |
| ------------------ | ----------------------------- | ------ | --------- | ---------------------- |
| `users`            | `/api/users`                  | GET    | Admin     | List all users         |
| `disasters`        | `/api/disasters`              | GET    | Public    | Get all disasters      |
| `disaster-reports` | `/api/disasters/{id}/reports` | GET    | Auth      | Reports per disaster   |
| `disaster-aids`    | `/api/disasters/{id}/aids`    | GET    | Volunteer | Aid records            |
| `notifications`    | `/api/notifications`          | GET    | Auth      | Get user notifications |

---

## ✅ Commit Guidelines

Use **conventional commits**:

```
feat: add disaster volunteers relationship
fix: correct uuid foreign key on disaster reports
chore: update seeder data for testing
refactor: clean up model casting
```

---

## 🔧 Recent Updates & Fixes

### User Registration Fix

-   **Issue**: Registration failing due to required `location` field
-   **Solution**: Made `location` and `coordinate` fields nullable in users migration
-   **Result**: Registration now works without requiring location data

### Enhanced User Model

-   **Added**: `HasUuids` and `SoftDeletes` traits to all models
-   **Added**: Enum casting for `type` and `status` fields
-   **Added**: Default value assignment in model boot method
-   **Added**: Comprehensive relationships between all models

### Comprehensive Testing Setup

-   **UserSeeder**: Predefined test users with Indonesian locations
-   **UserFactory**: Role-specific factory states for easy testing
-   **Timezone**: All users set to Asia/Jakarta (WIB) for consistency
-   **Locations**: Jakarta, Padang, Batusangkar with accurate coordinates

### Enum Integration

-   **Created**: 7 centralized enum classes in `app/Enums/`
-   **Implemented**: Type-safe enum casting in all models
-   **Standardized**: Consistent enum usage across the application

### Role-Based Middleware System

-   **Created**: 5 comprehensive middleware classes for access control
-   **Registered**: Middleware aliases in `bootstrap/app.php`
-   **Implemented**: Role-based route protection (admin, officer, volunteer)
-   **Added**: Test routes for middleware validation
-   **Features**:
    -   Active user verification
    -   Admin-only access control
    -   Officer/volunteer access control
    -   Web vs API access separation
    -   Proper error handling and redirects

### Admin UI/UX Simplification

-   Consolidated Users/Officers/Volunteers into single-page admin views with inline modals
-   Removed standalone officer create/edit pages and corresponding routes
-   Dashboard includes contextual heading/description; redundant quick action removed

### Volunteer Rejection Reason

-   Added `rejection_reason` to `users` table and `User` model `$fillable`
-   Rejection now requires a reason; stored and available for auditing

### Complete REST API Implementation

-   **API Structure**: Versioned API (`/api/v1`) with comprehensive endpoints
-   **Authentication**: Laravel Sanctum token-based authentication
-   **Controllers**: Full CRUD controllers for all disaster-related entities
-   **Endpoints**:
    -   Auth: login, register, logout, profile management
    -   Disasters: CRUD with volunteer assignment system
    -   Reports: Disaster reporting with final stage workflow
    -   Victims & Aids: Complete management system
    -   Notifications: Full notification system with statistics
    -   Pictures: Image upload/management for all entities

### Picture Management System

-   **Picture Model**: Generic picture storage with `foreign_id` and `type` fields
-   **PictureController**: CRUD operations for image uploads
-   **Profile Pictures**: Integrated with user profile management
-   **File Storage**: Organized in `pictures/{modelType}/` directories
-   **Validation**: File type and size validation (max 2MB)
-   **Database Fix**: Fixed `file_path` column length (45 → 255 characters)

### Profile Management API

-   **Profile Update**: `PUT /api/v1/profile` for user information updates
-   **Password Change**: `PUT /api/v1/profile/password` with current password verification
-   **Profile Picture**: Upload/delete profile pictures with automatic cleanup
-   **Enhanced /me**: Includes profile picture information in user data

### Disaster Management Enhancements

-   **Status Protection**: Prevent modification of cancelled/completed disasters
-   **Audit Trail**: Added `cancelled_at`, `cancelled_by`, `completed_at`, `completed_by` fields
-   **Cancel Endpoint**: Dedicated disaster cancellation with reason tracking
-   **Final Stage Workflow**: Automatic disaster completion when reports marked as final
-   **Volunteer Assignment**: Self-assignment system for disaster participation

### Database Schema Updates

-   **Enum Values**: Updated all enums to use English values consistently
-   **Column Sizes**: Fixed various column length issues for proper data storage
-   **Foreign Keys**: Proper UUID foreign key constraints with cascade rules
-   **Timestamps**: Added missing timestamps to notifications table

## 🧭 Cursor / Copilot Role Summary

When generating code in this project:

-   Always assume **Laravel 12 + UUID + Dual Architecture** context.
-   **Web Routes** (`web.php`): Use Livewire + Blade for admin interface
-   **API Routes** (`api.php`): Use RESTful endpoints for mobile app
-   Never use auto-increment IDs - always use UUIDs.
-   Write **clean, documented, production-ready** PHP code.
-   When unsure about a column or key — **refer to this file**.
-   **Test Users**: Use predefined test users from UserSeeder for development
-   **Factory States**: Leverage UserFactory states for role-based testing
-   **Middleware**: Use registered middleware aliases for route protection
-   **Access Control**: Implement role-based access using `active`, `admin`, `officer_or_volunteer` middleware

### View Structure (Admin)

-   Admin management screens are single Blade files:
    -   `resources/views/admin/users.blade.php`
    -   `resources/views/admin/officers.blade.php`
    -   `resources/views/admin/volunteers.blade.php`
-   Create/Edit forms for Officers are handled via modals inside the index page; no separate create/edit routes.

---

> **Author:** Mustafa Fathur Rahman
> **Version:** 1.0
> **Date:** October 2025
> **Purpose:** Standardize AI-generated code across Cursor and Copilot for e-Disaster Laravel backend.

---

## 🧭 Sidebar Navigation Structure

The application uses a sidebar layout component at `resources/views/components/layouts/app/sidebar.blade.php`.

### Current Structure

-   Platform

    -   Dashboard → route `dashboard` (visible to all authenticated users)

-   Admin (visible only when `auth()->user()->type->value === 'admin'`)

    -   Users → route `admin.users`
    -   Volunteers → route `admin.volunteers`
    -   Officers → route `admin.officers.create`

-   Disasters (currently placeholders; routes to be wired when features are implemented)
    -   Disaster List → href `#` (intended: `disasters.index`)
    -   Add Disaster → href `#` (intended: `disasters.create`)
    -   Reports → href `#` (intended: `reports.index`)
    -   Victims → href `#` (intended: `victims.index`)
    -   Aid → href `#` (intended: `aids.index`)

### Dropdown Structure (Tree Format) // Proposed

-   Dashboard
-   Bencana (All of disaster derivative is on the disaster detail)
-   Petugas (Officer management)
-   Sukarelawan
    -   Manajemen
    -   Persetujuan

### Notes

-   Icons use common Flux icons only (e.g., `home`, `users`, `plus`, `document`, `heart`, `cog`) to avoid missing-icon errors.
-   Navigation links use `wire:navigate` to integrate smoothly with Livewire/Volt.
-   Active state highlighting is handled via `:current="request()->routeIs('name*')"` where routes exist.
