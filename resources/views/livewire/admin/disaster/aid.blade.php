<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use \App\Models\Disaster as DisasterModel;

new #[Layout('components.layouts.app')] class extends Component {
	public DisasterModel $disaster;

	public function mount(DisasterModel $disaster)
	{
		$this->disaster = $disaster->loadMissing('reporter');
	}
};
?>

<section class="w-full">
	@include('partials.disaster-heading')

	<x-disaster.layout :heading="__('Bantuan Bencana')" :subheading=" __('Kelola bantuan dan dukungan terkait bencana')" :disaster="$disaster">
		<div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
			<p class="text-sm text-zinc-600 dark:text-zinc-300">Placeholder bantuan: konten akan ditambahkan.</p>
		</div>
	</x-disaster.layout>
</section>
