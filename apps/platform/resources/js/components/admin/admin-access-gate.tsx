import type { ReactNode } from 'react';

export type AdminAccess = {
    allowed: boolean;
    reason: string | null;
};

/**
 * Wasplex n'expose jamais un motif de refus interne (grant, politique,
 * définition de capacité — ADR-0004 §"décision explicable") : seul un
 * texte destiné à la personne est affiché.
 */
const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant: 'Vous ne détenez pas ce droit sur ce compte.',
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

export function AdminAccessGate({
    access,
    children,
}: {
    access: AdminAccess;
    children: ReactNode;
}) {
    if (!access.allowed) {
        // `session_assurance_insufficient` (TD-0002-B) : une reconfirmation
        // de mot de passe pourrait suffire — jamais un simple refus, un
        // lien d'action réel (même raisonnement que StepUpNotice sur les
        // écrans moderation.tsx).
        if (access.reason === 'session_assurance_insufficient') {
            return (
                <div className="rounded-xl border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-5 py-4">
                    <p className="text-sm font-semibold text-[var(--status-danger)]">
                        Confirmation de mot de passe requise
                    </p>
                    <p className="mt-1.5 text-sm text-[var(--status-danger)]">
                        Cette section exige une session renforcée. Confirmez
                        votre mot de passe puis revenez sur cette page.{' '}
                        <a
                            href="/user/confirm-password"
                            className="underline underline-offset-2"
                        >
                            Confirmer mon mot de passe
                        </a>
                    </p>
                </div>
            );
        }

        return (
            <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4">
                <p className="text-sm font-semibold text-[var(--status-warning)]">
                    Section indisponible
                </p>
                <p className="mt-1.5 text-sm text-[var(--text-secondary)]">
                    {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                        "Cette section n'est pas disponible pour votre compte."}
                </p>
            </div>
        );
    }

    return <>{children}</>;
}
