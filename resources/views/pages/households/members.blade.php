<?php

use App\Actions\Households\ChangeMemberRole;
use App\Actions\Households\RemoveMember;
use App\Actions\Households\SaveMember;
use App\Data\MemberRow;
use App\Enums\HouseholdRole;
use App\Exceptions\MemberException;
use App\Models\Household;
use App\Models\Member;
use App\Rules\MemberRules;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Members')] class extends Component
{
    #[Locked]
    public Household $household;

    /**
     * The Member in the edit dialog, and the one in the remove dialog.
     */
    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $removingId = null;

    public string $editName = '';

    public string $editNickname = '';

    public string $editRole = '';

    /**
     * The confirmation shown at the top of the page until the User does something else.
     */
    public ?string $status = null;

    public function mount(Household $household): void
    {
        Gate::authorize('view', $household);

        $this->household = $household;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manageMembers', $this->household);

        $member = $this->findMember($id);

        $this->editingId = $member->id;
        $this->editName = $member->name;
        $this->editNickname = (string) $member->nickname;
        $this->editRole = $member->user?->householdRole($this->household)?->value ?? '';
        $this->status = null;
        $this->resetValidation();

        Flux::modal('edit-member')->show();
    }

    public function saveEdit(SaveMember $saveMember, ChangeMemberRole $changeRole): void
    {
        Gate::authorize('manageMembers', $this->household);

        $member = $this->findMember((int) $this->editingId);
        $hasUser = $member->user_id !== null;

        $validated = $this->validate([
            'editName' => MemberRules::name(),
            'editNickname' => MemberRules::nickname(),
            'editRole' => $hasUser ? MemberRules::role() : ['nullable'],
        ]);

        try {
            DB::transaction(function () use ($saveMember, $changeRole, $member, $hasUser, $validated) {
                $saveMember->update($member, $validated['editName'], $validated['editNickname']);

                if ($hasUser) {
                    $changeRole->handle($member, HouseholdRole::from($validated['editRole']));
                }
            });
        } catch (MemberException $exception) {
            $exception->reason === MemberException::LABEL_TAKEN
                ? $this->addError('editNickname', $exception->userMessage(filled($validated['editNickname'])))
                : $this->addError('editRole', $exception->userMessage());

            return;
        }

        Flux::modal('edit-member')->close();
        $this->editingId = null;

        $this->afterChange(__('Saved.'));
    }

    public function confirmRemove(int $id): void
    {
        Gate::authorize('manageMembers', $this->household);

        $this->removingId = $this->findMember($id)->id;
        $this->status = null;
        $this->resetValidation();

        Flux::modal('remove-member')->show();
    }

    public function remove(RemoveMember $removeMember): void
    {
        Gate::authorize('manageMembers', $this->household);

        $member = $this->findMember((int) $this->removingId);
        $label = $member->label();

        try {
            $removeMember->handle($member);
        } catch (MemberException $exception) {
            $this->addError('remove', $exception->userMessage());

            return;
        }

        Flux::modal('remove-member')->close();
        $this->removingId = null;

        $this->afterChange(__(':label was removed.', ['label' => $label]));
    }

    /**
     * Refresh the list when a Member is added from the form above it.
     */
    #[On('member-saved')]
    public function refresh(): void
    {
        unset($this->members);
        $this->status = null;
    }

    public function updated(): void
    {
        $this->status = null;
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('manageMembers', $this->household);
    }

    /**
     * @return Collection<int, MemberRow>
     */
    #[Computed]
    public function members(): Collection
    {
        $members = $this->household->members()->with('user')->get();

        $roles = $members->mapWithKeys(fn (Member $member) => [$member->id => $member->user?->householdRole($this->household)]);
        $owners = $roles->filter(fn (?HouseholdRole $role) => $role === HouseholdRole::Owner)->count();

        return $members
            ->map(fn (Member $member) => new MemberRow(
                id: $member->id,
                label: $member->label(),
                name: $member->name,
                nickname: $member->nickname,
                hasUser: $member->user_id !== null,
                role: $roles[$member->id],
                isLastOwner: $roles[$member->id] === HouseholdRole::Owner && $owners === 1,
                holdsSubscriptions: $member->holdsSubscriptions(),
            ))
            ->sortBy(fn (MemberRow $row) => mb_strtolower($row->label), SORT_STRING)
            ->values();
    }

    #[Computed]
    public function editing(): ?MemberRow
    {
        return $this->editingId === null ? null : $this->members->firstWhere('id', $this->editingId);
    }

    #[Computed]
    public function removing(): ?MemberRow
    {
        return $this->removingId === null ? null : $this->members->firstWhere('id', $this->removingId);
    }

    /**
     * Get the roles an Owner can give: the default ones.
     *
     * @return list<HouseholdRole>
     */
    public function roles(): array
    {
        return HouseholdRole::cases();
    }

    /**
     * Find a Member of this Household. One from anywhere else is not found, whatever id is sent.
     */
    private function findMember(int $id): Member
    {
        return $this->household->members()->with('user')->findOrFail($id);
    }

    /**
     * Confirm a change. An Owner who has just given up their own rights has nothing left to manage here, and goes
     * back to their Households.
     */
    private function afterChange(string $message): void
    {
        unset($this->members, $this->canManage, $this->editing, $this->removing);

        if (! $this->canManage) {
            session()->flash('confirmation', $message);

            $this->redirectRoute('households.index', navigate: true);

            return;
        }

        $this->status = $message;
    }
}; ?>

