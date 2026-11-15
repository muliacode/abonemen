<?php

namespace Tests\Unit;

use App\Mail\HouseholdMail;
use PHPUnit\Framework\TestCase;

/**
 * An email that carries a Household's data must go through the email Connection's gate, and the gate lives in
 * HouseholdMail. A new email class that skips it fails here, unless it is added to the list of account emails on
 * purpose, with the reason. See ADR 0007.
 */
class HouseholdMailArchitectureTest extends TestCase
{
    /**
     * Emails that carry no Household data. They depend on the mail setup alone, not on a Household's consent.
     *
     * @var array<string, string>
     */
    private const ACCOUNT_EMAILS = [
        'TestMail' => 'The test email an Instance Admin sends to themselves.',
    ];

    /**
     * @return list<string> Short class names in app/Mail.
     */
    private function mailClasses(): array
    {
        $names = [];

        foreach (glob(dirname(__DIR__, 2).'/app/Mail/*.php') as $file) {
            $names[] = basename($file, '.php');
        }

        return $names;
    }

    public function test_every_email_extends_household_mail_or_is_a_listed_account_email(): void
    {
        $skippingTheGate = [];

        foreach ($this->mailClasses() as $name) {
            $class = "App\\Mail\\{$name}";

            if ($class === HouseholdMail::class || array_key_exists($name, self::ACCOUNT_EMAILS)) {
                continue;
            }

            if (! is_subclass_of($class, HouseholdMail::class)) {
                $skippingTheGate[] = $class;
            }
        }

        $this->assertSame([], $skippingTheGate, 'These emails skip the email Connection\'s gate. Extend HouseholdMail, or list them as account emails if they carry no Household data.');
    }

    public function test_the_list_of_account_emails_has_no_stale_entries(): void
    {
        foreach (array_keys(self::ACCOUNT_EMAILS) as $name) {
            $this->assertContains($name, $this->mailClasses(), "{$name} is listed as an account email but no longer exists in app/Mail.");
        }
    }
}
