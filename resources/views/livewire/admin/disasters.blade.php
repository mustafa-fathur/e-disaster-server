<?php

use App\Models\Disaster;
use App\Models\Picture;
use App\Enums\DisasterTypeEnum;
use App\Enums\DisasterStatusEnum;
use App\Enums\DisasterSourceEnum;
use App\Enums\PictureTypeEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $currentDisaster = null;

    public function with(): array
    {
        $disasters = Disaster::query()
            ->when($this->search, function ($q) {
                $q->where(function ($s) {
                    $s->where('title', 'like', "%{$this->search}%")
                      ->orWhere('location', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return [
            'disasters' => $disasters
        ];
    }

    public function getDisasterPictures($disasterId)
    {
        return Picture::where('foreign_id', $disasterId)
            ->where('type', PictureTypeEnum::DISASTER)
            ->get();
    }

    public function getTypeLabel($typeValue)
    {
        return match($typeValue) {
            'earthquake' => 'Gempa Bumi',
            'tsunami' => 'Tsunami',
            'volcanic_eruption' => 'Gunung Meletus',
            'flood' => 'Banjir',
            'drought' => 'Kekeringan',
            'tornado' => 'Angin Topan',
            'landslide' => 'Tanah Longsor',
            'non_natural_disaster' => 'Bencana Non Alam',
            'social_disaster' => 'Bencana Sosial',
            default => $typeValue,
        };
    }

    public function getSourceLabel($sourceValue)
    {
        return match($sourceValue) {
            'bmkg' => 'BMKG',
            'manual' => 'Manual',
            default => $sourceValue,
        };
    }

    public $title = '';
    public $description = '';
    public $source = 'bmkg';
    public $types = 'earthquake';
    public $status = 'ongoing';
    public $date = '';
    public $time = '';
    public $location = '';
    public $coordinate = '';
    public $lat = '';
    public $long = '';
    public $magnitude = '';
    public $depth = '';
    public $pictures = []; // Multiple files for create

    public function updatedPictures($value)
    {
        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $this->pictures = [$value];
        } elseif (is_array($value)) {
            $this->pictures = $value;
        } elseif ($this->pictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $this->pictures = [$this->pictures];
        } elseif (!is_array($this->pictures)) {
            $this->pictures = [];
        }
    }
    public $editPicture; // Single file upload for edit

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
        try {
            // Validate form data including picture
            $this->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'source' => 'required|in:bmkg,manual',
                'types' => 'required|string',
                'status' => 'required|in:ongoing,completed',
                'date' => 'required|date',
                'time' => 'required|date_format:H:i',
                'location' => 'nullable|string|max:255',
                'coordinate' => 'nullable|string',
                'lat' => 'nullable|numeric',
                'long' => 'nullable|numeric',
                'magnitude' => 'nullable|numeric',
                'depth' => 'nullable|numeric',
                'pictures' => 'nullable|array',
                'pictures.*' => 'image|max:4096',
            ]);

            // Create disaster first
            $disaster = \App\Models\Disaster::create([
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
                'reported_by' => Auth::id(),
            ]);

            // Handle picture uploads - ensure array and iterate
            $files = [];
            if ($this->pictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $files = [$this->pictures];
            } elseif (is_array($this->pictures)) {
                $files = $this->pictures;
            }

            if (!empty($files)) {
                foreach ($files as $file) {
                    try {
                        $storedPath = $file->store('pictures/disaster', 'public');
                        Picture::create([
                            'foreign_id' => $disaster->id,
                            'type' => PictureTypeEnum::DISASTER,
                            'file_path' => $storedPath,
                            'mine_type' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to store disaster picture: ' . $e->getMessage());
                    }
                }
            }
            
            // Reset form
            $this->reset(['title', 'description', 'source', 'types', 'status', 'date', 'time', 'location', 'coordinate', 'lat', 'long', 'magnitude', 'depth', 'pictures']);
            
            session()->flash('success', 'Bencana berhasil dibuat.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Validasi gagal: ' . implode(', ', $e->validator->errors()->all()));
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to store disaster: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            session()->flash('error', 'Gagal membuat bencana: ' . $e->getMessage());
        }
    }

    public function removePicture($index)
    {
        if ($this->pictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $this->pictures = [];
            return;
        }
        if (is_array($this->pictures) && isset($this->pictures[$index])) {
            unset($this->pictures[$index]);
            $this->pictures = array_values($this->pictures);
        }
    }

    public function removeEditPicture()
    {
        $this->editPicture = null;
    }

    public function viewDisaster($disasterId)
    {
        // View logic handled by modal
    }

    public function editDisaster($disasterId)
    {
        $this->currentDisaster = \App\Models\Disaster::findOrFail($disasterId);
        $this->editDisasterId = $disasterId;
        $this->editTitle = $this->currentDisaster->title;
        $this->editDescription = $this->currentDisaster->description ?? '';
        $this->editSource = $this->currentDisaster->source->value;
        $this->editTypes = $this->currentDisaster->types->value;
        $this->editStatus = $this->currentDisaster->status->value;
        if ($this->currentDisaster->date) {
            $dt = $this->currentDisaster->date instanceof \Carbon\Carbon
                ? $this->currentDisaster->date
                : \Carbon\Carbon::parse($this->currentDisaster->date);
            $this->editDate = $dt->format('Y-m-d');
        } else {
            $this->editDate = '';
        }
        $this->editTime = $this->currentDisaster->time ? $this->currentDisaster->time->format('H:i') : '';
        $this->editLocation = $this->currentDisaster->location ?? '';
        $this->editCoordinate = $this->currentDisaster->coordinate ?? '';
        $this->editLat = $this->currentDisaster->lat ?? '';
        $this->editLong = $this->currentDisaster->long ?? '';
        $this->editMagnitude = $this->currentDisaster->magnitude ?? '';
        $this->editDepth = $this->currentDisaster->depth ?? '';
        $this->editPictures = [];
    }

    public function updateDisaster($disasterId)
    {
        $disaster = \App\Models\Disaster::findOrFail($disasterId);
        
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDescription' => 'nullable|string',
            'editSource' => 'required|in:bmkg,manual',
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
            'editPicture' => 'nullable|image|max:4096',
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

        // Handle new picture upload - single file
        if ($this->editPicture) {
            try {
                $storedPath = $this->editPicture->store('pictures/disaster', 'public');
                Picture::create([
                    'foreign_id' => $disaster->id,
                    'type' => PictureTypeEnum::DISASTER,
                    'file_path' => $storedPath,
                    'mine_type' => method_exists($this->editPicture, 'getMimeType') ? $this->editPicture->getMimeType() : null,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to store disaster picture: ' . $e->getMessage());
                session()->flash('error', 'Gagal mengunggah gambar: ' . $e->getMessage());
            }
        }

        $this->reset(['editDisasterId', 'editTitle', 'editDescription', 'editSource', 'editTypes', 'editStatus', 'editDate', 'editTime', 'editLocation', 'editCoordinate', 'editLat', 'editLong', 'editMagnitude', 'editDepth', 'editPicture']);
        $this->editPicture = null;
        $this->currentDisaster = null;
        session()->flash('success', 'Bencana berhasil diperbarui.');
    }

    public function deletePicture($pictureId)
    {
        try {
            $picture = Picture::findOrFail($pictureId);
            if ($picture->file_path && Storage::disk('public')->exists($picture->file_path)) {
                Storage::disk('public')->delete($picture->file_path);
            }
            $picture->delete();
            session()->flash('success', 'Gambar berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Failed to delete picture: ' . $e->getMessage());
            session()->flash('error', 'Gagal menghapus gambar.');
        }
    }

    public function destroyDisaster($disasterId)
    {
        try {
            $disaster = \App\Models\Disaster::findOrFail($disasterId);
            // Delete associated pictures
            foreach (Picture::where('foreign_id', $disaster->id)->where('type', PictureTypeEnum::DISASTER)->get() as $picture) {
                if ($picture->file_path && Storage::disk('public')->exists($picture->file_path)) {
                    Storage::disk('public')->delete($picture->file_path);
                }
                $picture->delete();
            }
            $disaster->delete();
            session()->flash('success', 'Bencana berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Failed to delete disaster: ' . $e->getMessage());
            session()->flash('error', 'Gagal menghapus bencana.');
        }
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Manajemen Bencana') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Kelola data bencana: buat, edit, dan hapus informasi bencana.') }}</flux:subheading>
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
            <h3 class="mb-4 text-base font-semibold text-zinc-900 dark:text-zinc-100">Kelola Bencana</h3>
            <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-300">Tinjau daftar bencana terbaru. Gunakan tombol "Buat Bencana" untuk menambahkan bencana baru.</p>
            <div class="flex items-end">
                <button type="button" onclick="document.getElementById('disaster-create-modal').showModal()" class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 shadow-sm">Buat Bencana</button>
            </div>
        </div>

        <!-- Disasters Table -->
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/40">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-50 px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-800/40 dark:text-zinc-200">Judul</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Jenis</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Lokasi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-200">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                        @forelse ($disasters as $disaster)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->title }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->getTypeLabel($disaster->types->value) }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $disaster->status->value === 'ongoing' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' }}">
                                        {{ $disaster->status->value === 'ongoing' ? 'Berlangsung' : 'Selesai' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ $disaster->location ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ optional($disaster->date)->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button type="button" 
                                            onclick="document.getElementById('disaster-view-{{ $disaster->id }}').showModal()"
                                            class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-blue-700 transition-colors duration-200 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-900/20">Rincian</button>
                                    <button type="button" 
                                            wire:click="editDisaster('{{ $disaster->id }}')"
                                            onclick="document.getElementById('disaster-edit-{{ $disaster->id }}').showModal()"
                                            class="mr-2 inline-flex items-center rounded-md px-3 py-1.5 text-blue-700 transition-colors duration-200 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-900/20">Edit</button>
                                    <button type="button" 
                                            onclick="document.getElementById('delete-disaster-{{ $disaster->id }}').showModal()"
                                            class="inline-flex items-center rounded-md px-3 py-1.5 text-red-700 transition-colors duration-200 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20">Hapus</button>
                                </td>
                            </tr>

                            
                            <!-- View Disaster Modal -->
                            <dialog id="disaster-view-{{ $disaster->id }}" class="mx-auto w-full max-w-4xl p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Rincian Bencana</h3>
                                        <button class="rounded-md px-2 py-1 text-base text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                                    </div>
                                </form>

                                <div class="grid gap-6 p-6 md:grid-cols-3">
                                    <!-- Informasi Dasar -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Informasi Dasar</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Judul</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->title }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Jenis</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->getTypeLabel($disaster->types->value) }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Status</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->status->value === 'ongoing' ? 'Berlangsung' : 'Selesai' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Sumber</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->getSourceLabel($disaster->source->value) }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Lokasi & Waktu -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Lokasi & Waktu</h4>
                                        <dl class="space-y-3 text-base">
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Lokasi</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->location ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Tanggal</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ optional($disaster->date)->format('d M Y') ?? '—' }}</dd>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Waktu</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ optional($disaster->time)->format('H:i') ?? '—' }}</dd>
                                            </div>
                                            @if($disaster->lat && $disaster->long)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Koordinat</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($disaster->lat, 6) }}, {{ number_format($disaster->long, 6) }}</dd>
                                            </div>
                                            @endif
                                            @if($disaster->coordinate)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Koordinat (String)</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->coordinate }}</dd>
                                            </div>
                                            @endif
                                        </dl>
                                    </div>

                                    <!-- Detail Teknis -->
                                    <div>
                                        <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Detail Teknis</h4>
                                        <dl class="space-y-3 text-base">
                                            @if($disaster->magnitude)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Magnitudo</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->magnitude }}</dd>
                                            </div>
                                            @endif
                                            @if($disaster->depth)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Kedalaman</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->depth }} km</dd>
                                            </div>
                                            @endif
                                            @if($disaster->description)
                                            <div class="flex flex-col gap-1">
                                                <dt class="text-zinc-500 dark:text-zinc-400 text-sm">Deskripsi</dt>
                                                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->description }}</dd>
                                            </div>
                                            @endif
                                        </dl>
                                    </div>
                                </div>
                                @php $viewPictures = $this->getDisasterPictures($disaster->id); @endphp
                                @if($viewPictures->count() > 0)
                                <div class="border-t border-zinc-200 p-6 dark:border-zinc-700">
                                    <h4 class="mb-3 text-base font-semibold text-zinc-700 dark:text-zinc-300">Gambar</h4>
                                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                                        @foreach($viewPictures as $picture)
                                            <div class="relative group">
                                                <img src="{{ Storage::url($picture->file_path) }}" alt="{{ $picture->alt_text ?? 'Gambar bencana' }}" class="w-full h-32 object-cover rounded-lg border border-zinc-200 dark:border-zinc-700" />
                                                @if($picture->caption)
                                                    <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">{{ $picture->caption }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </dialog>

                            
                            <!-- Delete Disaster Modal -->
                            <dialog id="delete-disaster-{{ $disaster->id }}" class="mx-auto w-full max-w-md p-0 overflow-hidden rounded-lg bg-white shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Hapus Bencana</h3>
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
                                        <h4 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Konfirmasi Hapus Bencana</h4>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Apakah Anda yakin ingin menghapus bencana <strong>{{ $disaster->title }}</strong>? 
                                            Tindakan ini tidak dapat dibatalkan dan semua data terkait akan dihapus.
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <button type="button" 
                                                wire:click="destroyDisaster('{{ $disaster->id }}')"
                                                onclick="document.getElementById('delete-disaster-{{ $disaster->id }}').close()"
                                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-red-700">
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </dialog>

                            <!-- Edit Disaster Modal -->
                            <dialog id="disaster-edit-{{ $disaster->id }}" wire:ignore.self class="mx-auto w-full max-w-4xl overflow-hidden rounded-lg bg-white p-0 shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
                                <form method="dialog">
                                    <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Edit Bencana</h3>
                                        <button class="rounded-md px-2 py-1 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
                                    </div>
                                </form>
                                <form wire:submit.prevent="updateDisaster('{{ $disaster->id }}')" class="flex flex-col max-h-[80vh]">
                                    <div class="overflow-y-auto p-6">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Judul</label>
                                                <input wire:model="editTitle" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required />
                                            </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Sumber</label>
                                                    <select wire:model="editSource" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required>
                                                        <option value="bmkg">BMKG</option>
                                                        <option value="manual">Manual</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Jenis</label>
                                                    <select wire:model="editTypes" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required>
                                                        <option value="earthquake">Gempa Bumi</option>
                                                        <option value="tsunami">Tsunami</option>
                                                        <option value="volcanic_eruption">Gunung Meletus</option>
                                                        <option value="flood">Banjir</option>
                                                        <option value="drought">Kekeringan</option>
                                                        <option value="tornado">Angin Topan</option>
                                                        <option value="landslide">Tanah Longsor</option>
                                                        <option value="non_natural_disaster">Bencana Non Alam</option>
                                                        <option value="social_disaster">Bencana Sosial</option>
                                                    </select>
                                                </div>
                                            <div>
                                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Status</label>
                                                <select wire:model="editStatus" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" required>
                                                    <option value="ongoing">Berlangsung</option>
                                                    <option value="completed">Selesai</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tanggal</label>
                                                <input wire:model="editDate" type="date" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Waktu</label>
                                                <input wire:model="editTime" type="time" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Lokasi</label>
                                                <input wire:model="editLocation" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Magnitudo</label>
                                                <input wire:model="editMagnitude" type="number" step="any" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Deskripsi</label>
                                            <textarea wire:model="editDescription" rows="4" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"></textarea>
                                        </div>
                                        <div class="mt-4">
                                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Gambar Tambahan (opsional)</label>
                                            <input type="file" 
                                                   wire:model="editPicture" 
                                                   accept="image/*" 
                                                   onchange="
                                                       (function() {
                                                           const editModal = document.getElementById('disaster-edit-{{ $disaster->id }}');
                                                           if (editModal) {
                                                               setTimeout(() => {
                                                                   if (!editModal.open) {
                                                                       editModal.showModal();
                                                                   }
                                                               }, 100);
                                                           }
                                                       })();
                                                   "
                                                   class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Maksimal 4MB per gambar.</p>
                                            @error('editPicture')
                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                            <div wire:loading wire:target="editPicture" class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                                <span class="inline-flex items-center gap-2">
                                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Mengunggah...
                                                </span>
                                            </div>
                                            
                                            @if($editPicture)
                                            <div class="mt-4">
                                                <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Pratinjau Gambar Baru:</p>
                                                <div class="relative group">
                                                    @php
                                                        $previewUrl = null;
                                                        $fileName = '';
                                                        try {
                                                            if (is_object($this->editPicture) && method_exists($this->editPicture, 'temporaryUrl')) {
                                                                $previewUrl = $this->editPicture->temporaryUrl();
                                                            }
                                                            if (is_object($this->editPicture) && method_exists($this->editPicture, 'getClientOriginalName')) {
                                                                $fileName = $this->editPicture->getClientOriginalName();
                                                            }
                                                        } catch (\Exception $e) {
                                                            // Silently fail
                                                        }
                                                    @endphp
                                                    <div class="aspect-square overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 max-w-xs">
                                                        @if($previewUrl)
                                                            <img src="{{ $previewUrl }}" alt="Preview" class="h-full w-full object-cover" loading="lazy" />
                                                        @else
                                                            <div class="flex h-full items-center justify-center">
                                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Memuat...</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <button type="button" 
                                                            wire:click="removeEditPicture"
                                                            wire:loading.attr="disabled"
                                                            class="absolute top-2 right-2 flex items-center justify-center rounded-full bg-red-500 p-1.5 text-white opacity-0 transition-opacity duration-200 hover:bg-red-600 group-hover:opacity-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                    @if($fileName)
                                                    <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 truncate" title="{{ $fileName }}">{{ $fileName }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            @endif
                                            
                                            @php $editPictures = $currentDisaster ? $this->getDisasterPictures($currentDisaster->id) : collect(); @endphp
                                            @if($currentDisaster && $editPictures->isNotEmpty())
                                            <div class="mt-4">
                                                <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Gambar Saat Ini:</p>
                                                <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                                                    @foreach($editPictures as $picture)
                                                        <div class="relative group">
                                                            <div class="aspect-square overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                                                <img src="{{ Storage::url($picture->file_path) }}" alt="{{ $picture->alt_text ?? 'Gambar Bencana' }}" class="h-full w-full object-cover" />
                                                            </div>
                                                            <button type="button" 
                                                                    wire:click="deletePicture('{{ $picture->id }}')"
                                                                    class="absolute top-2 right-2 flex items-center justify-center rounded-full bg-red-500 p-1.5 text-white opacity-0 transition-opacity duration-200 hover:bg-red-600 group-hover:opacity-100">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                            @if($picture->caption)
                                                                <p class="mt-1 text-center text-xs text-zinc-600 dark:text-zinc-400">{{ $picture->caption }}</p>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 border-t border-zinc-200 bg-white px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <button type="button" onclick="document.getElementById('disaster-edit-{{ $disaster->id }}').close()" class="rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Batal</button>
                                        <button type="submit" onclick="document.getElementById('disaster-edit-{{ $disaster->id }}').close()" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">Simpan</button>
                                    </div>
                                        </form>
                                    </dialog>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada bencana.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $disasters->links() }}
            </div>
        </div>

        <!-- Create Disaster Modal -->
        <dialog id="disaster-create-modal" wire:ignore.self class="mx-auto w-full max-w-4xl overflow-hidden rounded-lg bg-white p-0 shadow-xl backdrop:bg-black/40 dark:bg-zinc-900">
    <form method="dialog">
        <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Buat Bencana</h3>
            <button class="rounded-md px-2 py-1 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">Tutup</button>
        </div>
    </form>
    <form wire:submit.prevent="storeDisaster" 
          enctype="multipart/form-data"
          class="flex flex-col max-h-[80vh]">
        <div class="overflow-y-auto p-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Judul <span class="text-red-500">*</span></label>
                    <input wire:model="title" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @error('title') border-red-500 @enderror" required />
                    @error('title') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Sumber <span class="text-red-500">*</span></label>
                    <select wire:model="source" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @error('source') border-red-500 @enderror" required>
                        <option value="bmkg">BMKG</option>
                        <option value="manual">Manual</option>
                    </select>
                    @error('source') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Jenis <span class="text-red-500">*</span></label>
                    <select wire:model="types" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @error('types') border-red-500 @enderror" required>
                        <option value="earthquake">Gempa Bumi</option>
                        <option value="tsunami">Tsunami</option>
                        <option value="volcanic_eruption">Gunung Meletus</option>
                        <option value="flood">Banjir</option>
                        <option value="drought">Kekeringan</option>
                        <option value="tornado">Angin Topan</option>
                        <option value="landslide">Tanah Longsor</option>
                        <option value="non_natural_disaster">Bencana Non Alam</option>
                        <option value="social_disaster">Bencana Sosial</option>
                    </select>
                    @error('types') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Status <span class="text-red-500">*</span></label>
                    <select wire:model="status" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @error('status') border-red-500 @enderror" required>
                        <option value="ongoing">Berlangsung</option>
                        <option value="completed">Selesai</option>
                    </select>
                    @error('status') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tanggal <span class="text-red-500">*</span></label>
                    <input wire:model="date" type="date" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @error('date') border-red-500 @enderror" required />
                    @error('date') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Waktu <span class="text-red-500">*</span></label>
                    <input wire:model="time" type="time" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @error('time') border-red-500 @enderror" required />
                    @error('time') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Lokasi</label>
                    <input wire:model="location" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Koordinat</label>
                    <input wire:model="coordinate" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Latitude</label>
                    <input wire:model="lat" type="number" step="any" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Longitude</label>
                    <input wire:model="long" type="number" step="any" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Magnitudo</label>
                    <input wire:model="magnitude" type="number" step="any" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Kedalaman</label>
                    <input wire:model="depth" type="number" step="any" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Deskripsi</label>
                <textarea wire:model="description" rows="4" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"></textarea>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Gambar (opsional)</label>
                <input type="file" 
                       wire:model="pictures" 
                       accept="image/*" 
                       id="picture-input-create"
                       multiple
                       onchange="
                           (function() {
                               const createModal = document.getElementById('disaster-create-modal');
                               if (createModal) {
                                   setTimeout(() => {
                                       if (!createModal.open) {
                                           createModal.showModal();
                                       }
                                   }, 100);
                               }
                           })();
                       "
                       class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Maksimal 4MB per gambar.</p>
                @error('pictures')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('pictures.*')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <div wire:loading wire:target="pictures,pictures.*" class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <span class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Mengunggah...
                    </span>
                </div>
                
                @if(!empty($pictures))
                <div class="mt-4">
                    <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Pratinjau Gambar:</p>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                        @foreach((is_array($pictures) ? $pictures : [$pictures]) as $idx => $file)
                            <div class="relative group">
                                @php
                                    $previewUrl = null;
                                    $fileName = '';
                                    try {
                                        if (is_object($file) && method_exists($file, 'temporaryUrl')) {
                                            $previewUrl = $file->temporaryUrl();
                                        }
                                        if (is_object($file) && method_exists($file, 'getClientOriginalName')) {
                                            $fileName = $file->getClientOriginalName();
                                        }
                                    } catch (\Exception $e) {
                                        // Silently fail
                                    }
                                @endphp
                                <div class="aspect-square overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                    @if($previewUrl)
                                        <img src="{{ $previewUrl }}" alt="Preview" class="h-full w-full object-cover" loading="lazy" />
                                    @else
                                        <div class="flex h-full items-center justify-center">
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Memuat...</span>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" 
                                        wire:click="removePicture({{ $idx }})"
                                        wire:loading.attr="disabled"
                                        class="absolute top-2 right-2 flex items-center justify-center rounded-full bg-red-500 p-1.5 text-white opacity-0 transition-opacity duration-200 hover:bg-red-600 group-hover:opacity-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                @if($fileName)
                                <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 truncate" title="{{ $fileName }}">{{ $fileName }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 bg-white px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900 sticky bottom-0 z-10">
            <button type="button" 
                    onclick="document.getElementById('disaster-create-modal').close()" 
                    wire:loading.attr="disabled"
                    wire:target="storeDisaster"
                    class="rounded-md px-3 py-2 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700 disabled:opacity-50">Batal</button>
            <button type="submit" 
                    wire:loading.attr="disabled"
                    wire:target="storeDisaster"
                    class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed relative z-20">
                <span wire:loading.remove wire:target="storeDisaster">Buat</span>
                <span wire:loading wire:target="storeDisaster" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Menyimpan...
                </span>
            </button>
        </div>
    </form>
</dialog>

<script>
    // Listen for successful form submission
    document.addEventListener('livewire:init', () => {
        // Check for success flash message after Livewire updates
        Livewire.hook('morph.updated', () => {
            const successAlert = document.querySelector('[role="alert"]');
            if (successAlert && successAlert.textContent.includes('Bencana berhasil dibuat')) {
                const modal = document.getElementById('disaster-create-modal');
                if (modal && modal.open) {
                    modal.close();
                }
                setTimeout(() => {
                    window.location.href = '{{ route("admin.disaster") }}';
                }, 500);
            }
        });
    });
</script>