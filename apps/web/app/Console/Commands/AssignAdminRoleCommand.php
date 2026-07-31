<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use RowBuddy\Administration\Contracts\AdminRoleAssignmentRepository;
use RowBuddy\Administration\ValueObjects\AdminRole;

/**
 * The documented, engineering-controlled role-assignment mechanism
 * ADR-026's Architecture Refinements §2 requires — no role-management
 * UI exists in the MVP (Decision 2), so assigning a role is deliberately
 * an operator running a command, not an admin using a page.
 *
 * Usage: php artisan admin:assign-role user@example.com administrator
 */
final class AssignAdminRoleCommand extends Command
{
    protected $signature = 'admin:assign-role {email} {role}';

    protected $description = 'Assign an administrative role to a user by email (engineering-controlled; no admin UI exists for this).';

    public function handle(AdminRoleAssignmentRepository $roles): int
    {
        $email = (string) $this->argument('email');
        $roleValue = (string) $this->argument('role');

        $role = AdminRole::tryFrom($roleValue);

        if ($role === null) {
            $valid = implode(', ', array_map(static fn (AdminRole $r): string => $r->value, AdminRole::cases()));
            $this->components->error("Invalid role [{$roleValue}]. Valid roles: {$valid}.");

            return self::FAILURE;
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $roles->assignRole((string) $user->id, $role, null);

        $this->components->info("Assigned role [{$role->value}] to [{$email}].");

        return self::SUCCESS;
    }
}
