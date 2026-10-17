<?php

namespace Tests\Arch\Support;

/**
 * A backed enum, for checking that jobs may take enums.
 */
enum JobStatus: string
{
    case Queued = 'queued';
}
