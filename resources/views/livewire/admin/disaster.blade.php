<?php

use App\Models\Disaster;
use App\Enums\DisasterTypeEnum;
use App\Enums\DisasterStatusEnum;
use App\Enums\DisasterSourceEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        $disasters = Disaster::query()
            ->latest()
            ->paginate(15);

        return [
            'disasters' => $disasters
        ];
    }

    public $title = '';
    public $description = '';
    public $source = 'BMKG';
    public $types = 'gempa bumi';
    public $status = 'ongoing';
    public $date = '';
    public $time = '';
    public $location = '';
    public $coordinate = '';
    public $lat = '';
    public $long = '';
    public $magnitude = '';
    public $depth = '';

    public $editDisasterId = null;
    public $editTitle = '';
    public $editDescription = '';
    public $editSource = '';
    public $editTypes = '';
    public $editStatus = '';
    public $editDate = '';
    public $editTime = '';
    public $editLocation = '';
    public $editCoordinate = '';
    public $editLat = '';
    public $editLong = '';
    public $editMagnitude = '';
    public $editDepth = '';

    public function storeDisaster()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => 'required|in:BMKG,manual',
            'types' => 'required|string',
            'status' => 'required|in:ongoing,completed',
            'date' => 'nullable|date',
            'time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'coordinate' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'magnitude' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
        ]);

        Disaster::create([
            'title' => $this->title,
            'description' => $this->description,
            'source' => $this->source,
            'types' => $this->types,
            'status' => $this->status,
            'date' => $this->date,
            'time' => $this->time,
            'location' => $this->location,
            'coordinate' => $this->coordinate,
            'lat' => $this->lat,
            'long' => $this->long,
            'magnitude' => $this->magnitude,
            'depth' => $this->depth,
            'reported_by' => auth()->id(),
        ]);

        $this->reset(['title', 'description', 'source', 'types', 'status', 'date', 'time', 'location', 'coordinate', 'lat', 'long', 'magnitude', 'depth']);
        session()->flash('success', 'Disaster created successfully.');
    }

    public function viewDisaster($disasterId)
    {
        // View logic handled by modal
    }

    public function editDisaster($disasterId)
    {
        $disaster = Disaster::findOrFail($disasterId);
        $this->editDisasterId = $disasterId;
        $this->editTitle = $disaster->title;
        $this->editDescription = $disaster->description ?? '';
        $this->editSource = $disaster->source->value;
        $this->editTypes = $disaster->types->value;
        $this->editStatus = $disaster->status->value;
        $this->editDate = $disaster->date ? $disaster->date->format('Y-m-d') : '';
        $this->editTime = $disaster->time ? $disaster->time->format('H:i') : '';
        $this->editLocation = $disaster->location ?? '';
        $this->editCoordinate = $disaster->coordinate ?? '';
        $this->editLat = $disaster->lat ?? '';
        $this->editLong = $disaster->long ?? '';
        $this->editMagnitude = $disaster->magnitude ?? '';
        $this->editDepth = $disaster->depth ?? '';
    }

    public function updateDisaster($disasterId)
    {
        $disaster = Disaster::findOrFail($disasterId);
        
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDescription' => 'nullable|string',
            'editSource' => 'required|in:BMKG,manual',
            'editTypes' => 'required|string',
            'editStatus' => 'required|in:ongoing,completed',
            'editDate' => 'nullable|date',
            'editTime' => 'nullable|date_format:H:i',
            'editLocation' => 'nullable|string|max:255',
            'editCoordinate' => 'nullable|string',
            'editLat' => 'nullable|numeric',
            'editLong' => 'nullable|numeric',
            'editMagnitude' => 'nullable|numeric',
            'editDepth' => 'nullable|numeric',
        ]);

        $disaster->update([
            'title' => $this->editTitle,
            'description' => $this->editDescription,
            'source' => $this->editSource,
            'types' => $this->editTypes,
            'status' => $this->editStatus,
            'date' => $this->editDate,
            'time' => $this->editTime,
            'location' => $this->editLocation,
            'coordinate' => $this->editCoordinate,
            'lat' => $this->editLat,
            'long' => $this->editLong,
            'magnitude' => $this->editMagnitude,
            'depth' => $this->editDepth,
        ]);

        $this->reset(['editDisasterId', 'editTitle', 'editDescription', 'editSource', 'editTypes', 'editStatus', 'editDate', 'editTime', 'editLocation', 'editCoordinate', 'editLat', 'editLong', 'editMagnitude', 'editDepth']);
        session()->flash('success', 'Disaster updated successfully.');
    }

    public function destroyDisaster($disasterId)
    {
        $disaster = Disaster::findOrFail($disasterId);
        $disaster->delete();
        session()->flash('success', 'Disaster deleted successfully.');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Disasters') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="space-y-6">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Disaster List') }}</h3>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">Browse recent disasters. Use the button to add a new disaster.</p>
                        </div>
                        <button type="button" onclick="document.getElementById('disaster-create-modal').showModal()" class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-white">
                            {{ __('Add New Disaster') }}
                        </button>
                    </div>
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

                <div class="rounded-xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                            <thead class="bg-neutral-50 dark:bg-neutral-900/40">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Types</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-900">
                                @forelse ($disasters as $disaster)
                                    <tr>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $disaster->title }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300">{{ $disaster->types->value }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $disaster->status->value === 'ongoing' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' }}">
                                                {{ ucfirst($disaster->status->value) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300">{{ $disaster->location ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300">{{ optional($disaster->date)->format('Y-m-d') ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                            <div class="flex items-center gap-2">
                                                <button type="button" wire:click="viewDisaster('{{ $disaster->id }}')" onclick="document.getElementById('disaster-view-{{ $disaster->id }}').showModal()" class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 ring-1 ring-inset ring-neutral-200 transition hover:bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700">{{ __('View') }}</button>
                                                <button type="button" wire:click="editDisaster('{{ $disaster->id }}')" onclick="document.getElementById('disaster-edit-{{ $disaster->id }}').showModal()" class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 ring-1 ring-inset ring-neutral-200 transition hover:bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700">{{ __('Edit') }}</button>
                                                <button wire:click="destroyDisaster('{{ $disaster->id }}')" 
                                                        onclick="return confirm('Delete this disaster?')"
                                                        class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">{{ __('Delete') }}</button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- View Disaster Modal -->
                                    <dialog id="disaster-view-{{ $disaster->id }}" class="mx-auto w-full max-w-4xl overflow-hidden rounded-xl bg-white p-0 shadow-xl backdrop:bg-black/40 dark:bg-gray-800">
                                        <form method="dialog">
                                            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                                                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Disaster Details</h3>
                                                <button class="rounded-md px-2 py-1 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Close</button>
                                            </div>
                                        </form>
                                        <div class="grid gap-6 p-6 md:grid-cols-2">
                                            <div>
                                                <h4 class="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Basic Information</h4>
                                                <dl class="space-y-2 text-sm">
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Title</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $disaster->title }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Type</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $disaster->types->value }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Status</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ ucfirst($disaster->status->value) }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Source</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $disaster->source->value }}</dd></div>
                                                </dl>
                                            </div>
                                            <div>
                                                <h4 class="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Location & Details</h4>
                                                <dl class="space-y-2 text-sm">
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Location</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $disaster->location ?? 'Not set' }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Date</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ optional($disaster->date)->format('Y-m-d') ?? 'Not set' }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Time</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ optional($disaster->time)->format('H:i') ?? 'Not set' }}</dd></div>
                                                    <div class="flex justify-between"><dt class="text-neutral-500 dark:text-neutral-400">Magnitude</dt><dd class="font-medium text-neutral-900 dark:text-neutral-100">{{ $disaster->magnitude ?? 'Not set' }}</dd></div>
                                                </dl>
                                            </div>
                                        </div>
                                        @if($disaster->description)
                                        <div class="border-t border-neutral-200 p-6 dark:border-neutral-700">
                                            <h4 class="mb-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Description</h4>
                                            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ $disaster->description }}</p>
                                        </div>
                                        @endif
                                    </dialog>

                                    <!-- Edit Disaster Modal -->
                                    <dialog id="disaster-edit-{{ $disaster->id }}" class="mx-auto w-full max-w-4xl overflow-hidden rounded-xl bg-white p-0 shadow-xl backdrop:bg-black/40 dark:bg-gray-800">
                                        <form method="dialog">
                                            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                                                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Edit Disaster</h3>
                                                <button class="rounded-md px-2 py-1 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Close</button>
                                            </div>
                                        </form>
                                        <form wire:submit.prevent="updateDisaster('{{ $disaster->id }}')" class="grid gap-4 p-6">
                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Title</label>
                                                    <input wire:model="editTitle" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Source</label>
                                                    <select wire:model="editSource" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required>
                                                        <option value="BMKG">BMKG</option>
                                                        <option value="manual">Manual</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Type</label>
                                                    <select wire:model="editTypes" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required>
                                                        <option value="gempa bumi">Gempa Bumi</option>
                                                        <option value="tsunami">Tsunami</option>
                                                        <option value="gunung meletus">Gunung Meletus</option>
                                                        <option value="banjir">Banjir</option>
                                                        <option value="kekeringan">Kekeringan</option>
                                                        <option value="angin topan">Angin Topan</option>
                                                        <option value="tahan longsor">Tanah Longsor</option>
                                                        <option value="bencana non alam">Bencana Non Alam</option>
                                                        <option value="bencana sosial">Bencana Sosial</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Status</label>
                                                    <select wire:model="editStatus" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required>
                                                        <option value="ongoing">Ongoing</option>
                                                        <option value="completed">Completed</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Date</label>
                                                    <input wire:model="editDate" type="date" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Time</label>
                                                    <input wire:model="editTime" type="time" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Location</label>
                                                    <input wire:model="editLocation" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Magnitude</label>
                                                    <input wire:model="editMagnitude" type="number" step="any" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Description</label>
                                                <textarea wire:model="editDescription" rows="4" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900"></textarea>
                                            </div>
                                            <div class="flex items-center justify-end gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                                                <button type="button" onclick="document.getElementById('disaster-edit-{{ $disaster->id }}').close()" class="rounded-md px-3 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Cancel</button>
                                                <button type="submit" class="rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-800 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-white">Save Changes</button>
                                            </div>
                                        </form>
                                    </dialog>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-6 text-center text-sm text-neutral-600 dark:text-neutral-300">No disasters found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4">
                        {{ $disasters->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Disaster Modal -->
    <dialog id="disaster-create-modal" class="mx-auto w-full max-w-4xl overflow-hidden rounded-xl bg-white p-0 shadow-xl backdrop:bg-black/40 dark:bg-gray-800">
        <form method="dialog">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Create Disaster</h3>
                <button class="rounded-md px-2 py-1 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Close</button>
            </div>
        </form>
        <form wire:submit.prevent="storeDisaster" class="grid gap-4 p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Title</label>
                    <input wire:model="title" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Source</label>
                    <select wire:model="source" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required>
                        <option value="BMKG">BMKG</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Type</label>
                    <select wire:model="types" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required>
                        <option value="gempa bumi">Gempa Bumi</option>
                        <option value="tsunami">Tsunami</option>
                        <option value="gunung meletus">Gunung Meletus</option>
                        <option value="banjir">Banjir</option>
                        <option value="kekeringan">Kekeringan</option>
                        <option value="angin topan">Angin Topan</option>
                        <option value="tahan longsor">Tanah Longsor</option>
                        <option value="bencana non alam">Bencana Non Alam</option>
                        <option value="bencana sosial">Bencana Sosial</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Status</label>
                    <select wire:model="status" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" required>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Date</label>
                    <input wire:model="date" type="date" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Time</label>
                    <input wire:model="time" type="time" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Location</label>
                    <input wire:model="location" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Coordinate</label>
                    <input wire:model="coordinate" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Latitude</label>
                    <input wire:model="lat" type="number" step="any" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Longitude</label>
                    <input wire:model="long" type="number" step="any" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Magnitude</label>
                    <input wire:model="magnitude" type="number" step="any" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Depth</label>
                    <input wire:model="depth" type="number" step="any" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Description</label>
                <textarea wire:model="description" rows="4" class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-neutral-500 focus:ring-neutral-500 dark:border-neutral-700 dark:bg-neutral-900"></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                <button type="button" onclick="document.getElementById('disaster-create-modal').close()" class="rounded-md px-3 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">Cancel</button>
                <button type="submit" class="rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-800 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-white">Create Disaster</button>
            </div>
        </form>
    </dialog>
</div>
