import { cleanup } from '@testing-library/vue';
import { afterEach, expect } from 'vite-plus/test';
import * as matchers from 'vitest-axe/matchers';

expect.extend(matchers);

afterEach(() => {
    cleanup();
});
