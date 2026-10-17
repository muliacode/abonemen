# Spikes

Short proofs of concept from the [technical design](../product/technical-design.md#spikes-before-building), each confirming or changing a decision before building starts. Every spike keeps its proof-of-concept code on its own `spike/` branch; that code is never merged. What is merged is the outcome below and any change to the design.

| Spike | Issue | Outcome |
| --- | --- | --- |
| [1. CipherSweet multi-tenant encryption](1-ciphersweet.md) | [#12](https://github.com/muliacode/abonemen/issues/12) | ADR 3 accepted with changes: a faster backend with the same wire format, ULIDs returned by the cast, ULID keys for users |
