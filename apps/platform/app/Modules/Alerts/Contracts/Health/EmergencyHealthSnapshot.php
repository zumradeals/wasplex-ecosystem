<?php

namespace App\Modules\Alerts\Contracts\Health;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Capsule médicale d'urgence (article 23, AMD-0016 ; ecosystem/sante/00
 * §3). Limitée intentionnellement aux faits vitaux pour les secours —
 * jamais un dossier médical complet, jamais un historique judiciaire,
 * jamais un profil publicitaire, jamais une généalogie.
 *
 * Aucune implémentation réelle de {@see EmergencyHealthSnapshotProvider}
 * ne construit cet objet dans ce lot (P008-A) : Santé n'existe pas encore.
 * Sa forme est fixée par anticipation pour que P009-B n'ait pas à
 * réinventer ce contrat.
 */
final readonly class EmergencyHealthSnapshot
{
    public function __construct(
        public ?string $bloodType,
        /** @var list<string> */
        public array $criticalAllergies,
        /** @var list<string> */
        public array $criticalConditions,
        /** @var list<string> */
        public array $vitalTreatments,
        public ?string $emergencyContact,
        public string $provenance,
        public string $verificationLevel,
        public Carbon|CarbonImmutable $freshAsOf,
    ) {}
}
