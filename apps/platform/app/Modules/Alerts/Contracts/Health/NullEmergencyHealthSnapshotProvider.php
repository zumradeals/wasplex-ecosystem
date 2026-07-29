<?php

namespace App\Modules\Alerts\Contracts\Health;

use App\Modules\Alerts\Models\AlertCase;

/**
 * Implémentation par défaut (P008-A) : Santé n'existe pas encore, donc
 * toute demande de capsule médicale d'urgence répond systématiquement par
 * une indisponibilité explicite. Alertes fonctionne intégralement sans
 * dégradation ni message trompeur en présence de ce fournisseur —
 * `AlertsServiceProvider` le lie par défaut à
 * {@see EmergencyHealthSnapshotProvider}, remplacé par une implémentation
 * réelle uniquement lorsque P009-B ouvrira le domaine Santé.
 */
final class NullEmergencyHealthSnapshotProvider implements EmergencyHealthSnapshotProvider
{
    public function forCase(AlertCase $case): EmergencyHealthSnapshotUnavailable
    {
        return new EmergencyHealthSnapshotUnavailable(reason: 'health_domain_not_available');
    }
}
