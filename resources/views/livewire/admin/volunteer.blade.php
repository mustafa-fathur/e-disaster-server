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
            session()->flash('error', 'Invalid volunteer status.');
            return;
        }

        $user->update(['status' => UserStatusEnum::ACTIVE]);
        session()->flash('success', 'Volunteer approved successfully.');
    }

    public function rejectVolunteer($userId)
    {
        $user = User::findOrFail($userId);
        
        if ($user->type !== UserTypeEnum::VOLUNTEER || $user->status !== UserStatusEnum::REGISTERED) {
            session()->flash('error', 'Invalid volunteer status.');
            return;
        }

        if (empty($this->rejectionReason)) {
            session()->flash('error', 'Rejection reason is required.');
            return;
        }

        $user->update([
            'status' => UserStatusEnum::INACTIVE,
            'rejection_reason' => $this->rejectionReason,
        ]);

        $this->rejectionReason = '';
        session()->flash('success', 'Volunteer rejected with reason saved.');
    }

    public function setRejectionReason($reason)
    {
        $this->rejectionReason = $reason;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Volunteer Management') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="space-y-6">
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

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Approval</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $volunteers->total() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Volunteers</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $totalVolunteers }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Volunteers</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $activeVolunteers }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Volunteers Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-neutral-200 dark:border-neutral-700">
                    <div class="p-6">
                        <div class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Volunteers Awaiting Approval ({{ $volunteers->total() }})
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Review and approve volunteer applications
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Volunteer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Applied</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($volunteers as $volunteer)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-green-300 dark:bg-green-600 flex items-center justify-center">
                                                            <span class="text-sm font-medium text-green-700 dark:text-green-300">
                                                                {{ strtoupper(substr($volunteer->name, 0, 2)) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $volunteer->name }}</div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $volunteer->email }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $volunteer->location ?? 'Not provided' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $volunteer->created_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    {{ ucfirst($volunteer->status->value) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button wire:click="approveVolunteer('{{ $volunteer->id }}')" 
                                                            class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-xs">
                                                        Approve
                                                    </button>
                                                    <button type="button" onclick="document.getElementById('reject-{{ $volunteer->id }}').showModal()" 
                                                            class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-xs">
                                                        Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    
                                    <!-- Reject Modal -->
                                    <dialog id="reject-{{ $volunteer->id }}" class="mx-auto w-full max-w-md overflow-hidden rounded-xl bg-white p-0 shadow-xl backdrop:bg-black/40 dark:bg-gray-800">
                                        <form method="dialog">
                                            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                                                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Reject Volunteer</h3>
                                                <button class="rounded-md px-2 py-1 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Close</button>
                                            </div>
                                        </form>
                                        <form wire:submit.prevent="rejectVolunteer('{{ $volunteer->id }}')" class="grid gap-4 p-6">
                                            <label class="text-sm text-neutral-700 dark:text-neutral-300">Reason</label>
                                            <textarea wire:model="rejectionReason" rows="4" placeholder="Provide a clear reason for rejection" 
                                                      class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-gray-700 dark:text-gray-200" required></textarea>
                                            <div class="flex items-center justify-end gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                                                <button type="button" onclick="document.getElementById('reject-{{ $volunteer->id }}').close()" 
                                                        class="rounded-md px-3 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Cancel</button>
                                                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700">Reject</button>
                                            </div>
                                        </form>
                                    </dialog>

                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center">
                                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                                    </svg>
                                                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No volunteers awaiting approval</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">All volunteers have been processed.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $volunteers->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
