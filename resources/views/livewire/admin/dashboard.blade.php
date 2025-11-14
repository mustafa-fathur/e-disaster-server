<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        return [
            'stats' => [
                'total_users' => User::count(),
                'active_users' => User::where('status', UserStatusEnum::ACTIVE)->count(),
                'admin_users' => User::where('type', UserTypeEnum::ADMIN)->count(),
                'officer_users' => User::where('type', UserTypeEnum::OFFICER)->count(),
                'volunteer_users' => User::where('type', UserTypeEnum::VOLUNTEER)->count(),
                'registered_volunteers' => User::where('type', UserTypeEnum::VOLUNTEER)
                    ->where('status', UserStatusEnum::REGISTERED)
                    ->count(),
            ]
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>
    <div class="py-6">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="space-y-6">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-sky-400 dark:text-neutral-100">Welcome to the Admin Dashboard</h3>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">Monitor key metrics and jump to common management tasks. Use the sidebar to navigate to Users, Officers, and Volunteers.</p>
                </div>
                @if (session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                        {{ session('error') }}
                    </div>
                @endif
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Users</p>
                        <p class="mt-2 text-4xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['total_users'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Active Users</p>
                        <p class="mt-2 text-4xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['active_users'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Pending Volunteers</p>
                        <p class="mt-2 text-4xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['registered_volunteers'] }}</p>
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Admins</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['admin_users'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Officers</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['officer_users'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Volunteers</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['volunteer_users'] }}</p>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">Quick Actions</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('admin.user') }}" class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-white">Manage Users</a>
                        <a href="{{ route('admin.volunteer') }}" class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-white">Review Volunteers</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>