<?php

namespace App\Modules\Wallet\Ledger\Services;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;

/**
 * Une ligne d'une intention de transaction (ADR-0003 §15). Le module
 * appelant compose déjà ses postings complets : {@see LedgerPoster} ne fait
 * jamais que les vérifier et les comptabiliser (ADR-0003 §7).
 */
final class PostingLine
{
    /**
     * @param  array<string, mixed>  $dimensions
     */
    public function __construct(
        public readonly string $accountId,
        public readonly PostingDirection $direction,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $label,
        public readonly array $dimensions = [],
    ) {}

    /**
     * Représentation canonique utilisée pour l'empreinte d'idempotence
     * (ordre des clés fixé, indépendant de l'ordre de construction).
     *
     * @return array<string, mixed>
     */
    public function toCanonicalArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'label' => $this->label,
            'dimensions' => $this->dimensions,
        ];
    }
}
