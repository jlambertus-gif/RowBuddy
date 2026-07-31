<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use RowBuddy\Administration\ValueObjects\AdminCapability;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => fn () => $request->user() === null ? null : [
                    ...$request->user()->only(['id', 'name', 'email', 'email_verified_at']),
                    // Computed via the capability-based Gate (ADR-026 §2/
                    // Architecture Refinements §1) rather than a raw
                    // is_admin column, which no longer exists — the
                    // frontend's own contract (this prop name) is
                    // deliberately unchanged.
                    'is_admin' => Gate::forUser($request->user())->allows(AdminCapability::QueuesModerate->value),
                    // Sprint 4 (ADR-026 §3): a dedicated capability check,
                    // not folded into is_admin above — dispute review is a
                    // distinct capability from queue moderation, not a
                    // generic "is this user any kind of admin" flag.
                    'can_review_disputes' => Gate::forUser($request->user())->allows(AdminCapability::DisputesReview->value),
                ],
            ],

            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],

            'locale' => [
                'current' => app()->getLocale(),
                'fallback' => config('app.fallback_locale'),
            ],
        ];
    }
}