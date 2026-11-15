<?php

use App\Actions\Instance\ChangeInstanceAdmin;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    use ProfileValidationRules;

    public string $email = '';

    // Locked: only a search by email address may choose the User. A browser could otherwise set these and read any User by id.
    #[Locked]
    public bool $searched = false;

    #[Locked]
    public ?int $userId = null;

    public string $newName = '';

    public string $newEmail = '';

    /**
     * The password of a User the admin just created. It is shown once and never stored in readable form.
     */
    public ?string $createdPassword = null;

    /**
     * Look a User up by the exact email address. A part of an address finds nobody.
     */
    public function find(): void
    {
        $this->resetErrorBag();
        $this->createdPassword = null;

        $this->userId = User::whereEmail(trim($this->email))->value('id');
        $this->searched = true;
    }

    #[Computed]
    public function user(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    public function makeAdmin(ChangeInstanceAdmin $change): void
    {
        if ($this->user) {
            $change->handle($this->user, true);
        }
    }

    public function removeAdmin(ChangeInstanceAdmin $change): void
    {
        if ($this->user && ! $change->handle($this->user, false)) {
            $this->addError('admin', __('This is the last Instance Admin. Make someone else an Instance Admin first.'));
        }
    }

    /**
     * Create a verified User with a generated password, to hand over once.
     */
    public function createUser(): void
    {
        $validated = $this->validate([
            'newName' => $this->nameRules(),
            'newEmail' => $this->emailRules(),
        ]);

        $this->createdPassword = Str::password(20, symbols: false);

        $user = new User([
            'name' => $validated['newName'],
            'email' => $validated['newEmail'],
            'password' => $this->createdPassword,
            'locale' => app()->getLocale(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->reset('newName', 'newEmail');
    }

    public function updated(string $property): void
    {
        if ($property !== 'newName' && $property !== 'newEmail') {
            return;
        }

        $this->createdPassword = null;
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Users')" :description="__('Find a User by their exact email address, or create one')" />

    <x-admin-nav />

    <x-error-summary />

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        <div>
            <h2 class="text-lg font-semibold">{{ __('Find a User') }}</h2>

            <form wire:submit="find" class="mt-4 flex flex-col gap-4">
                <flux:input id="email" wire:model="email" :label="__('Email address')" :description="__('The whole address. A part of it finds nobody.')" type="email" required autocomplete="off" />

                <div>
                    <flux:button type="submit" variant="primary" data-test="find-user-button">{{ __('Find') }}</flux:button>
                </div>
            </form>

            @if ($searched)
                <div class="mt-6" data-test="user-result" role="status">
                    @if ($this->user)
                        <p class="font-medium">{{ $this->user->name }}</p>
                        <p class="text-muted">{{ $this->user->email }}</p>
                        <p class="mt-2" id="admin">
                            {{ $this->user->is_instance_admin ? __('This User is an Instance Admin.') : __('This User is not an Instance Admin.') }}
                        </p>

                        <div class="mt-4">
                            @if ($this->user->is_instance_admin)
                                <flux:button wire:click="removeAdmin" data-test="remove-admin-button">{{ __('Remove Instance Admin') }}</flux:button>
                            @else
                                <flux:button wire:click="makeAdmin" data-test="make-admin-button">{{ __('Make Instance Admin') }}</flux:button>
                            @endif
                        </div>
                    @else
                        <p>{{ __('There is no User with that email address.') }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-lg font-semibold">{{ __('Create a User') }}</h2>

            <form wire:submit="createUser" class="mt-4 flex flex-col gap-4">
                <flux:input id="newName" wire:model="newName" :label="__('Name')" type="text" required autocomplete="off" />
                <flux:input id="newEmail" wire:model="newEmail" :label="__('Email address')" type="email" required autocomplete="off" />

                <div>
                    <flux:button type="submit" variant="primary" data-test="create-user-button">{{ __('Create User') }}</flux:button>
                </div>

                <div role="status" data-test="created-password">
                    @if ($createdPassword)
                        <p>{{ __('The User is created. Hand over this password now; it is not shown again.') }}</p>
                        <p class="mt-2 break-all font-mono text-lg" data-test="created-password-value">{{ $createdPassword }}</p>
                    @endif
                </div>
            </form>
        </div>
    </div>
</section>
