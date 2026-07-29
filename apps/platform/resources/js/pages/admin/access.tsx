import { Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import AdminLayout from '@/layouts/admin-layout';

type GrantRow = {
    grant_id: string;
    holder_name: string | null;
    capability_key: string;
    state: string;
    scope_type: string | null;
    valid_from: string;
    valid_until: string | null;
    author_name: string | null;
    approver_name: string | null;
    revoked_at: string | null;
    revocation_reason: string | null;
};

const STATE_LABELS: Record<string, string> = {
    proposed: 'Proposé',
    active: 'Actif',
    suspended: 'Suspendu',
    expired: 'Expiré',
    revoked: 'Révoqué',
};

const dateFormatter = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(iso: string): string {
    return dateFormatter.format(new Date(iso));
}

export default function AdminAccess({
    access,
    grants,
}: {
    access: AdminAccess;
    grants: GrantRow[];
}) {
    return (
        <AdminLayout
            title="Accès"
            description="Registre des habilitations (ADR-0004), en lecture seule."
        >
            <Head title="Personnel — Accès" />

            <AdminAccessGate access={access}>
                {grants.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={KeyRound}
                        title="Aucune habilitation à afficher"
                        description="Les habilitations accordées apparaîtront ici dès leur activation."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                        <div className="border-b border-[var(--border-default)] px-5 py-3">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                Habilitations
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead>
                                    <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                        <th className="px-5 py-2.5 font-medium">
                                            Titulaire
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Capacité
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            État
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Validité
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Auteur / Approbateur
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Révocation
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--border-default)]">
                                    {grants.map((grant) => (
                                        <tr key={grant.grant_id}>
                                            <td className="px-5 py-3 font-medium text-[var(--text-primary)]">
                                                {grant.holder_name ?? '—'}
                                            </td>
                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                {grant.capability_key}
                                                {grant.scope_type && (
                                                    <span className="block text-xs text-[var(--text-secondary)]/70">
                                                        {grant.scope_type}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                {STATE_LABELS[grant.state] ??
                                                    grant.state}
                                            </td>
                                            <td className="px-5 py-3 text-xs text-[var(--text-secondary)]">
                                                {formatDate(grant.valid_from)}
                                                {' → '}
                                                {grant.valid_until
                                                    ? formatDate(
                                                          grant.valid_until,
                                                      )
                                                    : 'sans échéance'}
                                            </td>
                                            <td className="px-5 py-3 text-xs text-[var(--text-secondary)]">
                                                {grant.author_name ?? '—'}
                                                {' / '}
                                                {grant.approver_name ?? '—'}
                                            </td>
                                            <td className="px-5 py-3 text-xs text-[var(--text-secondary)]">
                                                {grant.revoked_at ? (
                                                    <>
                                                        {formatDate(
                                                            grant.revoked_at,
                                                        )}
                                                        {grant.revocation_reason && (
                                                            <span className="block">
                                                                {
                                                                    grant.revocation_reason
                                                                }
                                                            </span>
                                                        )}
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </AdminAccessGate>
        </AdminLayout>
    );
}
