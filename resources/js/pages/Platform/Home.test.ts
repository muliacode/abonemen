import { render, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { axe } from '@/tests/axe';
import Home from './Home.vue';

import { i18nVue } from 'laravel-vue-i18n';
import en from '../../../../lang/en.json';

// <Head> needs a running Inertia app; the page title is checked end to end.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ props: { locale: 'en', locales: ['nl', 'en'] } }),
}));

const renderHome = () =>
    render(Home, {
        global: {
            plugins: [[i18nVue, { lang: 'en', resolve: () => en }]],
        },
    });

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
