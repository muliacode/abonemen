<?php

namespace App\Support\Crypto;

use App\Models\StoredFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use RuntimeException;
use Throwable;

/**
 * Encrypts uploads with libsodium secretstream (XChaCha20-Poly1305) under a
 * key derived from the household data key and the file's ULID (SEC-04), and
 * stores them on the "encrypted" disk, outside the web root.
 *
 * File format: "ABF1", the secretstream header, then the plaintext in 64 KiB
 * chunks, each followed by its tag; the last chunk carries the final tag, so
 * a truncated file is detected.
 */
final class FileVault
{
    public const DISK = 'encrypted';

    private const MAGIC = 'ABF1';

    private const CHUNK = 65536;

    public function __construct(private readonly DataKeyRing $ring) {}

    public function store(string $householdId, #[\SensitiveParameter] string $contents, string $mime): StoredFile
    {
        $file = new StoredFile;
        $file->id = $file->newUniqueId();
        $key = $this->ring->household($householdId);

        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->fileKey($key, $file->id));
        $encrypted = self::MAGIC.$header;
        $chunks = str_split($contents, self::CHUNK) ?: [''];
        $last = count($chunks) - 1;
        foreach ($chunks as $i => $chunk) {
            $encrypted .= sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,
                $chunk,
                '',
                $i === $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
            );
        }

        $path = "{$householdId}/{$file->id}";
        $this->disk()->put($path, $encrypted);

        // No row, no file: an encrypted file nothing points to would never be
        // deleted.
        try {
            $file->fill([
                'household_id' => $householdId,
                'mime' => $mime,
                'size' => strlen($contents),
                'path' => $path,
                'key_version' => $key->version,
            ])->save();
        } catch (Throwable $e) {
            $this->disk()->delete($path);

            throw $e;
        }

        return $file;
    }

    /**
     * @throws FileDecryptionFailed When the file was altered, truncated, moved
     *                              to another household or renamed to another id.
     */
    public function read(StoredFile $file): string
    {
        try {
            $encrypted = $this->disk()->get($file->path);
        } catch (UnableToReadFile) {
            $encrypted = null;
        }
        if ($encrypted === null) {
            throw new FileDecryptionFailed("File {$file->id} is missing from the disk.");
        }

        $headerBytes = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        if (! str_starts_with($encrypted, self::MAGIC) || strlen($encrypted) < strlen(self::MAGIC) + $headerBytes) {
            throw new FileDecryptionFailed("File {$file->id} is not an encrypted Abonemen file.");
        }

        $key = $this->ring->household($file->household_id, $file->key_version);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
            substr($encrypted, strlen(self::MAGIC), $headerBytes),
            $this->fileKey($key, $file->id),
        );

        $plaintext = '';
        $offset = strlen(self::MAGIC) + $headerBytes;
        $sealedChunk = self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        $finished = false;

        while ($offset < strlen($encrypted)) {
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, substr($encrypted, $offset, $sealedChunk));
            if ($result === false || $finished) {
                throw new FileDecryptionFailed("File {$file->id} cannot be decrypted.");
            }

            [$chunk, $tag] = $result;
            $plaintext .= $chunk;
            $finished = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            $offset += $sealedChunk;
        }

        if (! $finished) {
            throw new FileDecryptionFailed("File {$file->id} is truncated.");
        }

        return $plaintext;
    }

    public function delete(StoredFile $file): void
    {
        $this->disk()->delete($file->path);
        $file->delete();
    }

    /**
     * One key per file: keyed BLAKE2b over the file's ULID, with the household
     * data key as the key.
     */
    private function fileKey(DataKeyMaterial $householdKey, string $fileId): string
    {
        if ($fileId === '') {
            throw new RuntimeException('A file needs its ULID before it is encrypted.');
        }

        return sodium_crypto_generichash("abonemen:file:{$fileId}", $householdKey->bytes(), SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
