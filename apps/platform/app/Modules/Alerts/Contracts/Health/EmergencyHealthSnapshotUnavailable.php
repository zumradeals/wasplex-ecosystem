<?php

namespace App\Modules\Alerts\Contracts\Health;

/**
 * Réponse honnête du contrat Santé lorsque aucune capsule n'est
 * disponible (ecosystem/sante/00 §6) — jamais une capsule vide déguisée
 * en donnée réelle. Dans ce lot (P008-A), c'est la seule réponse possible :
 * aucune implémentation ne construit encore de vraie capsule.
 */
final readonly class EmergencyHealthSnapshotUnavailable
{
    public function __construct(
        public string $reason,
    ) {}
}
