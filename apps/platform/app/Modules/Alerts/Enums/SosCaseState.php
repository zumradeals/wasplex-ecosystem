<?php

namespace App\Modules\Alerts\Enums;

/**
 * Machine d'états d'un dossier `sos` (ecosystem/alertes/02 §3, table
 * exacte reprise sans variante). Graphe appliqué par le déclencheur
 * `alerts.enforce_case_state_machine` :
 *
 *   created      -> transmitted, cancelled, impossible
 *   transmitted  -> received, unanswered, refused, impossible
 *   received     -> accepted, transferred, refused
 *   accepted     -> processing, transferred
 *   processing   -> resolved, transferred, disputed, closed_unresolved
 *   unanswered   -> transferred, cancelled, impossible
 *   refused      -> transferred, cancelled
 *   transferred  -> transmitted, resolved, closed_unresolved
 *   disputed     -> resolved, closed_unresolved
 *   resolved, cancelled, impossible, closed_unresolved : terminaux.
 */
enum SosCaseState: string
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
    case Impossible = 'impossible';
    case Disputed = 'disputed';
    case ClosedUnresolved = 'closed_unresolved';

    /**
     * Reflet PHP du graphe appliqué par le déclencheur
     * `alerts.enforce_case_state_machine` (défense en profondeur).
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
            self::Processing => [self::Resolved, self::Transferred, self::Disputed, self::ClosedUnresolved],
            self::Unanswered => [self::Transferred, self::Cancelled, self::Impossible],
            self::Refused => [self::Transferred, self::Cancelled],
            self::Transferred => [self::Transmitted, self::Resolved, self::ClosedUnresolved],
            self::Disputed => [self::Resolved, self::ClosedUnresolved],
            self::Resolved, self::Cancelled, self::Impossible, self::ClosedUnresolved => [],
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
