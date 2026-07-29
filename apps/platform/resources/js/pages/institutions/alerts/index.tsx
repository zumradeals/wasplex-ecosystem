import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InstitutionalLayout from '@/layouts/institutional-layout';
import { postJson } from '@/lib/api';
import institutions from '@/routes/institutions';

type Access = { allowed: boolean; reason: string | null };

type Organization = {
    organization_id: string;
    display_name: string;
    country_code: string;
} | null;

type Dispatch = {
    dispatch_id: string;
    case_id: string;
    category: string;
    state: string;
    territory_code: string | null;
    transmitted_at: string | null;
    received_at: string | null;
    accepted_at: string | null;
    created_at: string;
    source_description: string;
    case_nature: string;
};

const STATE_LABELS: Record<string, string> = {
    created: 'En attente de transmission',
    transmitted: 'Transmis — accusé attendu',
    received: 'Reçu',
    accepted: 'Accepté',
    processing: 'En cours de traitement',
    resolved: 'Résolu',
    unanswered: 'Sans réponse',
    refused: 'Refusé',
    transferred: 'Transféré',
    cancelled: 'Annulé',
    impossible: 'Transmission impossible',
    closed_unresolved: 'Clos sans résolution',
};

const DENIED_MESSAGES: Record<string, string> = {
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous.",
    no_institutional_membership:
        "Votre compte n'est rattaché à aucune institution affiliée active.",
};

const ACTIONS: { decision: string; label: string; requires: string }[] = [
    {
        decision: 'acknowledge',
        label: 'Accuser réception',
        requires: 'alert_case.acknowledge',
    },
    { decision: 'accept', label: 'Accepter', requires: 'alert_case.accept' },
    {
        decision: 'process',
        label: 'Marquer en traitement',
        requires: 'alert_case.process',
    },
    { decision: 'resolve', label: 'Résoudre', requires: 'alert_case.resolve' },
];

export default function InstitutionalAlertsIndex({
    access,
    organization,
    capabilities,
    dispatches,
}: {
    access: Access;
    organization: Organization;
    capabilities: string[];
    dispatches: Dispatch[];
}) {
    if (!access.allowed) {
        return (
            <InstitutionalLayout>
                <Head title="Espace institutionnel — Alertes" />
                <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4">
                    <p className="text-sm font-semibold text-[var(--status-warning)]">
                        Section indisponible
                    </p>
                    <p className="mt-1.5 text-sm text-[var(--text-secondary)]">
                        {DENIED_MESSAGES[access.reason ?? ''] ??
                            "Cette section n'est pas disponible pour votre compte."}
                    </p>
                </div>
            </InstitutionalLayout>
        );
    }

    return (
        <InstitutionalLayout organizationName={organization?.display_name}>
            <Head title="Espace institutionnel — Alertes" />

            <h1 className="mb-1 text-lg font-bold text-[var(--text-primary)]">
                Dossiers Alertes transmis
            </h1>
            <p className="mb-6 text-sm text-[var(--text-secondary)]">
                {organization?.display_name} — {organization?.country_code}
            </p>

            {dispatches.length === 0 ? (
                <div className="rounded-xl border border-dashed border-[var(--border-default)] px-6 py-14 text-center">
                    <p className="text-sm text-[var(--text-secondary)]">
                        Aucun dossier transmis à votre organisation pour
                        l'instant.
                    </p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                <th className="px-5 py-2.5 font-medium">
                                    Catégorie
                                </th>
                                <th className="px-5 py-2.5 font-medium">
                                    Description
                                </th>
                                <th className="px-5 py-2.5 font-medium">
                                    État
                                </th>
                                <th className="px-5 py-2.5 font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border-default)]">
                            {dispatches.map((dispatch) => (
                                <DispatchRow
                                    key={dispatch.dispatch_id}
                                    dispatch={dispatch}
                                    capabilities={capabilities}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </InstitutionalLayout>
    );
}

function DispatchRow({
    dispatch,
    capabilities,
}: {
    dispatch: Dispatch;
    capabilities: string[];
}) {
    const [pending, setPending] = useState(false);

    async function decide(decision: string) {
        setPending(true);

        const result = await postJson(
            institutions.alerts.dispatches.decisions.store({
                dispatch: dispatch.dispatch_id,
            }).url,
            { decision },
        );

        setPending(false);

        if (result.ok) {
            router.reload({ only: ['dispatches'] });
        } else {
            toast.error("L'action n'a pas pu être effectuée.");
        }
    }

    return (
        <tr>
            <td className="px-5 py-3 text-[var(--text-secondary)]">
                {dispatch.category}
            </td>
            <td className="max-w-xs truncate px-5 py-3 text-[var(--text-primary)]">
                {dispatch.source_description}
            </td>
            <td className="px-5 py-3 text-[var(--text-secondary)]">
                {STATE_LABELS[dispatch.state] ?? dispatch.state}
            </td>
            <td className="px-5 py-3">
                <div className="flex flex-wrap gap-2">
                    {ACTIONS.filter((action) =>
                        capabilities.includes(action.requires),
                    ).map((action) => (
                        <button
                            key={action.decision}
                            type="button"
                            disabled={pending}
                            onClick={() => decide(action.decision)}
                            className="rounded-lg bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                        >
                            {action.label}
                        </button>
                    ))}
                </div>
            </td>
        </tr>
    );
}
