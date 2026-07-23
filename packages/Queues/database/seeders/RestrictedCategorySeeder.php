<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RowBuddy\Queues\Infrastructure\Eloquent\RestrictedCategoryModel;

/**
 * Seeds the fixed, platform-wide category prohibitions from
 * docs/legal/restricted-queues.md as global rows (jurisdiction_country
 * null = applies to every jurisdiction), so the gate stays data-driven
 * rather than hardcoding this list into the repository/policy code.
 *
 * Two items from that document are deliberately NOT seeded here because
 * they aren't category codes: "transfer prohibited by organizer policy"
 * and "queue involving minors where safety cannot be assured" are
 * per-queue judgment calls an admin makes during moderation (Sprint 5),
 * not something an automated category gate can decide.
 */
final class RestrictedCategorySeeder extends Seeder
{
    private const UNIVERSALLY_RESTRICTED_CATEGORIES = [
        'medical_emergency',
        'elections_voting',
        'immigration_consular',
        'courts_legal_proceedings',
        'government_benefits',
        'essential_public_services',
        'school_admissions',
        'disaster_relief',
        'food_aid',
    ];

    public function run(): void
    {
        foreach (self::UNIVERSALLY_RESTRICTED_CATEGORIES as $code) {
            RestrictedCategoryModel::query()->updateOrCreate(
                ['code' => $code, 'jurisdiction_country' => null],
                ['id' => (string) Str::uuid(), 'active' => true],
            );
        }
    }
}
