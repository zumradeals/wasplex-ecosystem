<?php

namespace App\Modules\Alerts\Enums;

/**
 * Machine d'états d'une transmission institutionnelle (ecosystem/institutions/01
 * §6 ; ecosystem/alertes/03 §1.3) : « la transmission n'est pas une
 * réception ; la réception n'est pas une acceptation ; l'acceptation n'est
 * pas une intervention réussie ». Graphe appliqué par le déclencheur
 * `alerts.enforce_dispatch_state_machine`.
 */
enum DispatchState: string
{
    case Created = 'created';
    case Transmitted = 'transmitted';
    case Received = 'received';
    case Accepted = 'accepted';
    case Processing = 'processing';
    case Resolved = 'resolved';
    case Unanswered = 'unanswered';
    case Refused = 'refused';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Impossible = 'impossible';
    case ClosedUnresolved = 'closed_unresolved';

    /**
     * Reflet PHP du graphe appliqué par le déclencheur
     * `alerts.enforce_dispatch_state_machine` (défense en profondeur).
     *
     * @return list<self>
     */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Created => [self::Transmitted, self::Cancelled, self::Impossible],
            self::Transmitted => [self::Received, self::Unanswered, self::Refused, self::Impossible],
            self::Received => [self::Accepted, self::Transferred, self::Refused],
            self::Accepted => [self::Processing, self::Transferred],
            self::Processing => [self::Resolved, self::Transferred, self::ClosedUnresolved],
            self::Unanswered => [self::Transferred, self::Cancelled, self::Impossible],
            self::Refused => [self::Transferred, self::Cancelled],
            self::Transferred => [self::Cancelled],
            self::Resolved, self::Cancelled, self::Expired, self::Impossible, self::ClosedUnresolved => [],
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
