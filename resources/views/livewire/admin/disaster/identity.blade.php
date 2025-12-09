<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use \App\Models\Disaster as DisasterModel;
use App\Models\Picture;
use App\Enums\PictureTypeEnum;

new #[Layout('components.layouts.app')] class extends Component {
	public DisasterModel $disaster;

	public function mount(DisasterModel $disaster)
	{
		// Reporter is a direct relation to User (via reported_by)
		$this->disaster = $disaster->loadMissing(['reporter']);
	}

	public function with(): array
	{
		$pictures = Picture::query()
			->where('foreign_id', $this->disaster->id)
			->where('type', PictureTypeEnum::DISASTER->value)
			->get();

		return compact('pictures');
	}
};
?>

<section class="w-full">
	@include('partials.disaster-heading')

	<x-disaster.layout :heading="__('Identitas Bencana')" :subheading=" __('Kelola rincian identitas bencana')" :disaster="$disaster">
		@php
			$typeVal = is_string($disaster->types) ? $disaster->types : ($disaster->types->value ?? null);
			$typeLabel = match($typeVal) {
				'earthquake' => __('Gempa Bumi'),
				'tsunami' => __('Tsunami'),
				'volcanic_eruption' => __('Letusan Gunung Api'),
				'flood' => __('Banjir'),
				'drought' => __('Kekeringan'),
				'tornado' => __('Angin Puting Beliung'),
				'landslide' => __('Tanah Longsor'),
				'non_natural_disaster' => __('Bencana Non-Alam'),
				'social_disaster' => __('Bencana Sosial'),
				default => $typeVal,
			};

			$statusVal = is_string($disaster->status) ? $disaster->status : ($disaster->status->value ?? null);
			$statusLabel = match($statusVal) {
				'cancelled' => __('Dibatalkan'),
				'ongoing' => __('Berlangsung'),
				'completed' => __('Selesai'),
				default => $statusVal,
			};

			$sourceVal = is_string($disaster->source) ? $disaster->source : ($disaster->source->value ?? null);
			$sourceLabel = match($sourceVal) {
				'bmkg' => __('BMKG'),
				'manual' => __('Manual'),
				default => $sourceVal,
			};
		@endphp
		<div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
			<h3 class="mb-4 text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $disaster->title }}</h3>
			<div class="grid gap-4 md:grid-cols-2">
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Jenis</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $typeLabel }}</p>
				</div>
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Status</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $statusLabel }}</p>
				</div>
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Sumber</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $sourceLabel }}</p>
				</div>
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Tanggal</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ optional($disaster->date)->format('Y-m-d') }}</p>
				</div>
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Waktu</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ optional($disaster->time)->format('H:i') }}</p>
				</div>
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Lokasi</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->location }}</p>
				</div>
				<div>
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Pelapor</p>
					<p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->reporter?->name ?? '—' }}</p>
				</div>
			</div>
			@if($disaster->description)
				<div class="mt-4">
					<p class="text-xs text-zinc-500 dark:text-zinc-400">Deskripsi</p>
					<p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $disaster->description }}</p>
				</div>
			@endif
		</div>

		<div class="mt-6">
			<flux:heading>{{ __('Foto Bencana') }}</flux:heading>
			<flux:subheading>{{ __('Kumpulan foto terkait bencana ini') }}</flux:subheading>
		</div>

		<div class="mt-3 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
			@if(isset($pictures) && $pictures->isNotEmpty())
				<div class="grid grid-cols-2 gap-4 md:grid-cols-3">
					@foreach($pictures as $picture)
						<div class="relative group overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
							<a href="{{ asset('storage/' . ltrim($picture->file_path, '/')) }}" target="_blank" rel="noopener noreferrer">
								<img
									src="{{ asset('storage/' . ltrim($picture->file_path, '/')) }}"
									alt="{{ $picture->alt_text ?: ($picture->caption ?: 'Disaster Photo') }}"
									class="h-36 w-full object-cover transition group-hover:scale-[1.02]"
									loading="lazy"
								/>
							</a>
							@if($picture->caption)
								<div class="p-2 text-xs text-zinc-600 dark:text-zinc-300">
									{{ $picture->caption }}
								</div>
							@endif
						</div>
					@endforeach
				</div>
			@else
				<p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Belum ada foto bencana.') }}</p>
			@endif
		</div>
	</x-disaster.layout>
</section>
