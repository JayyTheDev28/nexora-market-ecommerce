<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ApproveUser extends Command
{
    /**
     * There's no admin dashboard yet to do this from the UI, so this
     * command stands in for "an admin approves the application" while
     * testing the registration -> verification -> approval -> login flow.
     */
    protected $signature = 'user:approve
        {email : The email address of the account to update}
        {--disapprove : Mark as disapproved instead of approved}
        {--reason= : Reason shown to the user, used with --disapprove}';

    protected $description = 'Manually approve or disapprove a Nexora account (stand-in for the admin dashboard)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}");
            return self::FAILURE;
        }

        if ($this->option('disapprove')) {
            $user->update([
                'approval_status' => 'disapproved',
                'disapproval_reason' => $this->option('reason'),
            ]);
            $this->info("Disapproved {$user->email}.");
            return self::SUCCESS;
        }

        $user->update(['approval_status' => 'approved']);
        $this->info("Approved {$user->email} ({$user->role}). They can now log in.");

        return self::SUCCESS;
    }
}
