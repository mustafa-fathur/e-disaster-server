<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Notification;
use App\Enums\NotificationTypeEnum;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public int $perPage = 10;
    public string $search = '';
    public ?string $category = null;
    public ?bool $is_read = null;

    public bool $confirmDeleteModal = false;
    public ?string $deletingId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => null],
        'is_read' => ['except' => null],
        'perPage' => ['except' => 10],
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingCategory() { $this->resetPage(); }
    public function updatingIsRead() { $this->resetPage(); }
    public function updatingPerPage() { $this->resetPage(); }

    public function with(): array
    {
        $user = Illuminate\Support\Facades\Auth::user();
        $query = Notification::query()->where('user_id', $user?->id);

        if ($this->search) {
            $query->where(function($q){
                $q->where('title', 'like', '%'.$this->search.'%')
                  ->orWhere('message', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->category) {
            $query->where('category', $this->category);
        }

        if (!is_null($this->is_read)) {
            $query->where('is_read', (bool)$this->is_read);
        }

        $query->orderByDesc('created_at');

        $notifications = $query->paginate($this->perPage);

        // Stats summary similar to API stats
        $total = Notification::where('user_id', $user?->id)->count();
        $unread = Notification::where('user_id', $user?->id)->where('is_read', false)->count();

        return compact('notifications', 'total', 'unread');
    }

    public function markRead(string $id): void
    {
        $user = Illuminate\Support\Facades\Auth::user();
        $notification = Notification::where('user_id', $user?->id)->whereKey($id)->first();
        if ($notification) {
            $notification->update(['is_read' => true]);
        }
        $this->dispatch('notification-updated');
    }

    public function markAllRead(): void
    {
        $user = Illuminate\Support\Facades\Auth::user();
        Notification::where('user_id', $user?->id)->where('is_read', false)->update(['is_read' => true]);
        $this->dispatch('notification-updated');
    }

    public function openDelete(string $id): void
    {
        $this->deletingId = $id;
        $this->confirmDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        $user = Illuminate\Support\Facades\Auth::user();
        if ($this->deletingId) {
            Notification::where('user_id', $user?->id)->whereKey($this->deletingId)->delete();
        }
        $this->reset(['confirmDeleteModal','deletingId']);
        $this->dispatch('notification-deleted');
    }

    public function deleteAllRead(): void
    {
        $user = Illuminate\Support\Facades\Auth::user();
        Notification::where('user_id', $user?->id)->where('is_read', true)->delete();
        $this->dispatch('notification-deleted');
    }
};
?>

<section class="w-full">
    <div class="relative mb-6">
        <flux:heading size="xl" level="1">{{ __('Notifikasi') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Pemberitahuan untuk akun Anda') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>
    <div class="flex items-center justify-between">
        <div class="flex gap-2">
            <flux:button variant="ghost" wire:click="markAllRead">{{ __('Tandai semua dibaca') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteAllRead">{{ __('Hapus semua yang sudah dibaca') }}</flux:button>
        </div>
    </div>

    <div class="mt-4 grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-3 md:grid-cols-4">
            <flux:input wire:model.debounce.400ms="search" :label="__('Cari')" placeholder="{{ __('Judul atau pesan...') }}" />
            <flux:select wire:model="category" :label="__('Kategori')">
                <option value="">—</option>
                <option value="volunteer_verification">{{ __('Verifikasi Relawan') }}</option>
                <option value="new_disaster">{{ __('Bencana Baru') }}</option>
                <option value="new_disaster_report">{{ __('Laporan Bencana Baru') }}</option>
                <option value="new_disaster_victim_report">{{ __('Laporan Korban Baru') }}</option>
                <option value="new_disaster_aid_report">{{ __('Laporan Bantuan Baru') }}</option>
                <option value="disaster_status_changed">{{ __('Status Bencana Berubah') }}</option>
            </flux:select>
            <flux:select wire:model="is_read" :label="__('Status Baca')">
                <option value="">—</option>
                <option value="0">{{ __('Belum dibaca') }}</option>
                <option value="1">{{ __('Sudah dibaca') }}</option>
            </flux:select>
            <flux:select wire:model="perPage" :label="__('Per Halaman')">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </flux:select>
        </div>

        <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
            <div>
                {{ __('Total:') }} <span class="font-semibold">{{ $total }}</span>
                · {{ __('Belum dibaca:') }} <span class="font-semibold">{{ $unread }}</span>
            </div>
        </div>
    </div>

    <div class="mt-4 space-y-4">
        @forelse($notifications as $notif)
            @php($catVal = is_string($notif->category) ? $notif->category : ($notif->category->value ?? null))
            @php($catLabel = match($catVal){
                'volunteer_verification' => __('Verifikasi Relawan'),
                'new_disaster' => __('Bencana Baru'),
                'new_disaster_report' => __('Laporan Bencana Baru'),
                'new_disaster_victim_report' => __('Laporan Korban Baru'),
                'new_disaster_aid_report' => __('Laporan Bantuan Baru'),
                'disaster_status_changed' => __('Status Bencana Berubah'),
                default => $catVal,
            })
            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h5 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $notif->title }}</h5>
                            @if(!$notif->is_read)
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">{{ __('Baru') }}</span>
                            @endif
                        </div>
                        @if($notif->message)
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $notif->message }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('Kategori:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $catLabel }}</span></span>
                            @if($notif->sent_at)
                                <span>{{ __('Dikirim:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $notif->sent_at->format('Y-m-d H:i') }}</span></span>
                            @endif
                            @if($notif->created_at)
                                <span>{{ __('Dibuat:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $notif->created_at->format('Y-m-d H:i') }}</span></span>
                            @endif
                        </div>
                    </div>

                    <div x-data="{ open: false }" class="relative shrink-0">
                        <button type="button" @click="open = !open" class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path d="M12 6.75a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                            </svg>
                        </button>
                        <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-10 mt-2 w-48 overflow-hidden rounded-md border border-zinc-200 bg-white text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @if(!$notif->is_read)
                                <a href="#" wire:click.prevent="markRead('{{ $notif->id }}')" class="block px-3 py-2 text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Tandai dibaca') }}</a>
                            @endif
                            <a href="#" wire:click.prevent="openDelete('{{ $notif->id }}')" class="block px-3 py-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">{{ __('Hapus') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('Tidak ada notifikasi.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notifications->links() }}
    </div>

    <flux:modal wire:model="confirmDeleteModal">
        <flux:heading>{{ __('Hapus Notifikasi') }}</flux:heading>
        <flux:subheading>{{ __('Tindakan ini tidak dapat dibatalkan.') }}</flux:subheading>
        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Apakah Anda yakin ingin menghapus notifikasi ini?') }}</p>
        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" @click="$wire.confirmDeleteModal = false">{{ __('Batal') }}</flux:button>
            <flux:button variant="danger" wire:click="confirmDelete" wire:loading.attr="disabled">{{ __('Hapus') }}</flux:button>
        </div>
    </flux:modal>
</section>