<section class="w-full">
    <x-pages::households.layout :household="$household" :heading="__('Members')" :subheading="__('The people Subscriptions can be held by')">
        <div class="space-y-8">
            @if ($this->canManage)
                <livewire:add-member-form :household="$household" />
            @endif

            {{-- Shown only when there is something to say, then focused so that it is read out after a dialog closes. --}}
            @if (filled($status))
                <x-status-message :message="$status" wire:key="status-{{ md5($status) }}" x-data x-init="$nextTick(() => $el.focus())" />
            @endif

            <ul class="space-y-3" aria-label="{{ __('Members') }}">
                @foreach ($this->members as $row)
                    <li wire:key="member-{{ $row->id }}" class="rounded-lg border border-zinc-300 bg-surface p-4" data-test="member-row">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="font-medium break-words" data-test="member-label">{{ $row->label }}</h3>

                                @if ($row->nickname !== null)
                                    <p class="text-sm text-muted break-words" data-test="member-real-name">{{ $row->name }}</p>
                                @endif

                                <p class="mt-1 text-sm text-muted">
                                    @if ($row->hasUser)
                                        <span data-test="member-can-sign-in"><flux:icon name="key" variant="mini" class="inline" /> {{ __('Can sign in') }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span data-test="member-role">{{ $row->role?->label() }}</span>
                                    @else
                                        {{ __('Cannot sign in') }}
                                    @endif
                                </p>
                            </div>

                            @if ($this->canManage)
                                <div class="flex shrink-0 gap-2">
                                    <flux:button size="sm" icon="pencil" wire:click="edit({{ $row->id }})" :aria-label="__('Edit :label', ['label' => $row->label])" data-test="edit-member-button" />

                                    <flux:button size="sm" icon="trash" wire:click="confirmRemove({{ $row->id }})" :disabled="$row->cannotBeRemoved()" :aria-label="__('Remove :label', ['label' => $row->label])" data-test="remove-member-button" />
                                </div>
                            @endif
                        </div>

                        @if ($this->canManage && $row->isLastOwner)
                            <p class="mt-2 text-sm text-muted" data-test="last-owner-note">{{ __('The only Owner. Make someone else an Owner before this Member steps down or is removed.') }}</p>
                        @elseif ($this->canManage && $row->holdsSubscriptions)
                            <p class="mt-2 text-sm text-muted">{{ __('Holds Subscriptions. Reassign them before removing this Member.') }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </x-pages::households.layout>

    @if ($this->canManage)
        <flux:modal name="edit-member" aria-labelledby="edit-member-heading" :show="$errors->hasAny(['editName', 'editNickname', 'editRole'])" focusable class="max-w-lg">
            <form wire:submit="saveEdit" class="space-y-6">
                <x-error-summary prefix="edit-member-" :only="['editName', 'editNickname', 'editRole']" />

                <flux:heading id="edit-member-heading" level="2" size="lg">{{ __('Edit :label', ['label' => $this->editing?->label ?? '']) }}</flux:heading>

                <flux:input id="edit-member-editName" error:id="edit-member-error-editName" wire:model="editName" :label="__('Name')" required data-test="edit-member-name" />

                <flux:input id="edit-member-editNickname" error:id="edit-member-error-editNickname" wire:model="editNickname" :label="__('Nickname')" :description="__('Shown instead of the name. Use it to tell two Members with the same name apart.')" data-test="edit-member-nickname" />

                @if ($this->editing?->hasUser)
                    <flux:select id="edit-member-editRole" error:id="edit-member-error-editRole" wire:model="editRole" :label="__('Role')" :disabled="$this->editing->isLastOwner" required data-test="edit-member-role">
                        @foreach ($this->roles() as $role)
                            <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($this->editing->isLastOwner)
                        <p class="text-sm text-muted" data-test="role-locked-note">{{ __('This is the only Owner who can sign in, so the role cannot change. Make someone else an Owner first.') }}</p>
                    @endif
                @else
                    <p class="text-sm text-muted">{{ __('This Member is not linked to a User, so there is no role to set.') }}</p>
                @endif

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="edit-member-submit">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="remove-member" aria-labelledby="remove-member-heading" :show="$errors->has('remove')" focusable class="max-w-lg">
            <form wire:submit="remove" class="space-y-6">
                <x-error-summary prefix="remove-member-" :only="['remove']" />

                <div>
                    <flux:heading id="remove-member-heading" level="2" size="lg">{{ __('Remove :label', ['label' => $this->removing?->label ?? '']) }}</flux:heading>

                    <flux:subheading>
                        @if ($this->removing?->hasUser)
                            {{ __(':label will lose access to this Household, and keeps their account.', ['label' => $this->removing->label]) }}
                        @else
                            {{ __('Remove :label from this Household?', ['label' => $this->removing?->label ?? '']) }}
                        @endif
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button id="remove-member-remove" variant="danger" type="submit" data-test="confirm-remove-member-button">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
