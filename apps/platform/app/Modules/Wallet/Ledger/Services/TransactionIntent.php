<?php

namespace App\Modules\Wallet\Ledger\Services;

use App\Modules\Wallet\Ledger\Services\Exceptions\DirectReversalRefusedException;
use Carbon\CarbonInterface;

/**
 * Intention de transaction déjà composée de ses postings par le module
 * appelant (ADR-0003 §7) : {@see LedgerPoster} ne compose jamais librement
 * un journal financier, il vérifie et comptabilise celui-ci.
 *
 * `reversesTransactionId` et `reversalReason` ne sont jamais renseignés par
 * un appelant ordinaire : ils ne sont posés que par
 * {@see LedgerPoster::reverse()}, qui construit lui-même l'intention
 * correspondante. {@see LedgerPoster::post()} refuse toute intention qui les
 * porterait déjà (voir {@see DirectReversalRefusedException}).
 */
final class TransactionIntent
{
    /**
     * @param  list<PostingLine>  $postings
     */
    public function __construct(
        public readonly string $type,
        public readonly CarbonInterface $businessDate,
        public readonly CarbonInterface $accountingDate,
        public readonly string $sourceModule,
        public readonly string $sourceReference,
        public readonly string $idempotencyScope,
        public readonly string $idempotencyKey,
        public readonly string $correlationId,
        public readonly string $authoredBy,
        public readonly array $postings,
        public readonly ?string $configurationKey = null,
        public readonly ?int $configurationVersion = null,
        public readonly ?string $evidenceReference = null,
        public readonly ?string $reversesTransactionId = null,
        public readonly ?string $reversalReason = null,
    ) {}

    public function withReversalOf(string $originalTransactionId, string $reason): self
    {
        return new self(
            type: $this->type,
            businessDate: $this->businessDate,
            accountingDate: $this->accountingDate,
            sourceModule: $this->sourceModule,
            sourceReference: $this->sourceReference,
            idempotencyScope: $this->idempotencyScope,
            idempotencyKey: $this->idempotencyKey,
            correlationId: $this->correlationId,
            authoredBy: $this->authoredBy,
            postings: $this->postings,
            configurationKey: $this->configurationKey,
            configurationVersion: $this->configurationVersion,
            evidenceReference: $this->evidenceReference,
            reversesTransactionId: $originalTransactionId,
            reversalReason: $reason,
        );
    }
}
