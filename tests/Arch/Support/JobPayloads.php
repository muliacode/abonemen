<?php

namespace Tests\Arch\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Queued jobs carry ids, never plaintext (SEC-11): a job's constructor may
 * take ints, strings or arrays named like ids (…Id, …Ids), models (which
 * SerializesModels queues as their id) and enums.
 */
final class JobPayloads
{
    /**
     * @param  class-string  $job
     * @return list<string> One line per constructor parameter that may carry data.
     */
    public static function violations(string $job): array
    {
        $constructor = (new ReflectionClass($job))->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $violations = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $parameter->getName();
            $named = $type instanceof ReflectionNamedType ? $type->getName() : null;

            $allowed = match (true) {
                $named === 'int' => true,
                in_array($named, ['string', 'array'], true) => (bool) preg_match('/Ids?$/', $name),
                // Enums first: class_exists() is true for them too.
                $named !== null && enum_exists($named) => is_subclass_of($named, BackedEnum::class),
                $named !== null && class_exists($named) => is_subclass_of($named, Model::class),
                default => false,
            };

            if (! $allowed) {
                $violations[] = "{$job}::\${$name} (".($type ?? 'untyped').')';
            }
        }

        return $violations;
    }

    /**
     * @return list<class-string>
     */
    public static function appJobs(): array
    {
        $root = dirname(__DIR__, 3).'/app/Jobs';
        $jobs = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $relative = substr($file->getPathname(), strlen($root) + 1, -4);
                $jobs[] = 'App\\Jobs\\'.str_replace('/', '\\', $relative);
            }
        }

        /** @var list<class-string> $jobs */
        return $jobs;
    }
}
