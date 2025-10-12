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
            session()->flash('error', 'Cannot change admin user status.');
            return;
        }

        $user->update(['status' => $newStatus]);
        
        session()->flash('success', 'User status updated successfully.');
    }

    public function clearFilters()
    {
        $this->type = '';
        $this->status = '';
        $this->search = '';
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('User Management') }}
            </h2>
            <a href="{{ route('admin.officer') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Create Officer
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="space-y-6">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Manage application users</h3>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">Review user details, filter by type/status, and activate or deactivate accounts. Use "Create Officer" to add new officer users.</p>
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

                <!-- Filters -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <form wire:submit.prevent="clearFilters" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                            <select wire:model.live="type" id="type" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-gray-700 dark:text-gray-300">
                                <option value="">All Types</option>
                                <option value="admin">Admin</option>
                                <option value="officer">Officer</option>
                                <option value="volunteer">Volunteer</option>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select wire:model.live="status" id="status" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-gray-700 dark:text-gray-300">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="registered">Registered</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                            <input type="text" wire:model.live.debounce.300ms="search" id="search" 
                                   placeholder="Name or email..." 
                                   class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-gray-700 dark:text-gray-300">
                        </div>
                        <div class="flex items-end">
                            <button type="button" wire:click="clearFilters" class="w-full rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-white">Clear</button>
                        </div>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto divide-y divide-neutral-200 dark:divide-neutral-700">
                            <thead class="bg-neutral-50 dark:bg-gray-700/40">
                                <tr>
                                    <th class="sticky left-0 z-10 bg-neutral-50 px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:bg-gray-700/40 dark:text-neutral-200">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">NIK</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Created</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-200">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-gray-800">
                                @forelse ($users as $user)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-gray-700/30">
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200">
                                                        <span class="text-sm font-semibold">
                                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $user->name }}</div>
                                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold @if($user->type->value === 'admin') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 @elseif($user->type->value === 'officer') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 @else bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 @endif">{{ ucfirst($user->type->value) }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold @if($user->status->value === 'active') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 @elseif($user->status->value === 'registered') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 @else bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 @endif">{{ ucfirst($user->status->value) }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">{{ $user->location ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">{{ $user->nik ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">{{ $user->phone ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">{{ $user->address ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-500 dark:text-neutral-400">
                                            {{ $user->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <button type="button" onclick="document.getElementById('user-details-{{ $user->id }}').showModal()" class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-blue-700 transition-colors duration-200 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-900/20">Details</button>
                                            @if($user->type->value !== 'admin')
                                                @if($user->status->value === 'active')
                                                    <button type="button" wire:click="updateUserStatus('{{ $user->id }}', 'inactive')" 
                                                            onclick="return confirm('Are you sure you want to deactivate this account?')"
                                                            class="inline-flex items-center rounded-md px-3 py-1.5 text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20">
                                                        Deactivate
                                                    </button>
                                                @else
                                                    <button type="button" wire:click="updateUserStatus('{{ $user->id }}', 'active')" 
                                                            onclick="return confirm('Are you sure you want to activate this account?')"
                                                            class="inline-flex items-center rounded-md px-3 py-1.5 text-green-700 hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-900/20">
                                                        Activate
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- Details Modal -->
                                    <dialog id="user-details-{{ $user->id }}" class="mx-auto w-full max-w-xl p-0 overflow-hidden rounded-xl bg-white shadow-xl backdrop:bg-black/40 dark:bg-gray-800">
                                        <form method="dialog">
                                            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                                                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">User Details</h3>
                                                <button class="rounded-md px-2 py-1 text-sm text-neutral-600 transition-colors duration-200 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Close</button>
                                            </div>
                                        </form>
                                        <div class="grid gap-6 p-6 md:grid-cols-2">
                                            <div>
                                                <h4 class="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Basic Information</h4>
                                                <dl class="space-y-2 text-sm">
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Full Name</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->name }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Email</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->email }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Type</dt><dd class="font-medium capitalize text-neutral-900 dark:text-neutral-100">{{ $user->type->value }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Status</dt><dd class="font-medium capitalize text-neutral-900 dark:text-neutral-100">{{ $user->status->value }}</dd></div>
                                                </dl>
                                            </div>
                                            <div>
                                                <h4 class="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Location & Activity</h4>
                                                <dl class="space-y-2 text-sm">
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Location</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->location ?? 'Not set' }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Timezone</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->timezone ?? '—' }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Account Created</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->created_at->format('M d, Y H:i') }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Last Updated</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->updated_at->format('M d, Y H:i') }}</dd></div>
                                                </dl>
                                            </div>
                                        </div>
                                    </dialog>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">No users found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
