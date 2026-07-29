<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RowBuddy\Transfers\Application\TransferExpirySweepService;

/**
 * The scheduled sweep's delivery-layer entry point (ADR-018 §3) — the
 * first Horizon-queued job in this codebase. Runs through the same
 * `redis`/Horizon queue connection every other queued dispatch in this
 * app will use, rather than executing synchronously inside the scheduler
 * process itself.
 *
 * Deliberately thin: all the actual evaluation logic lives in
 * `TransferExpirySweepService` (packages/Transfers), resolved from the
 * container like any other constructor dependency — this job only
 * triggers it.
 */
final class EvaluateTransferExpiry implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(TransferExpirySweepService $sweep): void
    {
        $sweep->sweep();
    }
}
