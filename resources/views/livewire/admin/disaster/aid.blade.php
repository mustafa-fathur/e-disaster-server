<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use \App\Models\Disaster as DisasterModel;
use App\Models\DisasterAid;
use App\Models\Picture;
use App\Enums\PictureTypeEnum;
use App\Enums\DisasterAidCategoryEnum;
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

	public string $title = '';
	public string $description = '';
	public string $category = 'food';
	public $quantity = null; // allow int/float
	public string $unit = '';
	public array $pictures = [];

	public string $editTitle = '';
	public string $editDescription = '';
	public string $editCategory = 'food';
	public $editQuantity = null;
	public string $editUnit = '';
	public array $editPictures = [];

	public function mount(DisasterModel $disaster)
	{
		$this->disaster = $disaster->loadMissing('reporter');
	}

	public function with(): array
	{
		$aids = DisasterAid::query()
			->where('disaster_id', $this->disaster->id)
			->with([
				'reporter.user',
				'pictures' => function ($q) {
					$q->where('type', PictureTypeEnum::DISASTER_AID->value);
				},
			])
			->latest()
			->get();

		return compact('aids');
	}

	public function toggleCreate(bool $state = true): void
	{
		$this->showCreate = $state;
	}

	public function saveAid(): void
	{
		$validated = $this->validate([
			'title' => ['required', 'string', 'max:255'],
			'description' => ['nullable', 'string'],
			'category' => ['required', 'string', 'in:food,clothing,housing'],
			'quantity' => ['nullable', 'numeric', 'min:0'],
			'unit' => ['nullable', 'string', 'max:50'],
			'pictures' => ['nullable', 'array'],
			'pictures.*' => ['image', 'max:4096'],
		]);

		$volunteer = \App\Models\DisasterVolunteer::query()
			->where('disaster_id', $this->disaster->id)
			->where('user_id', Auth::id())
			->first();

		$aid = DisasterAid::create([
			'disaster_id' => $this->disaster->id,
			'reported_by' => $volunteer?->id,
			'title' => $validated['title'],
			'description' => $validated['description'] ?? null,
			'category' => $validated['category'],
			'quantity' => $validated['quantity'] ?? null,
			'unit' => $validated['unit'] ?? null,
		]);

		$files = [];
		if ($this->pictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
			$files = [$this->pictures];
		} elseif (is_array($this->pictures)) {
			$files = $this->pictures;
		}
		foreach ($files as $file) {
			$storedPath = $file->store('pictures/aid', 'public');
			Picture::create([
				'foreign_id' => $aid->id,
				'type' => PictureTypeEnum::DISASTER_AID->value,
				'file_path' => $storedPath,
				'mine_type' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
			]);
		}

		$this->reset(['title','description','category','quantity','unit','pictures']);
		$this->showCreate = false;
		$this->dispatch('aid-created');
	}

	public function openEdit(string $id): void
	{
		$aid = DisasterAid::query()->whereKey($id)->first();
		if (!$aid) { return; }
		$this->editingId = $aid->id;
		$this->editTitle = $aid->title ?? '';
		$this->editDescription = $aid->description ?? '';
		$this->editCategory = is_string($aid->category) ? $aid->category : ($aid->category->value ?? 'food');
		$this->editQuantity = $aid->quantity;
		$this->editUnit = $aid->unit ?? '';
		$this->editPictures = [];
		$this->showEdit = true;
	}

	public function saveEdit(): void
	{
		$aid = DisasterAid::query()->whereKey($this->editingId)->first();
		if (!$aid) { return; }

		$validated = $this->validate([
			'editTitle' => ['required', 'string', 'max:255'],
			'editDescription' => ['nullable', 'string'],
			'editCategory' => ['required', 'string', 'in:food,clothing,housing'],
			'editQuantity' => ['nullable', 'numeric', 'min:0'],
			'editUnit' => ['nullable', 'string', 'max:50'],
			'editPictures' => ['nullable', 'array'],
			'editPictures.*' => ['image', 'max:4096'],
		]);

		$aid->update([
			'title' => $validated['editTitle'],
			'description' => $validated['editDescription'] ?? null,
			'category' => $validated['editCategory'],
			'quantity' => $validated['editQuantity'] ?? null,
			'unit' => $validated['editUnit'] ?? null,
		]);

		$files = [];
		if ($this->editPictures instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
			$files = [$this->editPictures];
		} elseif (is_array($this->editPictures)) {
			$files = $this->editPictures;
		}
		foreach ($files as $file) {
			$storedPath = $file->store('pictures/aid', 'public');
			Picture::create([
				'foreign_id' => $aid->id,
				'type' => PictureTypeEnum::DISASTER_AID->value,
				'file_path' => $storedPath,
				'mine_type' => method_exists($file, 'getMimeType') ? $file->getMimeType() : null,
			]);
		}

		$this->reset(['showEdit','editingId','editTitle','editDescription','editCategory','editQuantity','editUnit','editPictures']);
		$this->dispatch('aid-updated');
	}

	public function openDelete(string $id): void
	{
		$this->deletingId = $id;
		$this->showDelete = true;
	}

	public function confirmDelete(): void
	{
		if (!$this->deletingId) { return; }
		$aid = DisasterAid::query()->whereKey($this->deletingId)->first();
		if ($aid) {
			foreach ($aid->pictures as $pic) {
				if ($pic->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($pic->file_path)) {
					\Illuminate\Support\Facades\Storage::disk('public')->delete($pic->file_path);
				}
				$pic->delete();
			}
			$aid->delete();
		}
		$this->reset(['showDelete','deletingId']);
		$this->dispatch('aid-deleted');
	}
};
?>

