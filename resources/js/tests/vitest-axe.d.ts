import type { AxeMatchers } from 'vitest-axe/matchers';

declare module 'vite-plus/test' {
    interface Assertion extends AxeMatchers {}
    interface AsymmetricMatchersContaining extends AxeMatchers {}
}
