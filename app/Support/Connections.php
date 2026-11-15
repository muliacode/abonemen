<?php

namespace App\Support;

use App\Enums\Connection;
use App\Exceptions\ConnectionException;
use App\Models\Household;
use App\Models\HouseholdConnection;
use App\Models\User;

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
        HouseholdConnection::where('household_id', $household->id)->where('connection', $connection)->delete();
    }

    /**
     * An Owner switches a Connection on for their Household, having read what it sends and where it goes.
     */
    public function confirm(Connection $connection, Household $household, User $owner): void
    {
        HouseholdConnection::updateOrCreate(
            ['household_id' => $household->id, 'connection' => $connection],
            [
                'enabled_by_user_id' => $owner->id,
                'confirmed_destination' => $this->mail->destination(),
                'confirmed_version' => $connection->disclosureVersion(),
                'enabled_at' => now(),
            ],
        );
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
