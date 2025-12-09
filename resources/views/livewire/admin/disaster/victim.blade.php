<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use \App\Models\Disaster as DisasterModel;
use App\Models\DisasterVictim;
use App\Models\Picture;
use App\Enums\PictureTypeEnum;
use App\Enums\DisasterVictimStatusEnum;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
	public DisasterModel $disaster;
	use WithFileUploads;

	public bool $showCreate = false;
	public bool $showEdit = false;
	public bool $showDelete = false;
	public ?string $editingId = null;
	public ?string $deletingId = null;

	public string $nik = '';
	public string $name = '';
	public ?string $date_of_birth = null;
	public ?bool $gender = null; // true male, false female
	public string $contact_info = '';
	public string $description = '';
	public bool $is_evacuated = false;
	public string $status = 'minor_injury';
	public array $pictures = [];

	public array $editPictures = [];
	public string $editNik = '';
	public string $editName = '';
	public ?string $editDateOfBirth = null;
	public ?bool $editGender = null;
	public string $editContactInfo = '';
	public string $editDescription = '';
	public bool $editIsEvacuated = false;
	public string $editStatus = 'minor_injury';

	public function mount(DisasterModel $disaster)
	{
		$this->disaster = $disaster->loadMissing('reporter');
	}

	public function with(): array
	{
		$victims = DisasterVictim::query()
			->where('disaster_id', $this->disaster->id)
			->with([
				'reporter.user',
				'pictures' => function ($q) {
					$q->where('type', PictureTypeEnum::DISASTER_VICTIM->value);
				},
			])
			->latest()
			->get();

		return compact('victims');
	}

	public function toggleCreate(bool $state = true): void
	{
		$this->showCreate = $state;
	}

	public function saveVictim(): void
	{
		$validated = $this->validate([
			'nik' => ['nullable', 'string', 'max:32'],
			'name' => ['required', 'string', 'max:255'],
			'date_of_birth' => ['nullable', 'date'],
			'gender' => ['nullable', 'boolean'],
			'contact_info' => ['nullable', 'string', 'max:255'],
			'description' => ['nullable', 'string'],
			'is_evacuated' => ['boolean'],
			'status' => ['required', 'string', 'in:minor_injury,serious_injuries,lost,deceased'],
			'pictures' => ['nullable', 'array'],
			'pictures.*' => ['image', 'max:4096'],
		]);

		$volunteer = \App\Models\DisasterVolunteer::query()
			->where('disaster_id', $this->disaster->id)
			->where('user_id', Auth::id())
			->first();

		$victim = DisasterVictim::create([
			'disaster_id' => $this->disaster->id,
			'reported_by' => $volunteer?->id,
			'nik' => $validated['nik'] ?? null,
			'name' => $validated['name'],
			'date_of_birth' => $validated['date_of_birth'] ?? null,
			'gender' => $validated['gender'] ?? null,
			'contact_info' => $validated['contact_info'] ?? null,
			'description' => $validated['description'] ?? null,
			'is_evacuated' => (bool)($validated['is_evacuated'] ?? false),
			'status' => $validated['status'],
		]);

		$files = [];
		if ($this->pictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
			$files = [$this->pictures];
		} elseif (is_array($this->pictures)) {
			$files = $this->pictures;
		}
		foreach ($files as $file) {
			$storedPath = $file->store('pictures/victim', 'public');
			Picture::create([
				'foreign_id' => $victim->id,
				'type' => PictureTypeEnum::DISASTER_VICTIM->value,
				'file_path' => $storedPath,
				'mine_type' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
			]);
		}

		$this->reset(['nik','name','date_of_birth','gender','contact_info','description','is_evacuated','status','pictures']);
		$this->showCreate = false;
		$this->dispatch('victim-created');
	}

	public function openEdit(string $id): void
	{
		$victim = DisasterVictim::query()->whereKey($id)->first();
		if (!$victim) { return; }

		$this->editingId = $victim->id;
		$this->editNik = $victim->nik ?? '';
		$this->editName = $victim->name ?? '';
		$this->editDateOfBirth = optional($victim->date_of_birth)->format('Y-m-d');
		$this->editGender = $victim->gender;
		$this->editContactInfo = $victim->contact_info ?? '';
		$this->editDescription = $victim->description ?? '';
		$this->editIsEvacuated = (bool)$victim->is_evacuated;
		$this->editStatus = is_string($victim->status) ? $victim->status : ($victim->status->value ?? 'minor_injury');
		$this->editPictures = [];
		$this->showEdit = true;
	}

	public function saveEdit(): void
	{
		$victim = DisasterVictim::query()->whereKey($this->editingId)->first();
		if (!$victim) { return; }

		$validated = $this->validate([
			'editNik' => ['nullable', 'string', 'max:32'],
			'editName' => ['required', 'string', 'max:255'],
			'editDateOfBirth' => ['nullable', 'date'],
			'editGender' => ['nullable', 'boolean'],
			'editContactInfo' => ['nullable', 'string', 'max:255'],
			'editDescription' => ['nullable', 'string'],
			'editIsEvacuated' => ['boolean'],
			'editStatus' => ['required', 'string', 'in:minor_injury,serious_injuries,lost,deceased'],
			'editPictures' => ['nullable', 'array'],
			'editPictures.*' => ['image', 'max:4096'],
		]);

		$victim->update([
			'nik' => $validated['editNik'] ?? null,
			'name' => $validated['editName'],
			'date_of_birth' => $validated['editDateOfBirth'] ?? null,
			'gender' => $validated['editGender'] ?? null,
			'contact_info' => $validated['editContactInfo'] ?? null,
			'description' => $validated['editDescription'] ?? null,
			'is_evacuated' => (bool)($validated['editIsEvacuated'] ?? false),
			'status' => $validated['editStatus'],
		]);

		$files = [];
		if ($this->editPictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
			$files = [$this->editPictures];
		} elseif (is_array($this->editPictures)) {
			$files = $this->editPictures;
		}
		foreach ($files as $file) {
			$storedPath = $file->store('pictures/victim', 'public');
			Picture::create([
				'foreign_id' => $victim->id,
				'type' => PictureTypeEnum::DISASTER_VICTIM->value,
				'file_path' => $storedPath,
				'mine_type' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
			]);
		}

		$this->reset(['showEdit','editingId','editNik','editName','editDateOfBirth','editGender','editContactInfo','editDescription','editIsEvacuated','editStatus','editPictures']);
		$this->dispatch('victim-updated');
	}

	public function openDelete(string $id): void
	{
		$this->deletingId = $id;
		$this->showDelete = true;
	}

	public function confirmDelete(): void
	{
		if (!$this->deletingId) { return; }
		$victim = DisasterVictim::query()->whereKey($this->deletingId)->first();
		if ($victim) {
			foreach ($victim->pictures as $pic) {
				if ($pic->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($pic->file_path)) {
					\Illuminate\Support\Facades\Storage::disk('public')->delete($pic->file_path);
				}
				$pic->delete();
			}
			$victim->delete();
		}
		$this->reset(['showDelete','deletingId']);
		$this->dispatch('victim-deleted');
	}
};
?>

