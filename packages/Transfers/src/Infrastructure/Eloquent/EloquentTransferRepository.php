<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Exceptions\TransferAlreadyIssuedForAuction;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceRecord;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * Translates between the {@see TransferModel}/{@see TransferEvidenceModel}
 * Eloquent records and the {@see Transfer} domain aggregate. This is the
 * only place in the Transfers module allowed to know both shapes at once.
 */
final class EloquentTransferRepository implements TransferRepository
{
    public function save(Transfer $transfer): void
    {
        $sellerGeo = $transfer->sellerConfirmedGeo();
        $buyerGeo = $transfer->buyerConfirmedGeo();

        try {
            TransferModel::query()->updateOrCreate(
                ['id' => $transfer->id],
                [
                    'auction_id' => $transfer->auctionId,
                    'winning_bid_id' => $transfer->winningBidId,
                    'seller_id' => $transfer->sellerId,
                    'buyer_id' => $transfer->buyerId,
                    'qr_token_hash' => $transfer->qrTokenHash,
                    'issued_at' => $transfer->issuedAt,
                    'expires_at' => $transfer->expiresAt,
                    'status' => $transfer->status()->value,
                    'seller_confirmed_at' => $transfer->sellerConfirmedAt(),
                    'seller_confirmed_latitude' => $sellerGeo?->latitude,
                    'seller_confirmed_longitude' => $sellerGeo?->longitude,
                    'buyer_confirmed_at' => $transfer->buyerConfirmedAt(),
                    'buyer_confirmed_latitude' => $buyerGeo?->latitude,
                    'buyer_confirmed_longitude' => $buyerGeo?->longitude,
                    'confirmed_at' => $transfer->confirmedAt(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw TransferAlreadyIssuedForAuction::forAuctionId($transfer->auctionId);
        }
    }

    public function recordEvidence(string $transferId, TransferEvidenceRecord $record): void
    {
        TransferEvidenceModel::query()->create([
            'transfer_id' => $transferId,
            'type' => $record->type->value,
            'storage_reference' => $record->storageReference,
            'submitted_by' => $record->submittedBy,
            'submitted_at' => $record->submittedAt,
        ]);
    }

    public function findById(string $id): ?Transfer
    {
        /** @var TransferModel|null $model */
        $model = TransferModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByAuctionId(string $auctionId): ?Transfer
    {
        /** @var TransferModel|null $model */
        $model = TransferModel::query()->where('auction_id', $auctionId)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByIdForUpdate(string $id): ?Transfer
    {
        /** @var TransferModel|null $model */
        $model = TransferModel::query()->lockForUpdate()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(TransferModel $model): Transfer
    {
        return Transfer::fromPersistence(
            id: $model->id,
            auctionId: $model->auction_id,
            winningBidId: $model->winning_bid_id,
            sellerId: (string) $model->seller_id,
            buyerId: (string) $model->buyer_id,
            qrTokenHash: $model->qr_token_hash,
            issuedAt: $model->issued_at->toDateTimeImmutable(),
            expiresAt: $model->expires_at->toDateTimeImmutable(),
            status: TransferStatus::from($model->status),
            sellerConfirmedAt: $model->seller_confirmed_at?->toDateTimeImmutable(),
            sellerConfirmedGeo: $this->geoFrom($model->seller_confirmed_latitude, $model->seller_confirmed_longitude),
            buyerConfirmedAt: $model->buyer_confirmed_at?->toDateTimeImmutable(),
            buyerConfirmedGeo: $this->geoFrom($model->buyer_confirmed_latitude, $model->buyer_confirmed_longitude),
            confirmedAt: $model->confirmed_at?->toDateTimeImmutable(),
            evidenceRecords: $this->evidenceRecordsFor($model->id),
        );
    }

    /**
     * @return list<TransferEvidenceRecord>
     */
    private function evidenceRecordsFor(string $transferId): array
    {
        /** @var iterable<TransferEvidenceModel> $evidenceModels */
        $evidenceModels = TransferEvidenceModel::query()
            ->where('transfer_id', $transferId)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $records = [];

        foreach ($evidenceModels as $evidence) {
            $records[] = new TransferEvidenceRecord(
                TransferEvidenceType::from($evidence->type),
                $evidence->storage_reference,
                (string) $evidence->submitted_by,
                $evidence->submitted_at->toDateTimeImmutable(),
            );
        }

        return $records;
    }

    /**
     * `decimal` columns come back as `string` under Postgres but as
     * native `float` under SQLite (no fixed-point type) — accept both so
     * this repository behaves identically against apps/web's real
     * Postgres database and this package's own SQLite-backed tests.
     */
    private function geoFrom(float|string|null $latitude, float|string|null $longitude): ?GeoPoint
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return new GeoPoint((float) $latitude, (float) $longitude);
    }
}
