<?php

namespace App\Modules\Alerts\Enums;

/**
 * Machine d'états d'un dossier `community` (UX-0001 §20 ; mission P008-A
 * §9). Graphe exact appliqué par le déclencheur
 * `alerts.enforce_case_state_machine` (migration
 * `..._add_alerts_state_machine_triggers`) :
 *
 *   draft                 -> submitted, withdrawn
 *   submitted             -> under_review, withdrawn
 *   under_review          -> published, restricted, rejected
 *   published             -> matched, expired, withdrawn
 *   restricted            -> matched, expired, withdrawn
 *   matched               -> restitution_scheduled, disputed
 *   restitution_scheduled -> resolved, disputed, expired
 *   disputed              -> resolved
 *   rejected, resolved, expired, withdrawn : terminaux.
 */
enum CommunityCaseState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Published = 'published';
    case Restricted = 'restricted';
    case Rejected = 'rejected';
    case Matched = 'matched';
    case RestitutionScheduled = 'restitution_scheduled';
    case Resolved = 'resolved';
    case Disputed = 'disputed';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    /**
     * Reflet PHP du graphe appliqué par le déclencheur
     * `alerts.enforce_case_state_machine` (défense en profondeur, sur le
     * modèle de `ConfigurationValueManager::assertState()`) — vérifié avant
     * l'écriture, jamais après.
     *
     * @return list<self>
     */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderReview, self::Withdrawn],
            self::UnderReview => [self::Published, self::Restricted, self::Rejected],
            self::Published => [self::Matched, self::Expired, self::Withdrawn],
            self::Restricted => [self::Matched, self::Expired, self::Withdrawn],
            self::Matched => [self::RestitutionScheduled, self::Disputed],
            self::RestitutionScheduled => [self::Resolved, self::Disputed, self::Expired],
            self::Disputed => [self::Resolved],
            self::Rejected, self::Resolved, self::Expired, self::Withdrawn => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
