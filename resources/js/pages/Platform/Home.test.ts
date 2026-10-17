import { render, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { axe } from '@/tests/axe';
import Home from './Home.vue';

// <Head> needs a running Inertia app; the page title is checked end to end.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
}));

const renderHome = () => render(Home);

describe('Platform/Home', () => {
    it('has a single level-one heading', () => {
        renderHome();

        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    });

    it('has no axe violations', async () => {
        const { container } = renderHome();

        expect(await axe(container)).toHaveNoViolations();
    });
});
