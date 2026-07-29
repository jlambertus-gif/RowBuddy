<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\Exceptions\DisputeAlreadyExistsForTransfer;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceRecord;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceType;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Disputes\ValueObjects\DisputeStatus;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Translates between the {@see DisputeModel}/{@see DisputeEvidenceModel}
 * Eloquent records and the {@see Dispute} domain aggregate. This is the
 * only place in the Disputes module allowed to know both shapes at once.
 */
final class EloquentDisputeRepository implements DisputeRepository
{
    public function save(Dispute $dispute): void
    {
        $refundAmount = $dispute->refundAmount();

        try {
            DisputeModel::query()->updateOrCreate(
                ['id' => $dispute->id],
                [
                    'transfer_id' => $dispute->transferId,
                    'auction_id' => $dispute->auctionId,
                    'buyer_id' => $dispute->buyerId,
                    'seller_id' => $dispute->sellerId,
                    'reason' => $dispute->reason,
                    'opened_at' => $dispute->openedAt,
                    'status' => $dispute->status()->value,
                    'resolution_outcome' => $dispute->resolutionOutcome()?->value,
                    'refund_amount_minor_units' => $refundAmount?->minorUnits,
                    'refund_amount_currency' => $refundAmount !== null ? (string) $refundAmount->currency : null,
                    'resolved_by' => $dispute->resolvedBy(),
                    'resolution_notes' => $dispute->resolutionNotes(),
                    'evidence_found_fraudulent' => $dispute->evidenceFoundFraudulent(),
                    'resolved_at' => $dispute->resolvedAt(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw DisputeAlreadyExistsForTransfer::forTransferId($dispute->transferId);
        }
    }

    public function recordEvidence(string $disputeId, DisputeEvidenceRecord $record): void
    {
        DisputeEvidenceModel::query()->create([
            'dispute_id' => $disputeId,
            'type' => $record->type->value,
            'storage_reference' => $record->storageReference,
            'submitted_by' => $record->submittedBy,
            'submitted_at' => $record->submittedAt,
        ]);
    }

    public function findById(string $id): ?Dispute
    {
        /** @var DisputeModel|null $model */
        $model = DisputeModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByTransferId(string $transferId): ?Dispute
    {
        /** @var DisputeModel|null $model */
        $model = DisputeModel::query()->where('transfer_id', $transferId)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByIdForUpdate(string $id): ?Dispute
    {
        /** @var DisputeModel|null $model */
        $model = DisputeModel::query()->lockForUpdate()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(DisputeModel $model): Dispute
    {
        return Dispute::fromPersistence(
            id: $model->id,
            transferId: $model->transfer_id,
            auctionId: $model->auction_id,
            buyerId: (string) $model->buyer_id,
            sellerId: (string) $model->seller_id,
            reason: $model->reason,
            openedAt: $model->opened_at->toDateTimeImmutable(),
            status: DisputeStatus::from($model->status),
            resolutionOutcome: $model->resolution_outcome !== null
                ? DisputeResolutionOutcome::from($model->resolution_outcome)
                : null,
            refundAmount: $this->moneyFrom($model->refund_amount_minor_units, $model->refund_amount_currency),
            resolvedBy: $model->resolved_by !== null ? (string) $model->resolved_by : null,
            resolutionNotes: $model->resolution_notes,
            evidenceFoundFraudulent: $model->evidence_found_fraudulent,
            resolvedAt: $model->resolved_at?->toDateTimeImmutable(),
            evidenceRecords: $this->evidenceRecordsFor($model->id),
        );
    }

    /**
     * @return list<DisputeEvidenceRecord>
     */
    private function evidenceRecordsFor(string $disputeId): array
    {
        /** @var iterable<DisputeEvidenceModel> $evidenceModels */
        $evidenceModels = DisputeEvidenceModel::query()
            ->where('dispute_id', $disputeId)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $records = [];

        foreach ($evidenceModels as $evidence) {
            $records[] = new DisputeEvidenceRecord(
                DisputeEvidenceType::from($evidence->type),
                $evidence->storage_reference,
                (string) $evidence->submitted_by,
                $evidence->submitted_at->toDateTimeImmutable(),
            );
        }

        return $records;
    }

    /**
     * `bigInteger`/decimal-adjacent columns can come back as `string`
     * under some drivers — accept both, mirroring
     * `EloquentTransferRepository::geoFrom()`'s identical cross-driver
     * concern.
     */
    private function moneyFrom(int|string|null $minorUnits, ?string $currency): ?Money
    {
        if ($minorUnits === null || $currency === null) {
            return null;
        }

        return new Money((int) $minorUnits, new Currency($currency));
    }
}
