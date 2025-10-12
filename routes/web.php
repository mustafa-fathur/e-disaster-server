<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DisasterController;

// Public
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Authenticated settings (shared for all roles)
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

// Admin routes with prefix
Route::prefix('admin')->middleware(['auth', 'active', 'admin'])->group(function () {
    // Dashboard
    Route::get('dashboard', function () {
        $stats = [
            'total_users' => \App\Models\User::count(),
            'active_users' => \App\Models\User::where('status', \App\Enums\UserStatusEnum::ACTIVE)->count(),
            'admin_users' => \App\Models\User::where('type', \App\Enums\UserTypeEnum::ADMIN)->count(),
            'officer_users' => \App\Models\User::where('type', \App\Enums\UserTypeEnum::OFFICER)->count(),
            'volunteer_users' => \App\Models\User::where('type', \App\Enums\UserTypeEnum::VOLUNTEER)->count(),
            'registered_volunteers' => \App\Models\User::where('type', \App\Enums\UserTypeEnum::VOLUNTEER)
                ->where('status', \App\Enums\UserStatusEnum::REGISTERED)
                ->count(),
        ];
        
        return view('admin.dashboard', compact('stats'));
    })->name('admin.dashboard');

    // User Management
    Route::get('users', [AdminController::class, 'users'])->name('admin.users');
    Route::patch('users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('admin.users.status');

    // Volunteer Management
    Route::get('volunteers', [AdminController::class, 'volunteers'])->name('admin.volunteers');
    Route::patch('volunteers/{user}/approve', [AdminController::class, 'approveVolunteer'])->name('admin.volunteers.approve');
    Route::patch('volunteers/{user}/reject', [AdminController::class, 'rejectVolunteer'])->name('admin.volunteers.reject');

    // Officer Management
    Route::get('officers', [AdminController::class, 'officers'])->name('admin.officers');
    Route::post('officers', [AdminController::class, 'storeOfficer'])->name('admin.officers.store');
    Route::patch('officers/{user}', [AdminController::class, 'updateOfficer'])->name('admin.officers.update');
    Route::delete('officers/{user}', [AdminController::class, 'destroyOfficer'])->name('admin.officers.destroy');
    
    // Disasters
    Route::get('disasters', [DisasterController::class, 'index'])->name('admin.disasters');
    Route::get('disasters/create', [DisasterController::class, 'create'])->name('admin.disasters.create');
    Route::post('disasters', [DisasterController::class, 'store'])->name('admin.disasters.store');
    Route::get('disasters/{disaster}', [DisasterController::class, 'show'])->name('admin.disasters.show');
    Route::get('disasters/{disaster}/edit', [DisasterController::class, 'edit'])->name('admin.disasters.edit');
    Route::patch('disasters/{disaster}', [DisasterController::class, 'update'])->name('admin.disasters.update');
    Route::delete('disasters/{disaster}', [DisasterController::class, 'destroy'])->name('admin.disasters.destroy');
});

// Officer routes with prefix
Route::prefix('officer')->middleware(['auth', 'active', 'officer_or_volunteer'])->group(function () {
    // Dashboard
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('officer.dashboard');
    
    // Add more officer routes here as needed
});

// Volunteer routes with prefix
Route::prefix('volunteer')->middleware(['auth', 'active', 'officer_or_volunteer'])->group(function () {
    // Dashboard
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('volunteer.dashboard');
    
    // Add more volunteer routes here as needed
});

// Diagnostic test routes (temporary)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/test/active', function () {
        return response()->json([
            'message' => 'You are an active user!',
            'user' => auth()->user()->only(['name', 'email', 'type', 'status'])
        ]);
    })->name('test.active');
});

Route::middleware(['auth', 'active', 'admin'])->group(function () {
    Route::get('/test/admin', function () {
        return response()->json([
            'message' => 'You are an admin!',
            'user' => auth()->user()->only(['name', 'email', 'type', 'status'])
        ]);
    })->name('test.admin');
});

Route::middleware(['auth', 'active', 'officer_or_volunteer'])->group(function () {
    Route::get('/test/officer-volunteer', function () {
        return response()->json([
            'message' => 'You are an officer or volunteer!',
            'user' => auth()->user()->only(['name', 'email', 'type', 'status'])
        ]);
    })->name('test.officer-volunteer');
});

require __DIR__.'/auth.php';
