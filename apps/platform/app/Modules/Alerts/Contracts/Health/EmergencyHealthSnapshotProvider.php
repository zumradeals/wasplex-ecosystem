<?php

namespace App\Modules\Alerts\Contracts\Health;

use App\Modules\Alerts\Models\AlertCase;

/**
 * Frontière fonctionnelle Alertes ↔ Santé (article 23, AMD-0016 ;
 * ecosystem/sante/00). Le seul point de contact entre les deux domaines :
 * Alertes ne lit jamais une table `health.*` directement, et ce contrat
 * n'accorde par lui-même aucun accès — la vérification de la capacité
 * d'urgence, la finalité, la durée et l'audit restent entièrement à la
 * charge de l'implémentation réelle (P009-B), non construite ici.
 */
interface EmergencyHealthSnapshotProvider
{
    public function forCase(AlertCase $case): EmergencyHealthSnapshot|EmergencyHealthSnapshotUnavailable;
}