<section class="w-full">
	@include('partials.disaster-heading')

	<x-disaster.layout :heading="__('Bantuan Bencana')" :subheading=" __('Kelola bantuan dan dukungan terkait bencana')" :disaster="$disaster">
		<x-slot:actions>
			<flux:button variant="primary" wire:click="toggleCreate(true)">{{ __('Tambah Bantuan') }}</flux:button>
		</x-slot:actions>

		@if(isset($aids) && $aids->isNotEmpty())
			<div class="space-y-4">
				@foreach($aids as $aid)
					<div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
						<div class="flex items-start justify-between gap-4">
							<div class="min-w-0">
								<h5 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $aid->title }}</h5>
								@if($aid->description)
									<p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ Str::limit($aid->description, 160) }}</p>
								@endif
							</div>
							<div x-data="{ open: false }" class="relative shrink-0">
								<button type="button" @click="open = !open" class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
										<path d="M12 6.75a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
									</svg>
								</button>
								<div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-md border border-zinc-200 bg-white text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
									<a href="#" wire:click.prevent="openEdit('{{ $aid->id }}')" class="block px-3 py-2 text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Edit') }}</a>
									<a href="#" wire:click.prevent="openDelete('{{ $aid->id }}')" class="block px-3 py-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">{{ __('Delete') }}</a>
								</div>
							</div>
						</div>

						<div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
							@php($reporterName = $aid->reporter?->user?->name)
							<span>{{ __('Pelapor:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $reporterName ?: '—' }}</span></span>
							@if($aid->created_at)
								<span>{{ __('Dibuat:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $aid->created_at->format('Y-m-d H:i') }}</span></span>
							@endif

							@php($catVal = is_string($aid->category) ? $aid->category : ($aid->category->value ?? null))
							@if($catVal)
								@php($catLabel = match($catVal){
									'food' => __('Makanan'),
									'clothing' => __('Pakaian'),
									'housing' => __('Perumahan'),
									default => $catVal,
								})
								<span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $catLabel }}</span>
							@endif

							@if(!is_null($aid->quantity) || $aid->unit)
								<span>{{ __('Jumlah:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $aid->quantity ?? '—' }} {{ $aid->unit }}</span></span>
							@endif
						</div>

						@if($aid->pictures && $aid->pictures->isNotEmpty())
							<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
								@foreach($aid->pictures as $picture)
									<div class="relative group overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
										<a href="{{ asset('storage/' . ltrim($picture->file_path, '/')) }}" target="_blank" rel="noopener noreferrer">
											<img
												src="{{ asset('storage/' . ltrim($picture->file_path, '/')) }}"
												alt="{{ $picture->alt_text ?: ($picture->caption ?: 'Aid Photo') }}"
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
				{{ __('Belum ada data bantuan untuk bencana ini.') }}
			</div>
		@endif

		<flux:modal wire:model="showCreate">
			<flux:heading>{{ __('Tambah Bantuan') }}</flux:heading>
			<flux:subheading>{{ __('Isi rincian bantuan bencana') }}</flux:subheading>

			<div class="mt-4 space-y-4">
				<flux:input wire:model="title" :label="__('Judul')" required />
				<flux:textarea wire:model="description" :label="__('Deskripsi')" rows="4" />
				<div class="grid grid-cols-2 gap-4">
					<flux:select wire:model="category" :label="__('Kategori')">
						<option value="food">{{ __('Makanan') }}</option>
						<option value="clothing">{{ __('Pakaian') }}</option>
						<option value="housing">{{ __('Perumahan') }}</option>
					</flux:select>
					<div class="grid grid-cols-2 gap-4">
						<flux:input wire:model="quantity" :label="__('Jumlah')" type="number" step="any" min="0" />
						<flux:input wire:model="unit" :label="__('Satuan')" />
					</div>
				</div>
				<div>
					<flux:input type="file" multiple wire:model="pictures" :label="__('Foto Bantuan (maks 4MB per file)')" />
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
				<flux:button variant="primary" wire:click="saveAid" wire:loading.attr="disabled" wire:target="saveAid,pictures,pictures.*">{{ __('Simpan') }}</flux:button>
			</div>
		</flux:modal>

		<flux:modal wire:model="showEdit">
			<flux:heading>{{ __('Edit Bantuan') }}</flux:heading>
			<flux:subheading>{{ __('Perbarui rincian bantuan bencana') }}</flux:subheading>

			<div class="mt-4 space-y-4">
				<flux:input wire:model="editTitle" :label="__('Judul')" required />
				<flux:textarea wire:model="editDescription" :label="__('Deskripsi')" rows="4" />
				<div class="grid grid-cols-2 gap-4">
					<flux:select wire:model="editCategory" :label="__('Kategori')">
						<option value="food">{{ __('Makanan') }}</option>
						<option value="clothing">{{ __('Pakaian') }}</option>
						<option value="housing">{{ __('Perumahan') }}</option>
					</flux:select>
					<div class="grid grid-cols-2 gap-4">
						<flux:input wire:model="editQuantity" :label="__('Jumlah')" type="number" step="any" min="0" />
						<flux:input wire:model="editUnit" :label="__('Satuan')" />
					</div>
				</div>
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
			<flux:heading>{{ __('Hapus Bantuan') }}</flux:heading>
			<flux:subheading>{{ __('Tindakan ini tidak dapat dibatalkan.') }}</flux:subheading>

			<p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Apakah Anda yakin ingin menghapus data bantuan ini beserta semua fotonya?') }}</p>

			<div class="mt-6 flex justify-end gap-2">
				<flux:button variant="ghost" @click="$wire.showDelete = false">{{ __('Batal') }}</flux:button>
				<flux:button variant="danger" wire:click="confirmDelete" wire:loading.attr="disabled">{{ __('Hapus') }}</flux:button>
			</div>
		</flux:modal>
	</x-disaster.layout>
</section>