<section class="w-full">
	@include('partials.disaster-heading')

	<x-disaster.layout :heading="__('Korban Bencana')" :subheading=" __('Kelola data korban terkait bencana')" :disaster="$disaster">
		<x-slot:actions>
			<flux:button variant="primary" wire:click="toggleCreate(true)">{{ __('Tambah Korban') }}</flux:button>
		</x-slot:actions>

		@if(isset($victims) && $victims->isNotEmpty())
			<div class="space-y-4">
				@foreach($victims as $victim)
					<div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
						<div class="flex items-start justify-between gap-4">
							<div class="min-w-0">
								<h5 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $victim->name }}</h5>
								@if($victim->description)
									<p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ Str::limit($victim->description, 160) }}</p>
								@endif
							</div>
							<div x-data="{ open: false }" class="relative shrink-0">
								<button type="button" @click="open = !open" class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
										<path d="M12 6.75a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
									</svg>
								</button>
								<div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-md border border-zinc-200 bg-white text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
									<a href="#" wire:click.prevent="openEdit('{{ $victim->id }}')" class="block px-3 py-2 text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Edit') }}</a>
									<a href="#" wire:click.prevent="openDelete('{{ $victim->id }}')" class="block px-3 py-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">{{ __('Delete') }}</a>
								</div>
							</div>
						</div>

						<div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
							@php($reporterName = $victim->reporter?->user?->name)
							<span>{{ __('Pelapor:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $reporterName ?: '—' }}</span></span>
							@if($victim->created_at)
								<span>{{ __('Dibuat:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $victim->created_at->format('Y-m-d H:i') }}</span></span>
							@endif
							@if($victim->nik)
								<span>{{ __('NIK:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $victim->nik }}</span></span>
							@endif
							@if($victim->date_of_birth)
								<span>{{ __('Tanggal Lahir:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ optional($victim->date_of_birth)->format('Y-m-d') }}</span></span>
							@endif
							@if(!is_null($victim->gender))
								<span>{{ __('Jenis Kelamin:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $victim->gender ? 'Laki-laki' : 'Perempuan' }}</span></span>
							@endif
							@php($statusVal = is_string($victim->status) ? $victim->status : ($victim->status->value ?? null))
							@if($statusVal)
								@php($statusLabel = match($statusVal){
									'minor_injury' => __('Luka Ringan'),
									'serious_injuries' => __('Luka Berat'),
									'lost' => __('Hilang'),
									'deceased' => __('Meninggal'),
									default => $statusVal,
								})
								<span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
									{{ $statusLabel }}
								</span>
							@endif
							@if($victim->is_evacuated)
								<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">{{ __('Dievakuasi') }}</span>
							@endif
						</div>

						@if($victim->pictures && $victim->pictures->isNotEmpty())
							<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
								@foreach($victim->pictures as $picture)
									<div class="relative group overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
										<a href="{{ asset('storage/' . ltrim($picture->file_path, '/')) }}" target="_blank" rel="noopener noreferrer">
											<img
												src="{{ asset('storage/' . ltrim($picture->file_path, '/')) }}"
												alt="{{ $picture->alt_text ?: ($picture->caption ?: 'Victim Photo') }}"
												class="h-28 w-full object-cover transition group-hover:scale-[1.02]"
												loading="lazy"
											/>
										</a>
										@if($picture->caption)
											<div class="p-2 text-[11px] text-zinc-600 dark:text-zinc-300">
												{{ $picture->caption }}
											</div>
										@endif
									</div>
								@endforeach
							</div>
						@endif
					</div>
				@endforeach
			</div>
		@else
			<div class="rounded-lg border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
				{{ __('Belum ada data korban untuk bencana ini.') }}
			</div>
		@endif

		<flux:modal wire:model="showCreate">
			<flux:heading>{{ __('Tambah Korban') }}</flux:heading>
			<flux:subheading>{{ __('Isi rincian korban bencana') }}</flux:subheading>

			<div class="mt-4 space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<flux:input wire:model="nik" :label="__('NIK')" />
					<flux:input wire:model="name" :label="__('Nama')" required />
				</div>
				<div class="grid grid-cols-2 gap-4">
					<flux:input wire:model="date_of_birth" :label="__('Tanggal Lahir')" type="date" />
					<flux:select wire:model="gender" :label="__('Jenis Kelamin')">
						<option value="">—</option>
						<option value="1">{{ __('Laki-laki') }}</option>
						<option value="0">{{ __('Perempuan') }}</option>
					</flux:select>
				</div>
				<flux:input wire:model="contact_info" :label="__('Kontak')" />
				<flux:textarea wire:model="description" :label="__('Deskripsi')" rows="3" />
					<flux:select wire:model="status" :label="__('Status')">
						<option value="minor_injury">{{ __('Luka Ringan') }}</option>
						<option value="serious_injuries">{{ __('Luka Berat') }}</option>
						<option value="lost">{{ __('Hilang') }}</option>
						<option value="deceased">{{ __('Meninggal') }}</option>
					</flux:select>
                <flux:checkbox wire:model="is_evacuated" :label="__('Sudah Dievakuasi')"/>
				<div>
					<flux:input type="file" multiple wire:model="pictures" :label="__('Foto Korban (maks 4MB per file)')" />
					<div class="mt-2 grid grid-cols-3 gap-2" wire:loading.class="opacity-50" wire:target="pictures,pictures.*">
						@foreach((array) $pictures as $idx => $file)
							<div class="rounded border border-zinc-200 p-1 text-xs dark:border-zinc-700">
								{{ is_string($file) ? $file : ($file->getClientOriginalName() ?? 'file') }}
							</div>
						@endforeach
					</div>
				</div>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<flux:button variant="ghost" wire:click="toggleCreate(false)">{{ __('Batal') }}</flux:button>
				<flux:button variant="primary" wire:click="saveVictim" wire:loading.attr="disabled" wire:target="saveVictim,pictures,pictures.*">{{ __('Simpan') }}</flux:button>
			</div>
		</flux:modal>

		<flux:modal wire:model="showEdit">
			<flux:heading>{{ __('Edit Korban') }}</flux:heading>
			<flux:subheading>{{ __('Perbarui rincian korban bencana') }}</flux:subheading>

			<div class="mt-4 space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<flux:input wire:model="editNik" :label="__('NIK')" />
					<flux:input wire:model="editName" :label="__('Nama')" required />
				</div>
				<div class="grid grid-cols-2 gap-4">
					<flux:input wire:model="editDateOfBirth" :label="__('Tanggal Lahir')" type="date" />
					<flux:select wire:model="editGender" :label="__('Jenis Kelamin')">
						<option value="">—</option>
						<option value="1">{{ __('Laki-laki') }}</option>
						<option value="0">{{ __('Perempuan') }}</option>
					</flux:select>
				</div>
				<flux:input wire:model="editContactInfo" :label="__('Kontak')" />
				<flux:textarea wire:model="editDescription" :label="__('Deskripsi')" rows="3" />
				<flux:textarea wire:model="description" :label="__('Deskripsi')" rows="3" />
					<flux:select wire:model="status" :label="__('Status')">
						<option value="minor_injury">{{ __('Luka Ringan') }}</option>
						<option value="serious_injuries">{{ __('Luka Berat') }}</option>
						<option value="lost">{{ __('Hilang') }}</option>
						<option value="deceased">{{ __('Meninggal') }}</option>
					</flux:select>
                <flux:checkbox wire:model="is_evacuated" :label="__('Sudah Dievakuasi')"/>
				<div>
					<flux:input type="file" multiple wire:model="editPictures" :label="__('Tambah Foto (opsional)')" />
					<div class="mt-2 grid grid-cols-3 gap-2" wire:loading.class="opacity-50" wire:target="editPictures,editPictures.*">
						@foreach((array) $editPictures as $idx => $file)
							<div class="rounded border border-zinc-200 p-1 text-xs dark:border-zinc-700">
								{{ is_string($file) ? $file : ($file->getClientOriginalName() ?? 'file') }}
							</div>
						@endforeach
					</div>
				</div>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<flux:button variant="ghost" @click="$wire.showEdit = false">{{ __('Batal') }}</flux:button>
				<flux:button variant="primary" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit,editPictures,editPictures.*">{{ __('Simpan') }}</flux:button>
			</div>
		</flux:modal>

		<flux:modal wire:model="showDelete">
			<flux:heading>{{ __('Hapus Korban') }}</flux:heading>
			<flux:subheading>{{ __('Tindakan ini tidak dapat dibatalkan.') }}</flux:subheading>

			<p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Apakah Anda yakin ingin menghapus data korban ini beserta semua fotonya?') }}</p>

			<div class="mt-6 flex justify-end gap-2">
				<flux:button variant="ghost" @click="$wire.showDelete = false">{{ __('Batal') }}</flux:button>
				<flux:button variant="danger" wire:click="confirmDelete" wire:loading.attr="disabled">{{ __('Hapus') }}</flux:button>
			</div>
		</flux:modal>
	</x-disaster.layout>
</section>
