<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Hash;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $name = '';
    public $email = '';
    public $nik = '';
    public $phone = '';
    public $address = '';
    public $gender = '';
    public $date_of_birth = '';
    public $password = '';
    public $password_confirmation = '';
    public $editOfficerId = null;
    public $editName = '';
    public $editEmail = '';
    public $editNik = '';
    public $editPhone = '';
    public $editAddress = '';
    public $editGender = '';
    public $editDateOfBirth = '';
    public $editPassword = '';
    public $editStatus = '';
    public $showEditModal = false;

    public function with(): array
    {
        $officers = User::where('type', UserTypeEnum::OFFICER)
            ->when($this->search, function ($q) {
                $q->where(function ($s) {
                    $s->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return [
            'officers' => $officers
        ];
    }

    public function storeOfficer()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'nik' => 'required|string|max:45|unique:users',
            'phone' => 'required|string|max:45',
            'address' => 'required|string',
            'gender' => 'required|boolean',
            'date_of_birth' => 'required|date|before:today',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'nik' => $this->nik,
            'phone' => $this->phone,
            'address' => $this->address,
            'gender' => (bool) $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'password' => Hash::make($this->password),
            'type' => UserTypeEnum::OFFICER,
            'status' => UserStatusEnum::ACTIVE,
            'email_verified' => true,
        ]);

        $this->reset(['name', 'email', 'nik', 'phone', 'address', 'gender', 'date_of_birth', 'password', 'password_confirmation']);
        session()->flash('success', 'Petugas berhasil dibuat.');
    }

    public function editOfficer($officerId)
    {
        $officer = User::findOrFail($officerId);
        $this->editOfficerId = $officerId;
        $this->editName = $officer->name;
        $this->editEmail = $officer->email;
        $this->editNik = $officer->nik;
        $this->editPhone = $officer->phone;
        $this->editAddress = $officer->address;
        $this->editGender = $officer->gender;
        $this->editDateOfBirth = $officer->date_of_birth ? $officer->date_of_birth->format('Y-m-d') : '';
        $this->editStatus = $officer->status->value;
        
        $this->dispatch('officer-edited');
    }

    public function updateOfficer()
    {
        $officer = User::findOrFail($this->editOfficerId);
        
        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => 'required|string|email|max:255|unique:users,email,' . $officer->id,
            'editNik' => 'required|string|max:45|unique:users,nik,' . $officer->id,
            'editPhone' => 'required|string|max:45',
            'editAddress' => 'required|string',
            'editGender' => 'required|boolean',
            'editDateOfBirth' => 'required|date|before:today',
            'editStatus' => 'required|in:active,inactive',
            'editPassword' => 'nullable|string|min:8|confirmed',
        ]);

        $updateData = [
            'name' => $this->editName,
            'email' => $this->editEmail,
            'nik' => $this->editNik,
            'phone' => $this->editPhone,
            'address' => $this->editAddress,
            'gender' => (bool) $this->editGender,
            'date_of_birth' => $this->editDateOfBirth,
            'status' => $this->editStatus,
        ];

        if (!empty($this->editPassword)) {
            $updateData['password'] = Hash::make($this->editPassword);
        }

        $officer->update($updateData);

        $this->reset(['editOfficerId', 'editName', 'editEmail', 'editNik', 'editPhone', 'editAddress', 'editGender', 'editDateOfBirth', 'editPassword', 'editStatus']);
        session()->flash('success', 'Petugas berhasil diperbarui.');
    }

    public function destroyOfficer($officerId)
    {
        $officer = User::findOrFail($officerId);
        $officer->delete();
        session()->flash('success', 'Petugas berhasil dihapus.');
    }

    public function cancelEdit()
    {
        $this->reset(['editOfficerId', 'editName', 'editEmail', 'editNik', 'editPhone', 'editAddress', 'editGender', 'editDateOfBirth', 'editPassword', 'editStatus']);
    }

    public function clearFilters()
    {
        $this->search = '';
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Manajemen Petugas') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Kelola akun petugas: buat, edit, dan hapus petugas. Petugas memiliki akses ke web dan mobile.') }}</flux:subheading>
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

        <!-- Search Section -->
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-4 text-base font-semibold text-zinc-900 dark:text-zinc-100">Cari Petugas</h3>
            <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-300">Cari petugas berdasarkan nama atau email. Gunakan tombol "Buat Petugas" untuk menambahkan petugas baru.</p>
            <form wire:submit.prevent="clearFilters" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cari</label>
                    <input type="text" wire:model.live.debounce.300ms="search" id="search"
                           placeholder="Nama atau email..."
                           class="mt-1 block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                </div>

                <div class="flex items-end">
                    <button type="button" wire:click="clearFilters" class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">Bersihkan</button>
                </div>

                <div class="flex items-end">
                    <button type="button" onclick="document.getElementById('officer-create-modal').showModal()" class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 shadow-sm">Buat Petugas</button>
                </div>
            </form>
        </div>

        <!-- Officers Table -->
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/40">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-50 px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-800/40 dark:text-zinc-200">Petugas</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Lokasi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Telepon</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Dibuat</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                        @forelse($officers as $officer)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-200 text-blue-700 dark:bg-blue-700 dark:text-blue-200">
                                                <span class="text-sm font-semibold">
                                                    {{ strtoupper(substr($officer->name, 0, 2)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $officer->name }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $officer->email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold @if($officer->status->value === 'active') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 @else bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 @endif">
                                        {{ $officer->status->value === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $officer->location ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $officer->phone ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $officer->created_at->format('M d, Y') }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button type="button" 
                                            wire:click="editOfficer('{{ $officer->id }}')"
                                            class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-blue-700 transition-colors duration-200 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-900/20">Edit</button>
                                    <button type="button" onclick="document.getElementById('delete-officer-{{ $officer->id }}').showModal()" class="inline-flex items-center rounded-md px-3 py-1.5 text-red-700 transition-colors duration-200 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20">Hapus</button>
                                </td>
                            </tr>

                            <!-- Delete Officer Modal -->
                            <dialog id="delete-officer-{{ $officer->id }}" class="mx-auto w-full max-w-md p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Hapus Petugas</h3>
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
                                        <h4 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Konfirmasi Hapus Petugas</h4>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Apakah Anda yakin ingin menghapus petugas <strong>{{ $officer->name }}</strong>? 
                                            Tindakan ini tidak dapat dibatalkan dan semua data terkait akan dihapus.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <button type="button" 
                                                wire:click="destroyOfficer('{{ $officer->id }}')"
                                                onclick="document.getElementById('delete-officer-{{ $officer->id }}').close()"
                                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-red-700">
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </dialog>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada petugas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $officers->links() }}
            </div>
        </div>

        <!-- Edit Officer Modal -->
        <dialog id="officer-edit-modal" 
                class="mx-auto w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
            <form method="dialog">
                <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Edit Petugas</h3>
                    <button type="button" wire:click="cancelEdit" onclick="document.getElementById('officer-edit-modal').close()" class="rounded-md px-2 py-1 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                </div>
            </form>
            <form wire:submit.prevent="updateOfficer" class="flex flex-col max-h-[80vh]">
                <div class="overflow-y-auto px-6 py-4">
                    <div class="grid gap-4">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nama Lengkap</label>
                            <input wire:model="editName" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Email</label>
                            <input wire:model="editEmail" type="email" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">NIK</label>
                            <input wire:model="editNik" type="text" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Telepon</label>
                            <input wire:model="editPhone" type="text" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Alamat</label>
                            <textarea wire:model="editAddress" rows="3" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Jenis Kelamin</label>
                            <select wire:model="editGender" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="0">Laki-laki</option>
                                <option value="1">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tanggal Lahir</label>
                            <input wire:model="editDateOfBirth" type="date" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Status</label>
                            <select wire:model="editStatus" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Password Baru (opsional)</label>
                            <input wire:model="editPassword" type="password" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Konfirmasi Password Baru</label>
                            <input wire:model="editPassword_confirmation" type="password" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-200 bg-white px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <button type="button" onclick="document.getElementById('officer-edit-modal').close(); $wire.cancelEdit();" class="rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Batal</button>
                    <button type="submit" onclick="document.getElementById('officer-edit-modal').close()" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">Simpan</button>
                </div>
            </form>
        </dialog>

        <!-- Create Officer Modal -->
        <dialog id="officer-create-modal" class="mx-auto w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
            <form method="dialog">
                <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Buat Petugas</h3>
                    <button class="rounded-md px-2 py-1 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                </div>
            </form>
            <form wire:submit.prevent="storeOfficer" class="flex flex-col max-h-[80vh]">
                <div class="overflow-y-auto px-6 py-4">
                    <div class="grid gap-4">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nama Lengkap</label>
                            <input wire:model="name" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Email</label>
                            <input wire:model="email" type="email" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">NIK</label>
                            <input wire:model="nik" type="text" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Telepon</label>
                            <input wire:model="phone" type="text" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Alamat</label>
                            <textarea wire:model="address" rows="3" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Jenis Kelamin</label>
                            <select wire:model="gender" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="0">Laki-laki</option>
                                <option value="1">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tanggal Lahir</label>
                            <input wire:model="date_of_birth" type="date" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Password</label>
                            <input wire:model="password" type="password" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Konfirmasi Password</label>
                            <input wire:model="password_confirmation" type="password" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-200 bg-white px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <button type="button" onclick="document.getElementById('officer-create-modal').close()" class="rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Batal</button>
                    <button type="submit" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">Buat</button>
                </div>
            </form>
        </dialog>
    </div>
</section>

@script
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('officer-edited', () => {
            setTimeout(() => {
                const modal = document.getElementById('officer-edit-modal');
                if (modal) {
                    modal.showModal();
                }
            }, 100);
        });
    });
</script>
@endscript


