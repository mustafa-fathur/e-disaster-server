<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item :href="route('admin.disaster.identity', $disaster)" wire:navigate>{{ __('Identitas') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.disaster.report', $disaster)" wire:navigate>{{ __('Laporan') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.disaster.victim', $disaster)" wire:navigate>{{ __('Korban') }}</flux:navlist.item>
            <flux:navlist.item :href="route('admin.disaster.aid', $disaster)" wire:navigate>{{ __('Bantuan') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</div>