<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Storage;
use \App\Models\Disaster as DisasterModel;
use App\Models\Picture;
use App\Enums\PictureTypeEnum;

new #[Layout('components.layouts.app')] class extends Component {
    public DisasterModel $disaster;
    public bool $disasterNotFound = false;

    // Declare actions / data providers at top and call below in template
    public function mount($disaster)
    {
        if ($disaster instanceof DisasterModel) {
            $this->disaster = $disaster->loadMissing('reporter');
            $this->disasterNotFound = false;
            return;
        }
        // Fallback when binding didn't pass a model (e.g., UUID string)
        $model = DisasterModel::find($disaster);
        if (!$model) {
            $this->disasterNotFound = true;
            return;
        }
        $this->disaster = $model->loadMissing('reporter');
        $this->disasterNotFound = false;
    }

    public function with(): array
    {
        $pictures = Picture::where('foreign_id', $this->disaster->id)
            ->where('type', PictureTypeEnum::DISASTER)
            ->get();
        return compact('pictures');
    }
};
?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Detail Bencana') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Informasi lengkap bencana') }}</flux:subheading>
        <flux:separator variant="subtle" />
        <div class="mt-4">
            <a href="{{ route('admin.disaster') }}"
               class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">
                Kembali ke Daftar
            </a>
        </div>
    </div>

    @if($disasterNotFound)
        <div class="rounded-lg border border-red-300 bg-red-50 p-6 text-red-800 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300">
            <p class="text-sm">Data bencana tidak ditemukan.</p>
            <a href="{{ route('admin.disaster') }}" class="mt-3 inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white">Kembali ke daftar</a>
        </div>
        @php return; @endphp
    @endif

    <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 md:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $disaster->title }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Jenis</p>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->types->value ?? $disaster->types }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Status</p>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->status->value ?? $disaster->status }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Sumber</p>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->source->value ?? $disaster->source }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Tanggal</p>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ optional($disaster->date)->format('d M Y') ?? $disaster->date }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Waktu</p>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ optional($disaster->time)->format('H:i') ?? $disaster->time }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Lokasi</p>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->location ?? '—' }}</p>
                </div>
            </div>
            @if($disaster->description)
                <div class="mt-4">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Deskripsi</p>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $disaster->description }}</p>
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h4 class="mb-3 text-base font-semibold text-zinc-900 dark:text-zinc-100">Teknis</h4>
            <div class="space-y-2">
                @if($disaster->magnitude)
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">Magnitudo</span>
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->magnitude }}</span>
                </div>
                @endif
                @if($disaster->depth)
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">Kedalaman</span>
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->depth }} km</span>
                </div>
                @endif
                @if($disaster->lat && $disaster->long)
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">Koordinat</span>
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($disaster->lat, 6) }}, {{ number_format($disaster->long, 6) }}</span>
                </div>
                @endif
                @if($disaster->coordinate)
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">Koordinat (String)</span>
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $disaster->coordinate }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <h4 class="mb-3 text-base font-semibold text-zinc-900 dark:text-zinc-100">Gambar</h4>
        @if($pictures->isNotEmpty())
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            @foreach($pictures as $picture)
                <div class="relative group">
                    <img src="{{ Storage::url($picture->file_path) }}" alt="{{ $picture->alt_text ?? 'Gambar bencana' }}" class="w-full h-32 object-cover rounded-lg border border-zinc-200 dark:border-zinc-700" />
                    @if($picture->caption)
                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">{{ $picture->caption }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        @else
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada gambar.</p>
        @endif
    </div>
</section>
