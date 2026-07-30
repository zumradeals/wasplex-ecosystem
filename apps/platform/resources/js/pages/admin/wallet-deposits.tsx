import { Head, Link } from '@inertiajs/react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import AdminLayout from '@/layouts/admin-layout';
import { amountFormatter } from '@/lib/advertising-labels';

type QueuePagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    previous_url: string | null;
    next_url: string | null;
};

type Section<T> = {
    access: AdminAccess;
    items: T[];
    pagination: QueuePagination;
};

type DisputedDepositItem = {
    deposit_id: string;
    person_id: string;
    country_code: string;
    currency: string;
    amount: number;
    fees_amount: number | null;
    net_amount: number | null;
    provider: string;
    provider_reference: string | null;
    failure_reason: string | null;
    created_at: string;
    updated_at: string;
};

type InvalidWebhookItem = {
    webhook_event_id: string;
    provider: string;
    event_type: string | null;
    wallet_deposit_id: string | null;
    received_at: string;
    processing_result: string | null;
};

function SectionCard({
    title,
    description,
    section,
    emptyLabel,
    children,
}: {
    title: string;
    description: string;
    section: Section<unknown>;
    emptyLabel: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
            <div className="border-b border-[var(--border-default)] px-5 py-4">
                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                    {title}
                </h2>
                <p className="mt-0.5 text-xs text-[var(--text-secondary)]">
                    {description}
                </p>
            </div>
            <div className="p-5">
                <AdminAccessGate access={section.access}>
                    <>
                        {section.items.length === 0 ? (
                            <p className="text-sm text-[var(--text-secondary)]">
                                {emptyLabel}
                            </p>
                        ) : (
                            <div className="space-y-3">{children}</div>
                        )}

                        {section.pagination.last_page > 1 && (
                            <nav
                                className="mt-5 flex items-center justify-between gap-3 border-t border-[var(--border-default)] pt-4"
                                aria-label={`Pagination — ${title}`}
                            >
                                <div className="text-xs text-[var(--text-secondary)]">
                                    Page {section.pagination.current_page} sur{' '}
                                    {section.pagination.last_page} —{' '}
                                    {section.pagination.total} éléments
                                </div>
                                <div className="flex gap-2">
                                    {section.pagination.previous_url && (
                                        <Link
                                            href={
                                                section.pagination.previous_url
                                            }
                                            preserveScroll
                                            className="rounded-md border border-[var(--border-default)] px-3 py-1.5 text-xs text-[var(--text-primary)] hover:bg-[var(--bg-subtle)]"
                                        >
                                            Précédent
                                        </Link>
                                    )}
                                    {section.pagination.next_url && (
                                        <Link
                                            href={section.pagination.next_url}
                                            preserveScroll
                                            className="rounded-md border border-[var(--border-default)] px-3 py-1.5 text-xs text-[var(--text-primary)] hover:bg-[var(--bg-subtle)]"
                                        >
                                            Suivant
                                        </Link>
                                    )}
                                </div>
                            </nav>
                        )}
                    </>
                </AdminAccessGate>
            </div>
        </section>
    );
}

function DisputedDepositRow({ item }: { item: DisputedDepositItem }) {
    return (
        <div className="space-y-1.5 rounded-lg border border-[var(--status-warning)]/40 p-4">
            <div className="flex items-start justify-between gap-2">
                <p className="font-medium text-[var(--text-primary)] tabular-nums">
                    {amountFormatter.format(item.amount)} {item.currency}
                </p>
                <span className="rounded-full bg-[var(--status-warning)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-warning)]">
                    unknown_reconciliation
                </span>
            </div>
            <p className="text-xs text-[var(--text-secondary)]">
                Dépôt {item.deposit_id} — personne {item.person_id} —{' '}
                {item.provider} ({item.country_code})
            </p>
            {item.provider_reference && (
                <p className="text-xs text-[var(--text-secondary)]/70">
                    Référence prestataire : {item.provider_reference}
                </p>
            )}
            {item.failure_reason && (
                <p className="text-xs text-[var(--status-danger)]">
                    {item.failure_reason}
                </p>
            )}
            <p className="text-xs text-[var(--text-secondary)]/70">
                Créé le {new Date(item.created_at).toLocaleString('fr-FR')} —
                dernière mise à jour le{' '}
                {new Date(item.updated_at).toLocaleString('fr-FR')}
            </p>
        </div>
    );
}

function InvalidWebhookRow({ item }: { item: InvalidWebhookItem }) {
    return (
        <div className="space-y-1.5 rounded-lg border border-[var(--status-danger)]/40 p-4">
            <div className="flex items-start justify-between gap-2">
                <p className="font-medium text-[var(--text-primary)]">
                    {item.event_type ?? 'Type inconnu'} — {item.provider}
                </p>
                <span className="rounded-full bg-[var(--status-danger)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-danger)]">
                    signature invalide
                </span>
            </div>
            <p className="text-xs text-[var(--text-secondary)]">
                {item.wallet_deposit_id
                    ? `Dépôt associé : ${item.wallet_deposit_id}`
                    : 'Aucun dépôt associé identifié.'}
            </p>
            {item.processing_result && (
                <p className="text-xs text-[var(--text-secondary)]/70">
                    {item.processing_result}
                </p>
            )}
            <p className="text-xs text-[var(--text-secondary)]/70">
                Reçu le {new Date(item.received_at).toLocaleString('fr-FR')}
            </p>
        </div>
    );
}

export default function AdminWalletDeposits({
    disputedDeposits,
    invalidWebhooks,
}: {
    disputedDeposits: Section<DisputedDepositItem>;
    invalidWebhooks: Section<InvalidWebhookItem>;
}) {
    return (
        <AdminLayout
            title="Dépôts Wallet — supervision"
            description="Dépôts GeniusPay en litige et webhooks à signature invalide (TD-0008-D, ecosystem/wallet/05)."
        >
            <Head title="Personnel — Dépôts Wallet" />

            <div className="space-y-6">
                <SectionCard
                    title="Dépôts en litige"
                    description="État unknown_reconciliation — jamais présenté comme réussi ni échoué (ecosystem/wallet/05 §5). Aucune résolution automatique n'existe encore : suivi seul dans ce lot."
                    section={disputedDeposits}
                    emptyLabel="Aucun dépôt en litige."
                >
                    {disputedDeposits.items.map((item) => (
                        <DisputedDepositRow key={item.deposit_id} item={item} />
                    ))}
                </SectionCard>

                <SectionCard
                    title="Webhooks à signature invalide"
                    description="Rejetés (401) et journalisés avant tout effet métier (ecosystem/wallet/05 §4). Suivi seul dans ce lot."
                    section={invalidWebhooks}
                    emptyLabel="Aucun webhook à signature invalide."
                >
                    {invalidWebhooks.items.map((item) => (
                        <InvalidWebhookRow
                            key={item.webhook_event_id}
                            item={item}
                        />
                    ))}
                </SectionCard>
            </div>
        </AdminLayout>
    );
}
