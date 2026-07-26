import { useState } from 'react';
import { postJson } from '@/lib/api';
import moderationCaseDecisions from '@/routes/advertising/moderation-cases/decisions';

/**
 * Décision d'un `ModerationCase` ouvert (`campaign.moderate`,
 * `03-signalements-sanctions-et-remuneration.md` §1-2) : un signalement
 * seul ne prouve jamais la violation, d'où les deux issues possibles
 * seulement (jamais un état intermédiaire, cf.
 * {@see \App\Modules\Advertising\Enums\ModerationDecision}). Partagé entre
 * « À traiter » (file des dossiers ouverts) et « Publicité et
 * modération » (historique complet).
 */
export function ModerationCaseDecisionForm({
    moderationCaseId,
    onDecided,
}: {
    moderationCaseId: string;
    onDecided: () => void;
}) {
    const [measure, setMeasure] = useState('none');
    const [submitting, setSubmitting] = useState<'confirm' | 'dismiss' | null>(
        null,
    );
    const [error, setError] = useState<string | null>(null);

    async function decide(
        decision: 'violation_confirmed' | 'no_violation_found',
    ) {
        setSubmitting(
            decision === 'violation_confirmed' ? 'confirm' : 'dismiss',
        );
        setError(null);

        const result = await postJson(
            moderationCaseDecisions.store.url(moderationCaseId),
            {
                decision,
                precautionary_measure:
                    decision === 'violation_confirmed' ? measure : 'none',
            },
        );

        setSubmitting(null);

        if (!result.ok) {
            setError("La décision n'a pas abouti.");

            return;
        }

        onDecided();
    }

    return (
        <div className="flex flex-wrap items-end gap-2">
            {error && (
                <p className="w-full text-sm text-[var(--status-danger)]">
                    {error}
                </p>
            )}

            <label className="space-y-1">
                <span className="block text-xs font-medium text-[var(--text-primary)]">
                    Mesure conservatoire si violation confirmée
                </span>
                <select
                    value={measure}
                    onChange={(event) => setMeasure(event.target.value)}
                    className="rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                >
                    <option value="none">Aucune</option>
                    <option value="limited_diffusion">Diffusion limitée</option>
                    <option value="campaign_suspended">
                        Campagne suspendue
                    </option>
                    <option value="destination_blocked">
                        Destination bloquée
                    </option>
                    <option value="advertiser_blocked">Annonceur bloqué</option>
                </select>
            </label>

            <button
                type="button"
                onClick={() => decide('no_violation_found')}
                disabled={submitting !== null}
                className="rounded-lg border border-[var(--border-default)] px-3 py-2 text-sm font-medium text-[var(--text-primary)] hover:bg-[var(--bg-raised)] disabled:opacity-50"
            >
                {submitting === 'dismiss'
                    ? 'Envoi...'
                    : 'Aucune violation constatée'}
            </button>
            <button
                type="button"
                onClick={() => decide('violation_confirmed')}
                disabled={submitting !== null}
                className="rounded-lg bg-[var(--status-danger)] px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
                {submitting === 'confirm'
                    ? 'Envoi...'
                    : 'Confirmer la violation'}
            </button>
        </div>
    );
}
