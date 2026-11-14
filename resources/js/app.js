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

        // A confirmation waiting on the new page ("Household created.") is read out first.
        const confirmation = [...document.querySelectorAll('[data-status-message]')].find((message) => message.textContent.trim() !== '');
        const target = confirmation ?? document.querySelector('[data-page-heading]') ?? document.getElementById('main');

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

/**
 * Flux ties a field's label and description to its control, but not its error message.
 * Point each control at the message of its own field while there is one.
 */
function linkErrorsToControls() {
    document.querySelectorAll('ui-field').forEach((field) => {
        const error = field.querySelector('[data-flux-error]');
        const control = field.querySelector('[data-flux-control]');

        if (!error?.id || !control) {
            return;
        }

        const target = control.matches('input, select, textarea') ? control : control.querySelector('input, select, textarea, button') ?? control;
        const ids = (target.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => id && id !== error.id);

        if (error.textContent.trim() !== '') {
            ids.push(error.id);
        }

        if (ids.length > 0) {
            target.setAttribute('aria-describedby', ids.join(' '));
        } else {
            target.removeAttribute('aria-describedby');
        }
    });
}

let errorLinkScheduled = false;

new MutationObserver(() => {
    if (errorLinkScheduled) {
        return;
    }

    errorLinkScheduled = true;

    requestAnimationFrame(() => {
        errorLinkScheduled = false;
        linkErrorsToControls();
    });
}).observe(document.documentElement, { childList: true, subtree: true, characterData: true });

document.addEventListener('DOMContentLoaded', linkErrorsToControls);
