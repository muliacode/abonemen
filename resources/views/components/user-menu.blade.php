<flux:dropdown position="top" align="start">
    <button type="button" class="group flex min-h-14 w-full items-center gap-3 rounded-lg px-2 py-1.5 text-start text-rail-text hover:bg-white/10" data-test="sidebar-menu-button">
        <flux:avatar :initials="auth()->user()->initials()" size="sm" class="bg-white/10! text-rail-text!" />
        <span class="grid min-w-0 flex-1 leading-tight">
            <span class="truncate font-medium">{{ auth()->user()->name }}</span>
            <span class="truncate text-sm text-rail-muted">{{ auth()->user()->email }}</span>
        </span>
        <flux:icon name="chevrons-up-down" variant="micro" class="size-4 shrink-0 text-rail-muted" />
    </button>

    <flux:menu>
        <div class="flex items-center gap-3 px-2 py-2 text-start">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid min-w-0 flex-1 leading-tight">
                <span class="truncate font-medium text-ink">{{ auth()->user()->name }}</span>
                <span class="truncate text-sm text-muted">{{ auth()->user()->email }}</span>
            </div>
        </div>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer"
                data-test="logout-button"
            >
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
