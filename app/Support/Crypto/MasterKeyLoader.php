<?php

namespace App\Support\Crypto;

/**
 * Loads the master key from a file (ABONEMEN_MASTER_KEY_FILE, preferred) or
 * from the environment (ABONEMEN_MASTER_KEY), once per process.
 *
 * Setting both is refused, so it is never ambiguous which key wraps the data
 * keys. An empty value counts as not set.
 */
final class MasterKeyLoader
{
    private ?MasterKey $key = null;

    private ?InvalidMasterKey $error = null;

    private bool $loaded = false;

    public function __construct(
        #[\SensitiveParameter] private readonly ?string $environmentValue,
        private readonly ?string $filePath,
    ) {}

    /**
     * @throws MasterKeyMissing
     * @throws InvalidMasterKey
     */
    public function key(): MasterKey
    {
        $this->load();

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->key ?? throw new MasterKeyMissing;
    }

    public function status(): MasterKeyStatus
    {
        $this->load();

        return match (true) {
            $this->error !== null => MasterKeyStatus::Invalid,
            $this->key === null => MasterKeyStatus::Missing,
            default => MasterKeyStatus::Ready,
        };
    }

    /**
     * Why a configured key cannot be used, without the key itself.
     */
    public function problem(): ?string
    {
        $this->load();

        return $this->error?->getMessage();
    }

    /**
     * The configured key file, as an absolute path, or null when the key is
     * not configured as a file.
     */
    public function filePath(): ?string
    {
        if ($this->filePath === null || $this->filePath === '') {
            return null;
        }

        return str_starts_with($this->filePath, '/') ? $this->filePath : base_path($this->filePath);
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        $fromEnvironment = $this->environmentValue !== null && $this->environmentValue !== '';
        $path = $this->filePath();

        try {
            $this->key = match (true) {
                $fromEnvironment && $path !== null => throw new InvalidMasterKey('Set ABONEMEN_MASTER_KEY_FILE or ABONEMEN_MASTER_KEY, not both.'),
                $path !== null => $this->fromFile($path),
                $fromEnvironment => MasterKey::fromBase64((string) $this->environmentValue),
                default => null,
            };
        } catch (InvalidMasterKey $e) {
            $this->error = $e;
        }
    }

    /**
     * @throws InvalidMasterKey
     */
    private function fromFile(string $path): MasterKey
    {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidMasterKey("The master key file {$path} does not exist or cannot be read.");
        }

        try {
            return MasterKey::fromBase64($contents);
        } catch (InvalidMasterKey) {
            throw new InvalidMasterKey("The master key file {$path} does not hold ".MasterKey::BYTES.' base64-encoded bytes.');
        }
    }
}
