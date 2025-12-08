<?php

use App\Models\User;
use App\Models\Picture;
use App\Enums\PictureTypeEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public ?string $currentProfileUrl = null;

    public $profile_image; // TemporaryUploadedFile via WithFileUploads
    public string $caption = '';
    public string $alt_text = '';

    use WithFileUploads;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;

        // Load current profile picture URL for preview
        $existing = Picture::query()
            ->where('foreign_id', Auth::id())
            ->where('type', PictureTypeEnum::PROFILE->value)
            ->first();

        // Resolve URL via public storage symlink
        $this->currentProfileUrl = $existing ? asset('storage/' . ltrim($existing->file_path, '/')) : null;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Update the user's profile picture.
     */
    public function updateProfilePicture(): void
    {
        $this->validate([
            'profile_image' => ['required', 'image', 'max:4096'], // max 4MB
            'caption' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        // Remove existing profile picture file and record, if any
        $existing = Picture::query()
            ->where('foreign_id', $user->id)
            ->where('type', PictureTypeEnum::PROFILE->value)
            ->first();

        if ($existing) {
            // Delete from the same disk where we store (public)
            if ($existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $existing->delete();
        }

        // Store new image
        $storedPath = $this->profile_image->store('pictures/profile', 'public');

        // Create new picture record
        Picture::create([
            'foreign_id' => $user->id,
            'type' => PictureTypeEnum::PROFILE->value,
            'file_path' => $storedPath,
            'caption' => $this->caption ?: null,
            'alt_text' => $this->alt_text ?: null,
        ]);

        // Refresh preview URL and reset upload state
        $this->currentProfileUrl = asset('storage/' . ltrim($storedPath, '/'));
        $this->reset('profile_image');

        $this->dispatch('profile-picture-updated');
    }

    /**
     * Remove the user's profile picture.
     */
    public function deleteProfilePicture(): void
    {
        $user = Auth::user();
        $existing = Picture::query()
            ->where('foreign_id', $user->id)
            ->where('type', PictureTypeEnum::PROFILE->value)
            ->first();

        if ($existing) {
            if ($existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $existing->delete();
        }

        $this->currentProfileUrl = null;
        $this->dispatch('profile-picture-deleted');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />

        <hr class="my-8 border-gray-200 dark:border-gray-700" />

        <div class="my-6 w-full space-y-6">
            <flux:text class="font-medium">{{ __('Profile Picture') }}</flux:text>

            @if ($currentProfileUrl)
                <img src="{{ $currentProfileUrl }}" alt="{{ __('Profile picture') }}" class="h-24 w-24 rounded-full object-cover" />
                <div class="mt-2">
                    <flux:button variant="danger" wire:click="deleteProfilePicture" data-test="delete-profile-picture-button">
                        {{ __('Remove current picture') }}
                    </flux:button>
                    <x-action-message class="ms-3" on="profile-picture-deleted">
                        {{ __('Removed.') }}
                    </x-action-message>
                </div>
            @else
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">{{ __('No profile picture yet.') }}</flux:text>
            @endif

            <form wire:submit="updateProfilePicture" class="space-y-4" enctype="multipart/form-data">
                <flux:input type="file" wire:model="profile_image" :label="__('Upload new picture')" accept="image/*" required />
                <flux:input wire:model="caption" :label="__('Caption (optional)')" type="text" />
                <flux:input wire:model="alt_text" :label="__('Alt text (optional)')" type="text" />

                <div wire:loading wire:target="profile_image" class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Uploading...') }}
                </div>

                @error('profile_image')
                    <flux:text class="text-sm !text-red-600 !dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="upload-profile-picture-button">
                        {{ __('Save Picture') }}
                    </flux:button>

                    <x-action-message class="me-3" on="profile-picture-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </div>
    </x-settings.layout>
</section>
