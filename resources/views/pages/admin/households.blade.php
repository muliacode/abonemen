<?php

use App\Models\Household;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Households')] class extends Component {
    use WithPagination;

    /**
     * How many Households and how many Users in each: nothing of what is inside them (ADR 0003).
     *
     * @return LengthAwarePaginator<int, Household>
     */
    #[Computed]
    public function households(): LengthAwarePaginator
    {
        return Household::query()
            ->withCount(['members as users_count' => fn ($members) => $members->whereNotNull('user_id')])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Households')" :description="__('Every Household on this instance')" />

    <x-admin-nav />

    <table class="w-full text-start" data-test="households-table">
        <caption class="sr-only">{{ __('Households, newest first') }}</caption>
        <thead>
            <tr class="border-b border-zinc-300 text-start">
                <th scope="col" class="py-2 pe-4 text-start font-medium">{{ __('Name') }}</th>
                <th scope="col" class="py-2 pe-4 text-start font-medium">{{ __('Created') }}</th>
                <th scope="col" class="py-2 text-end font-medium">{{ __('Users') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->households as $household)
                <tr class="border-b border-zinc-200" wire:key="household-{{ $household->id }}" data-test="household-row">
                    <th scope="row" class="py-3 pe-4 text-start font-medium">{{ $household->name }}</th>
                    <td class="py-3 pe-4"><time datetime="{{ $household->created_at->toIso8601String() }}">{{ $household->created_at->format('Y-m-d') }}</time></td>
                    <td class="py-3 text-end">{{ $household->users_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-8 text-center text-muted">{{ __('There are no Households yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-6">
        {{ $this->households->links() }}
    </div>
</section>
