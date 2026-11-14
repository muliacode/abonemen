// Behaviour for keyboard and screen reader users that markup alone cannot give. See docs/accessibility.md.

let firstPage = true;

/**
 * After a wire:navigate page change nothing tells assistive technology that the page changed,
 * so move focus to the new page's heading (or <main>), unless something there already took it.
 */
document.addEventListener('livewire:navigated', () => {
    if (firstPage) {
        firstPage = false;

        return;
    }

    requestAnimationFrame(() => {
        const somethingHasFocus = document.activeElement && document.activeElement !== document.body;

        if (somethingHasFocus) {
            return;
        }

        const target = document.querySelector('[data-page-heading]') ?? document.getElementById('main');

        target?.focus();
    });
});

/**
 * The sidebar toggle of the mobile header reports whether the sidebar is open.
 */
function syncSidebarToggle() {
    const sidebar = document.querySelector('[data-flux-sidebar]');

    if (!sidebar) {
        return;
    }

    const open = sidebar.hasAttribute('data-flux-sidebar-on-mobile') && !sidebar.hasAttribute('data-flux-sidebar-collapsed-mobile');

    document.querySelectorAll('[data-flux-sidebar-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-expanded', String(open));
    });
}

let sidebarObserver = null;

document.addEventListener('livewire:navigated', () => {
    const sidebar = document.querySelector('[data-flux-sidebar]');

    sidebarObserver?.disconnect();
    syncSidebarToggle();

    if (sidebar) {
        sidebarObserver = new MutationObserver(syncSidebarToggle);
        sidebarObserver.observe(sidebar, { attributes: true });
    }
});
