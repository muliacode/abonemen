<?php

namespace App\Support;

use App\Enums\Connection;
use App\Enums\ConnectionState;
use App\Exceptions\ConnectionException;
use App\Models\Household;
use App\Models\HouseholdConnection;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether a Connection may be used for a Household. Everything that sends Household data asks here.
 */
class Connections
{
    public function __construct(private PlatformSettings $settings, private MailConfiguration $mail)
    {
        //
    }

    public function isAvailable(Connection $connection): bool
    {
        return $this->settings->connectionIsAvailable($connection);
    }

    /**
     * Whether the Instance Admin may make a Connection available: only while there is somewhere real for it to go.
     */
    public function canBeMadeAvailable(Connection $connection): bool
    {
        return match ($connection) {
            Connection::Email => $this->mail->delivers(),
        };
    }

    /**
     * The Instance Admin makes a Connection available to Households.
     *
     * @throws ConnectionException
     */
    public function makeAvailable(Connection $connection): void
    {
        if (! $this->canBeMadeAvailable($connection)) {
            throw ConnectionException::mailDoesNotDeliver();
        }

        $this->settings->setConnectionAvailable($connection, true);
    }

    /**
     * The Instance Admin withdraws a Connection. Households keep their own switches, which apply again if it returns.
     */
    public function makeUnavailable(Connection $connection): void
    {
        $this->settings->setConnectionAvailable($connection, false);
    }

    /**
     * An Owner switches a Connection off for their Household. It is always possible, whatever the platform says.
     */
    public function disable(Connection $connection, Household $household): void
    {
        $switchedOff = HouseholdConnection::where('household_id', $household->id)->where('connection', $connection)->delete();

        if ($switchedOff > 0) {
            $this->log($connection, $household, 'disabled', Auth::id());
        }
    }

    /**
     * A change is logged with who made it and where, never with what a Household sends.
     */
    private function log(Connection $connection, Household $household, string $action, ?int $userId): void
    {
        Log::info('Household connection changed.', [
            'household_id' => $household->id,
            'connection' => $connection->value,
            'action' => $action,
            'user_id' => $userId,
        ]);
    }

    /**
     * An Owner switches a Connection on for their Household, having read what it sends and where it goes.
     *
     * @throws ConnectionException
     */
    public function confirm(Connection $connection, Household $household, User $owner): void
    {
        if (! $this->isAvailable($connection)) {
            throw ConnectionException::notAvailable();
        }

        if (! $this->canBeMadeAvailable($connection)) {
            throw ConnectionException::mailDoesNotDeliver();
        }

        HouseholdConnection::updateOrCreate(
            ['household_id' => $household->id, 'connection' => $connection],
            [
                'enabled_by_user_id' => $owner->id,
                'confirmed_destination' => $this->mail->destination(),
                'confirmed_version' => $connection->disclosureVersion(),
                'enabled_at' => now(),
            ],
        );

        $this->log($connection, $household, 'enabled', $owner->id);
    }

    /**
     * How many Households have a Connection switched on with consent that still holds, and how many switched it on
     * but must confirm again. Counts only: an Instance Admin never sees which Households.
     *
     * @return array{on: int, waiting: int}
     */
    public function householdCounts(Connection $connection): array
    {
        $switchedOn = HouseholdConnection::where('connection', $connection)->count();

        $on = HouseholdConnection::where('connection', $connection)
            ->where('confirmed_destination', $this->mail->destination())
            ->where('confirmed_version', $connection->disclosureVersion())
            ->count();

        return ['on' => $on, 'waiting' => $switchedOn - $on];
    }

    /**
     * Where a Connection stands for a Household, in the order an Owner needs to hear it: whether the Instance Admin has
     * made it available, whether anyone would receive it, and whether their own consent still holds.
     */
    public function stateFor(Connection $connection, Household $household): ConnectionState
    {
        return match (true) {
            ! $this->isAvailable($connection) => ConnectionState::Unavailable,
            ! $this->canBeMadeAvailable($connection) => ConnectionState::Paused,
            ! $this->hasSwitchedOn($connection, $household) => ConnectionState::Off,
            ! $this->consentIsCurrent($connection, $household) => ConnectionState::ConfirmAgain,
            default => ConnectionState::On,
        };
    }

    /**
     * Whether the Owner has switched the Connection on, however current their consent is.
     */
    public function hasSwitchedOn(Connection $connection, Household $household): bool
    {
        return HouseholdConnection::where('household_id', $household->id)->where('connection', $connection)->exists();
    }

    /**
     * Where Household emails go now: the SMTP host, or the mailer's name. This is what an Owner confirms.
     */
    public function destination(): string
    {
        return $this->mail->destination();
    }

    public function allows(Connection $connection, Household $household): bool
    {
        return $this->settings->connectionIsAvailable($connection)
            && $this->mail->delivers()
            && $this->consentIsCurrent($connection, $household);
    }

    /**
     * Whether the Owner has confirmed what is sent now and where it goes now. A different mail server, or a
     * disclosure that changed in a release, ends the consent until they confirm again (ADR 0007).
     */
    private function consentIsCurrent(Connection $connection, Household $household): bool
    {
        return HouseholdConnection::query()
            ->where('household_id', $household->id)
            ->where('connection', $connection)
            ->where('confirmed_destination', $this->mail->destination())
            ->where('confirmed_version', $connection->disclosureVersion())
            ->exists();
    }
}
