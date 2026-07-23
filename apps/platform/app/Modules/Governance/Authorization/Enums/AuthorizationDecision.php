<?php

namespace App\Modules\Governance\Authorization\Enums;

/**
 * Familles de décision adoptées par le moteur d'autorisation (P003-B1 §15).
 * Aucune autre valeur n'est ajoutée sans nouvelle décision architecturale.
 */
enum AuthorizationDecision: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';
    case StepUpRequired = 'step_up_required';
    case ApprovalRequired = 'approval_required';
    case AllowedMasked = 'allowed_masked';
    case AllowedReadOnly = 'allowed_read_only';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
