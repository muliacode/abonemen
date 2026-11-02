<?php

use App\Data\UserHousehold;
use App\Models\Household;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function currentHousehold(): ?array
    {
        $household = Auth::user()->currentHousehold;

        return $household ? [
            'id' => $household->id,
            'name' => $household->name,
            'ulid' => $household->ulid,
        ] : null;
    }

    /**
     * @return Collection<int, UserHousehold>
     */
    public function households(): Collection
    {
        return Auth::user()->toUserHouseholds();
    }

    public function switchHousehold(string $ulid): void
    {
        $user = Auth::user();

        abort_unless(
            $user->belongsToHousehold($household = Household::where('ulid', $ulid)->firstOrFail()),
            403
        );

        $currentUlid = $user->currentHousehold?->ulid;

        $user->switchHousehold($household);

        if (! request()->header('Referer')) {
            $this->redirectRoute('dashboard', ['current_household' => $household->ulid], navigate: true);

            return;
        }

        if (! $currentUlid) {
            $this->redirect(request()->header('Referer'), navigate: true);

            return;
        }

        $redirectTo = $this->replaceCurrentHouseholdInReferer(
            request()->header('Referer'),
            $currentUlid,
            $household->ulid,
        );

        $this->redirect($redirectTo ?? request()->header('Referer'), navigate: true);
    }

    protected function replaceCurrentHouseholdInReferer(string $referer, string $currentUlid, string $newUlid): ?string
    {
        $redirectTo = preg_replace(
            '#/'.preg_quote($currentUlid, '#').'(?=/|\?|$)#',
            '/'.$newUlid,
            $referer,
            1,
        );

        return preg_replace(
            '#([?&]current_household=)'.preg_quote($currentUlid, '#').'(?=&|$)#',
            '$1'.$newUlid,
            $redirectTo ?? $referer,
            1,
        );
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="start">
        <flux:button variant="ghost" class="group w-full justify-start in-data-flux-sidebar-collapsed-desktop:justify-center" data-test="household-switcher-trigger">
            <flux:icon name="users" class="hidden size-4 in-data-flux-sidebar-collapsed-desktop:block" />
            <span class="truncate font-semibold in-data-flux-sidebar-collapsed-desktop:hidden">{{ $this->currentHousehold()['name'] ?? __('Select household') }}</span>
            <flux:icon
                name="chevrons-up-down"
                variant="micro"
                class="ms-auto size-4 in-data-flux-sidebar-collapsed-desktop:hidden"
            />
        </flux:button>

        <flux:menu class="min-w-56">
            <flux:menu.heading>{{ __('Households') }}</flux:menu.heading>

            @foreach ($this->households() as $household)
                <flux:menu.item
                    wire:click="switchHousehold('{{ $household->ulid }}')"
                    class="cursor-pointer"
                    data-test="household-switcher-item"
                >
                    <div class="flex w-full items-center justify-between">
                        <span>{{ $household->name }}</span>
                        @if ($household->isCurrent)
                            <flux:icon name="check" class="size-4" />
                        @endif
                    </div>
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />

            <flux:modal.trigger name="create-household">
                <flux:menu.item icon="plus" class="cursor-pointer" data-test="household-switcher-new-household">
                    {{ __('New household') }}
                </flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
</div>
