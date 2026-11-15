<?php

namespace App\Actions\Instance;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeInstanceAdmin
{
    /**
     * Make the User an Instance Admin or take it away, unless that would leave the instance with nobody
     * to run it. Returns false when it was refused for that reason.
     */
    public function handle(User $user, bool $admin): bool
    {
        return DB::transaction(function () use ($user, $admin) {
            if (! $admin && $this->wouldLeaveNoAdmin($user)) {
                return false;
            }

            $user->is_instance_admin = $admin;
            $user->save();

            return true;
        });
    }

    /**
     * Whether the instance would be left without an Instance Admin if this User stopped being one.
     */
    public function wouldLeaveNoAdmin(User $user): bool
    {
        return $user->is_instance_admin
            && ! User::where('is_instance_admin', true)->whereKeyNot($user->getKey())->lockForUpdate()->exists();
    }
}
