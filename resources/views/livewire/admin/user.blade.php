<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $type = '';
    public $status = '';
    public $search = '';

    public function with(): array
    {
        $query = User::query();

        // Filter by type
        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        // Filter by status
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        // Search by name or email
        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return [
            'users' => $users
        ];
    }

    public function updateUserStatus($userId, $newStatus)
    {
        $user = User::findOrFail($userId);
        
        // Prevent admin status changes
        if ($user->type === UserTypeEnum::ADMIN) {
            session()->flash('error', 'Tidak dapat mengubah status pengguna admin.');
            return;
        }

        $user->update(['status' => $newStatus]);
        
        session()->flash('success', 'Status pengguna berhasil diperbarui.');
    }

    public function clearFilters()
    {
        $this->type = '';
        $this->status = '';
        $this->search = '';
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Manajemen Pengguna') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Kelola pengguna aplikasi: tinjau detail, filter, aktifkan atau nonaktifkan akun.') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="space-y-6">
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

        <!-- Filters -->
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit.prevent="clearFilters" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="type" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipe</label>
                    <select wire:model.live="type" id="type" class="mt-1 block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        <option value="">{{ __('Semua Tipe') }}</option>
                        <option value="admin">Admin</option>
                        <option value="officer">Petugas</option>
                        <option value="volunteer">Relawan</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Status</label>
                    <select wire:model.live="status" id="status" class="mt-1 block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        <option value="">{{ __('Semua Status') }}</option>
                        <option value="active">Aktif</option>
                        <option value="registered">Terdaftar</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>

                <div>
                    <label for="search" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cari</label>
                    <input type="text" wire:model.live.debounce.300ms="search" id="search"
                           placeholder="Nama atau email..."
                           class="mt-1 block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                </div>

                <div class="flex items-end">
                    <button type="button" wire:click="clearFilters" class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">Bersihkan</button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/40">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-50 px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-800/40 dark:text-zinc-200">Pengguna</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Lokasi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">NIK</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Telepon</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Alamat</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Dibuat</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                        @forelse ($users as $user)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                                <span class="text-sm font-semibold">
                                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold @if($user->type->value === 'admin') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 @elseif($user->type->value === 'officer') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 @else bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 @endif">
                                        {{ $user->type->value === 'officer' ? 'Petugas' : ( $user->type->value === 'volunteer' ? 'Relawan' : ucfirst($user->type->value) ) }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold @if($user->status->value === 'active') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 @elseif($user->status->value === 'registered') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 @else bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 @endif">
                                        {{ $user->status->value === 'active' ? 'Aktif' : ( $user->status->value === 'registered' ? 'Terdaftar' : 'Nonaktif' ) }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $user->location ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $user->nik ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $user->phone ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $user->address ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button type="button" onclick="document.getElementById('user-details-{{ $user->id }}').showModal()" class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-blue-700 transition-colors duration-200 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-900/20">Rincian</button>

                                    @if($user->type->value !== 'admin')
                                        @if($user->status->value === 'active')
                                            <button type="button" onclick="document.getElementById('deactivate-user-{{ $user->id }}').showModal()"
                                                    class="inline-flex items-center rounded-md px-3 py-1.5 text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Nonaktifkan
                                            </button>
                                        @else
                                            <button type="button" onclick="document.getElementById('activate-user-{{ $user->id }}').showModal()"
                                                    class="inline-flex items-center rounded-md px-3 py-1.5 text-green-700 hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-900/20">
                                                Aktifkan
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>

                            <!-- Deactivate User Modal -->
                            @if($user->type->value !== 'admin' && $user->status->value === 'active')
                            <dialog id="deactivate-user-{{ $user->id }}" class="mx-auto w-full max-w-md p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Nonaktifkan Pengguna</h3>
                                        <button class="rounded-md px-2 py-1 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                                    </div>
                                </form>
                                <div class="p-6">
                                    <div class="mb-4">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="mb-6 text-center">
                                        <h4 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Konfirmasi Nonaktifkan Akun</h4>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Apakah Anda yakin ingin menonaktifkan akun <strong>{{ $user->name }}</strong>? 
                                            Pengguna ini tidak akan dapat mengakses sistem setelah akun dinonaktifkan.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <button type="button" 
                                                wire:click="updateUserStatus('{{ $user->id }}', 'inactive')"
                                                onclick="document.getElementById('deactivate-user-{{ $user->id }}').close()"
                                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-red-700">
                                            Nonaktifkan
                                        </button>
                                    </div>
                                </div>
                            </dialog>
                            @endif

                            <!-- Activate User Modal -->
                            @if($user->type->value !== 'admin' && $user->status->value !== 'active')
                            <dialog id="activate-user-{{ $user->id }}" class="mx-auto w-full max-w-md p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Aktifkan Pengguna</h3>
                                        <button class="rounded-md px-2 py-1 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                                    </div>
                                </form>
                                <div class="p-6">
                                    <div class="mb-4">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="mb-6 text-center">
                                        <h4 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Konfirmasi Aktifkan Akun</h4>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Apakah Anda yakin ingin mengaktifkan akun <strong>{{ $user->name }}</strong>? 
                                            Pengguna ini akan dapat mengakses sistem setelah akun diaktifkan.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <button type="button" 
                                                wire:click="updateUserStatus('{{ $user->id }}', 'active')"
                                                onclick="document.getElementById('activate-user-{{ $user->id }}').close()"
                                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-green-700">
                                            Aktifkan
                                        </button>
                                    </div>
                                </div>
                            </dialog>
                            @endif

                            <!-- Details Modal -->
                            <dialog id="user-details-{{ $user->id }}" class="mx-auto w-full max-w-4xl p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rincian Pengguna</h3>
                                        <button class="rounded-md px-2 py-1 text-base text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                                    </div>
                                </form>

                                <div class="grid gap-6 p-6 md:grid-cols-3">
                                    <!-- Informasi Dasar -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Informasi Dasar</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Nama Lengkap</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Email</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->email }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Tipe</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->type->value === 'officer' ? 'Petugas' : ( $user->type->value === 'volunteer' ? 'Relawan' : ucfirst($user->type->value) ) }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Status</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->status->value === 'active' ? 'Aktif' : ( $user->status->value === 'registered' ? 'Terdaftar' : 'Nonaktif' ) }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Email Terverifikasi</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->email_verified ? 'Ya' : 'Tidak' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Informasi Pribadi -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Informasi Pribadi</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">NIK</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->nik ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Telepon</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->phone ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Alamat</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->address ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Jenis Kelamin</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    @if($user->gender === null)
                                                        —
                                                    @elseif($user->gender)
                                                        Perempuan
                                                    @else
                                                        Laki-laki
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Tanggal Lahir</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->date_of_birth ? $user->date_of_birth->format('d M Y') : '—' }}</dd>
                                            </div>
                                            @if($user->type->value === 'volunteer' && $user->reason_to_join)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Alasan Bergabung</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->reason_to_join }}</dd>
                                            </div>
                                            @endif
                                        </dl>
                                    </div>

                                    <!-- Lokasi & Aktivitas -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Lokasi & Aktivitas</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Lokasi</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->location ?? 'Belum disetel' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Zona Waktu</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->timezone ?? '—' }}</dd>
                                            </div>
                                            @if($user->lat && $user->long)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Koordinat</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($user->lat, 6) }}, {{ number_format($user->long, 6) }}</dd>
                                            </div>
                                            @endif
                                            @if($user->last_login_at)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Terakhir Masuk</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->last_login_at->format('d M Y H:i') }}</dd>
                                            </div>
                                            @endif
                                            @if($user->type->value === 'volunteer')
                                                @if($user->registered_at)
                                                <div class="flex flex-col gap-1">
                                                    <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Terdaftar Pada</dt>
                                                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->registered_at->format('d M Y H:i') }}</dd>
                                                </div>
                                                @endif
                                                @if($user->approved_at)
                                                <div class="flex flex-col gap-1">
                                                    <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Disetujui Pada</dt>
                                                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->approved_at->format('d M Y H:i') }}</dd>
                                                </div>
                                                @endif
                                                @if($user->rejection_reason)
                                                <div class="flex flex-col gap-1">
                                                    <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Alasan Penolakan</dt>
                                                    <dd class="font-medium text-red-600 dark:text-red-400">{{ $user->rejection_reason }}</dd>
                                                </div>
                                                @endif
                                            @endif
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Akun Dibuat</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->created_at->format('d M Y H:i') }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Terakhir Diperbarui</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->updated_at->format('d M Y H:i') }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </dialog>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada pengguna.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</section>