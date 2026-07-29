import { Head } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import AdminLayout from '@/layouts/admin-layout';

type Approval = {
    approver_name: string | null;
    decision: string;
    motif: string | null;
    decided_at: string;
};

type Activation = {
    activated_by_name: string | null;
    activated_at: string;
    correlation_id: string;
};

type ValueVersion = {
    value_version_id: string;
    version: number;
    value: unknown;
    justification: string;
    state: string;
    author_name: string | null;
    created_at: string;
    approvals: Approval[];
    activation: Activation | null;
};

type DefinitionRow = {
    definition_id: string;
    stable_key: string;
    version: number;
    domain: string;
    level: string;
    value_type: string;
    unit: string | null;
    description: string;
    state: string;
    value_versions: ValueVersion[];
};

const LEVEL_LABELS: Record<string, string> = {
    c1: 'C1 — double approbation',
    c2: 'C2 — auteur et approbateur distincts',
    c3: 'C3 — approbation simple',
};

const dateFormatter = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(iso: string): string {
    return dateFormatter.format(new Date(iso));
}

export default function AdminConfigurations({
    access,
    definitions,
}: {
    access: AdminAccess;
    definitions: DefinitionRow[];
}) {
    return (
        <AdminLayout
            title="Configurations"
            description="Registre central des paramètres métier versionnés (ADR-0002), en lecture seule."
        >
            <Head title="Personnel — Configurations" />

            <AdminAccessGate access={access}>
                {definitions.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={Settings}
                        title="Aucun paramètre déclaré pour l'instant"
                        description="Aucun module métier n'a encore proposé de Definition dans le registre central de configuration."
                    />
                ) : (
                    <div className="space-y-4">
                        {definitions.map((definition) => (
                            <div
                                key={definition.definition_id}
                                className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2 border-b border-[var(--border-default)] px-5 py-3">
                                    <div>
                                        <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                            {definition.stable_key}
                                        </h2>
                                        <p className="mt-0.5 text-xs text-[var(--text-secondary)]">
                                            {definition.description}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-wrap items-center gap-2 text-xs text-[var(--text-secondary)]">
                                        <span className="rounded-full bg-[var(--bg-raised)] px-2.5 py-1 font-medium">
                                            {definition.domain}
                                        </span>
                                        <span className="rounded-full bg-[var(--bg-raised)] px-2.5 py-1 font-medium">
                                            {LEVEL_LABELS[definition.level] ??
                                                definition.level}
                                        </span>
                                        <span className="rounded-full bg-[var(--bg-raised)] px-2.5 py-1 font-medium">
                                            {definition.state}
                                        </span>
                                    </div>
                                </div>

                                {definition.value_versions.length === 0 ? (
                                    <p className="px-5 py-4 text-sm text-[var(--text-secondary)]">
                                        Aucune valeur proposée pour l'instant.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[720px] text-left text-sm">
                                            <thead>
                                                <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                                    <th className="px-5 py-2.5 font-medium">
                                                        Version
                                                    </th>
                                                    <th className="px-5 py-2.5 font-medium">
                                                        Valeur
                                                    </th>
                                                    <th className="px-5 py-2.5 font-medium">
                                                        État
                                                    </th>
                                                    <th className="px-5 py-2.5 font-medium">
                                                        Auteur
                                                    </th>
                                                    <th className="px-5 py-2.5 font-medium">
                                                        Approbations
                                                    </th>
                                                    <th className="px-5 py-2.5 font-medium">
                                                        Activation
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-[var(--border-default)]">
                                                {definition.value_versions.map(
                                                    (version) => (
                                                        <tr
                                                            key={
                                                                version.value_version_id
                                                            }
                                                        >
                                                            <td className="px-5 py-3 font-medium text-[var(--text-primary)] tabular-nums">
                                                                v
                                                                {
                                                                    version.version
                                                                }
                                                            </td>
                                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                                {String(
                                                                    version.value,
                                                                )}
                                                                {definition.unit
                                                                    ? ` ${definition.unit}`
                                                                    : ''}
                                                            </td>
                                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                                {version.state}
                                                            </td>
                                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                                {version.author_name ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                                {version
                                                                    .approvals
                                                                    .length ===
                                                                0 ? (
                                                                    '—'
                                                                ) : (
                                                                    <ul className="space-y-1">
                                                                        {version.approvals.map(
                                                                            (
                                                                                approval,
                                                                                index,
                                                                            ) => (
                                                                                <li
                                                                                    key={
                                                                                        index
                                                                                    }
                                                                                >
                                                                                    {approval.approver_name ??
                                                                                        '—'}

                                                                                    {
                                                                                        ' — '
                                                                                    }
                                                                                    {
                                                                                        approval.decision
                                                                                    }
                                                                                </li>
                                                                            ),
                                                                        )}
                                                                    </ul>
                                                                )}
                                                            </td>
                                                            <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                                {version.activation ? (
                                                                    <>
                                                                        {version
                                                                            .activation
                                                                            .activated_by_name ??
                                                                            '—'}
                                                                        <br />
                                                                        <span className="text-xs">
                                                                            {formatDate(
                                                                                version
                                                                                    .activation
                                                                                    .activated_at,
                                                                            )}
                                                                        </span>
                                                                    </>
                                                                ) : (
                                                                    '—'
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </AdminAccessGate>
        </AdminLayout>
    );
}
