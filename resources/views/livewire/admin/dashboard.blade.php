<?php

use App\Models\User;
use App\Models\Disaster;
use App\Models\DisasterVictim;
use App\Models\DisasterAid;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use App\Enums\DisasterStatusEnum;
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
                'ongoing_disasters' => Disaster::where('status', DisasterStatusEnum::ONGOING)->count(),
                'completed_disasters' => Disaster::where('status', DisasterStatusEnum::COMPLETED)->count(),
                'total_victims' => DisasterVictim::count(),
                'evacuated_victims' => DisasterVictim::where('is_evacuated', true)->count(),
                'total_aid_items' => DisasterAid::count(),
            ]
        ];
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Pantau metrik utama dan kelola tugas administratif') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div role="alert" class="mb-6 rounded-lg border border-green-300 bg-green-50 p-4 text-green-800 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    
    @if (session('error'))
        <div role="alert" class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Stats Grid -->
    <div class="space-y-6">
        <!-- User Management Section -->
        <div>
            <flux:heading level="2" size="lg" class="mb-4">{{ __('Manajemen Pengguna') }}</flux:heading>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Total Users Stat -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Pengguna</p>
                    <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_users'] }}</p>
                </div>

                <!-- Active Users Stat -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Pengguna Aktif</p>
                    <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['active_users'] }}</p>
                </div>

                <!-- Pending Volunteers Stat - Clickable -->
                <a href="{{ route('admin.volunteer') }}" class="group rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 cursor-pointer">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Relawan Menunggu</p>
                    <div class="flex items-end justify-between">
                        <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['registered_volunteers'] }}</p>
                        <svg class="h-5 w-5 text-zinc-400 transition group-hover:translate-x-1 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </a>
            </div>
        </div>

        <!-- User Type Stats -->
        <div>
            <flux:heading level="2" size="lg" class="mb-4">{{ __('Tipe Pengguna') }}</flux:heading>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Admins Stat -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Admin</p>
                    <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['admin_users'] }}</p>
                    <div class="mt-4">
                        <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Sistem</span>
                    </div>
                </div>

                <!-- Officers Stat - Clickable -->
                <a href="{{ route('admin.officer') }}" class="group rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 cursor-pointer">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Petugas</p>
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['officer_users'] }}</p>
                            <div class="mt-4">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 transition group-hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:group-hover:bg-blue-900/50">Staf</span>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-zinc-400 transition group-hover:translate-x-1 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </a>

                <!-- Volunteers Stat - Clickable -->
                <a href="{{ route('admin.user', ['type' => 'volunteer']) }}" class="group rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 cursor-pointer">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Relawan</p>
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['volunteer_users'] }}</p>
                            <div class="mt-4">
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 transition group-hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:group-hover:bg-emerald-900/50">Komunitas</span>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-zinc-400 transition group-hover:translate-x-1 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </a>
            </div>
        </div>

        <!-- Disaster Management Section -->
        <div>
            <flux:heading level="2" size="lg" class="mb-4">{{ __('Manajemen Bencana') }}</flux:heading>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Ongoing Disasters -->
                <a href="{{ route('admin.disaster') }}" class="group rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 cursor-pointer">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Bencana Berlangsung</p>
                    <div class="flex items-end justify-between">
                        <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['ongoing_disasters'] }}</p>
                        <svg class="h-5 w-5 text-zinc-400 transition group-hover:translate-x-1 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </a>

                <!-- Completed Disasters -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Bencana Selesai</p>
                    <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['completed_disasters'] }}</p>
                </div>

                <!-- Total Victims -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Korban</p>
                    <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_victims'] }}</p>
                </div>
            </div>
        </div>

        <!-- Disaster Response Section -->
        <div>
            <flux:heading level="2" size="lg" class="mb-4">{{ __('Respons Bencana') }}</flux:heading>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Evacuated Victims -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Korban Dievakuasi</p>
                    <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['evacuated_victims'] }}</p>
                </div>

                <!-- Total Aid Items -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Bantuan</p>
                    <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_aid_items'] }}</p>
                </div>
            </div>
        </div>
    </div>
</section>