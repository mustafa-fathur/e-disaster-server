<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $rejectionReason = '';

    public function with(): array
    {
        $volunteers = User::where('type', UserTypeEnum::VOLUNTEER)
            ->where('status', UserStatusEnum::REGISTERED)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $totalVolunteers = User::where('type', UserTypeEnum::VOLUNTEER)->count();
        $activeVolunteers = User::where('type', UserTypeEnum::VOLUNTEER)
            ->where('status', UserStatusEnum::ACTIVE)
            ->count();

        return [
            'volunteers' => $volunteers,
            'totalVolunteers' => $totalVolunteers,
            'activeVolunteers' => $activeVolunteers
        ];
    }

    public function approveVolunteer($userId)
    {
        $user = User::findOrFail($userId);
        
        if ($user->type !== UserTypeEnum::VOLUNTEER || $user->status !== UserStatusEnum::REGISTERED) {
            session()->flash('error', 'Status relawan tidak valid.');
            return;
        }

        $user->update([
            'status' => UserStatusEnum::ACTIVE,
            'approved_at' => now(),
        ]);
        session()->flash('success', 'Relawan berhasil disetujui.');
    }

    public function rejectVolunteer($userId)
    {
        $user = User::findOrFail($userId);
        
        if ($user->type !== UserTypeEnum::VOLUNTEER || $user->status !== UserStatusEnum::REGISTERED) {
            session()->flash('error', 'Status relawan tidak valid.');
            return;
        }

        if (empty($this->rejectionReason)) {
            session()->flash('error', 'Alasan penolakan wajib diisi.');
            return;
        }

        $user->update([
            'status' => UserStatusEnum::INACTIVE,
            'rejection_reason' => $this->rejectionReason,
        ]);

        $this->rejectionReason = '';
        session()->flash('success', 'Relawan ditolak dan alasan telah disimpan.');
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Manajemen Relawan') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Tinjau dan setujui aplikasi relawan yang menunggu persetujuan.') }}</flux:subheading>
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

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Menunggu Persetujuan</p>
                <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $volunteers->total() }}</p>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Relawan</p>
                <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $totalVolunteers }}</p>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Relawan Aktif</p>
                <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $activeVolunteers }}</p>
            </div>
        </div>
        <!-- Volunteers Table -->
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/40">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-50 px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-800/40 dark:text-zinc-200">Relawan</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">NIK</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Telepon</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Lokasi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Terdaftar</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                        @forelse ($volunteers as $volunteer)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-200 text-green-700 dark:bg-green-700 dark:text-green-200">
                                                <span class="text-sm font-semibold">
                                                    {{ strtoupper(substr($volunteer->name, 0, 2)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->name }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $volunteer->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $volunteer->nik ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $volunteer->phone ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $volunteer->location ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $volunteer->created_at->format('M d, Y') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button type="button" 
                                            onclick="document.getElementById('volunteer-details-{{ $volunteer->id }}').showModal()"
                                            class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-blue-700 transition-colors duration-200 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-900/20">Rincian</button>
                                    <button type="button" 
                                            onclick="document.getElementById('approve-volunteer-{{ $volunteer->id }}').showModal()"
                                            class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-green-700 transition-colors duration-200 hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-900/20">Setujui</button>
                                    <button type="button" 
                                            onclick="document.getElementById('reject-{{ $volunteer->id }}').showModal()"
                                            class="inline-flex items-center rounded-md px-3 py-1.5 text-red-700 transition-colors duration-200 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20">Tolak</button>
                                </td>
                            </tr>
                            
                            <!-- Details Modal -->
                            <dialog id="volunteer-details-{{ $volunteer->id }}" class="mx-auto w-full max-w-4xl p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rincian Pendaftaran Relawan</h3>
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
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->name }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Email</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->email }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">NIK</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->nik ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Nomor Telepon</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->phone ?? '—' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Informasi Pribadi -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Informasi Pribadi</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Alamat</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->address ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Jenis Kelamin</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    @if($volunteer->gender === null)
                                                        —
                                                    @elseif($volunteer->gender)
                                                        Perempuan
                                                    @else
                                                        Laki-laki
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Tanggal Lahir</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->date_of_birth ? $volunteer->date_of_birth->format('d M Y') : '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Alasan Bergabung</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->reason_to_join ?? '—' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Lokasi & Aktivitas -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Lokasi & Aktivitas</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Lokasi</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->location ?? '—' }}</dd>
                                            </div>
                                            @if($volunteer->lat && $volunteer->long)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Koordinat</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($volunteer->lat, 6) }}, {{ number_format($volunteer->long, 6) }}</dd>
                                            </div>
                                            @endif
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Terdaftar Pada</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $volunteer->registered_at?->format('d M Y H:i') ?? $volunteer->created_at->format('d M Y H:i') }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Status</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">Terdaftar</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </dialog>

                            <!-- Approve Volunteer Modal -->
                            <dialog id="approve-volunteer-{{ $volunteer->id }}" class="mx-auto w-full max-w-md p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Setujui Relawan</h3>
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
                                        <h4 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Konfirmasi Setujui Relawan</h4>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Apakah Anda yakin ingin menyetujui aplikasi <strong>{{ $volunteer->name }}</strong>? 
                                            Relawan ini akan dapat mengakses sistem setelah disetujui.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <button type="button" 
                                                wire:click="approveVolunteer('{{ $volunteer->id }}')"
                                                onclick="document.getElementById('approve-volunteer-{{ $volunteer->id }}').close()"
                                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-green-700">
                                            Setujui
                                        </button>
                                    </div>
                                </div>
                            </dialog>

                            <!-- Reject Modal -->
                            <dialog id="reject-{{ $volunteer->id }}" class="mx-auto w-full max-w-md p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Tolak Relawan</h3>
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
                                    <form wire:submit.prevent="rejectVolunteer('{{ $volunteer->id }}')" class="space-y-4">
                                        <div class="mb-6 text-center">
                                            <h4 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Konfirmasi Tolak Relawan</h4>
                                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                                Apakah Anda yakin ingin menolak aplikasi <strong>{{ $volunteer->name }}</strong>? 
                                                Berikan alasan penolakan di bawah ini.
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Alasan Penolakan</label>
                                            <textarea wire:model="rejectionReason" rows="4" placeholder="Berikan alasan yang jelas untuk penolakan" 
                                                      class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required></textarea>
                                        </div>
                                        <div class="flex items-center justify-center">
                                            <button type="submit" 
                                                    onclick="document.getElementById('reject-{{ $volunteer->id }}').close()"
                                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-red-700">
                                                Tolak
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </dialog>

                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada relawan yang menunggu persetujuan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $volunteers->links() }}
            </div>
        </div>
    </div>
</section>
