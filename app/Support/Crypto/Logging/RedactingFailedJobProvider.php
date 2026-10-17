<?php

namespace App\Support\Crypto\Logging;

use App\Support\Crypto\DecryptedValues;
use DateTimeInterface;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;
use RuntimeException;

/**
 * Wraps Laravel's failed-job store, which writes the whole exception text to
 * the failed_jobs table, so a value decrypted during the job is scrubbed from
 * it first (SEC-11). The job payload itself holds only ids.
 */
final class RedactingFailedJobProvider implements CountableFailedJobProvider, FailedJobProviderInterface, PrunableFailedJobProvider
{
    public function __construct(private readonly FailedJobProviderInterface $inner) {}

    public function log($connection, $queue, $payload, $exception)
    {
        $scrubbed = app(DecryptedValues::class)->scrub((string) $exception);

        return $this->inner->log($connection, $queue, $payload, new class($scrubbed) extends RuntimeException
        {
            public function __construct(private readonly string $text)
            {
                parent::__construct('A queued job failed.');
            }

            public function __toString(): string
            {
                return $this->text;
            }
        });
    }

    /**
     * @return array<int, int|string>
     */
    public function ids($queue = null)
    {
        return $this->inner->ids($queue);
    }

    /**
     * @return array<int, object>
     */
    public function all()
    {
        return $this->inner->all();
    }

    public function find($id)
    {
        return $this->inner->find($id);
    }

    public function forget($id)
    {
        return $this->inner->forget($id);
    }

    public function flush($hours = null)
    {
        $this->inner->flush($hours);
    }

    public function count($connection = null, $queue = null)
    {
        return $this->inner instanceof CountableFailedJobProvider ? $this->inner->count($connection, $queue) : count($this->inner->all());
    }

    public function prune(DateTimeInterface $before)
    {
        return $this->inner instanceof PrunableFailedJobProvider ? $this->inner->prune($before) : 0;
    }
}
