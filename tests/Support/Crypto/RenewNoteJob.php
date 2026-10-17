<?php

namespace Tests\Support\Crypto;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * A job shaped like the real ones: it carries an id, loads the row, and here
 * fails with the decrypted values in its message, as a careless bug would.
 */
class RenewNoteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $noteId) {}

    public function handle(): void
    {
        $note = EncryptedNote::query()->findOrFail($this->noteId);

        throw new RuntimeException("Could not renew {$note->title} at {$note->amount}.");
    }
}
