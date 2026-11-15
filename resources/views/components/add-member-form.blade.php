<?php

use App\Actions\Households\SaveMember;
use App\Exceptions\MemberException;
use App\Models\Household;
use App\Rules\MemberRules;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Household $household;

    public string $name = '';

    public string $nickname = '';

    /**
     * The confirmation shown next to the form until the User changes something again.
     */
    public ?string $status = null;

    public function mount(Household $household): void
    {
        $this->household = $household;
    }

    public function add(SaveMember $saveMember): void
    {
        Gate::authorize('manageMembers', $this->household);

        $validated = $this->validate([
            'name' => MemberRules::name(),
            'nickname' => MemberRules::nickname(),
        ]);

        try {
            $saveMember->create($this->household, $validated['name'], $validated['nickname']);
        } catch (MemberException $exception) {
            $this->addError('nickname', $exception->userMessage(filled($validated['nickname'])));

            return;
        }

        $this->reset('name', 'nickname');
        $this->status = __('Member added.');

        $this->dispatch('member-saved');
    }

    public function updated(): void
    {
        $this->status = null;
    }
}; ?>

<form wire:submit="add" class="space-y-6" data-test="add-member-form">
    <x-error-summary prefix="add-member-" />

    <flux:input id="add-member-name" error:id="add-member-error-name" wire:model="name" :label="__('Name')" required data-test="add-member-name" />

    <flux:input id="add-member-nickname" error:id="add-member-error-nickname" wire:model="nickname" :label="__('Nickname')" :description="__('Shown instead of the name. Use it to tell two Members with the same name apart.')" data-test="add-member-nickname" />

    <div class="flex items-center gap-4">
        <flux:button variant="primary" type="submit" data-test="add-member-submit">
            {{ __('Add Member') }}
        </flux:button>

        <x-status-message :message="$status" />
    </div>
</form>
