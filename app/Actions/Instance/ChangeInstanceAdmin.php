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
     *
     * Call it inside a transaction. It locks every Instance Admin row, this User's too: with only the others locked,
     * two admins demoting each other would lock different rows, both pass, and leave nobody to run the instance.
     */
    public function wouldLeaveNoAdmin(User $user): bool
    {
        $admins = User::where('is_instance_admin', true)->lockForUpdate()->pluck('id');

        return $user->is_instance_admin && $admins->diff([$user->getKey()])->isEmpty();
    }
}
