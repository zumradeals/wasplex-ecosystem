import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';
import admin from '@/routes/admin';

type Section<T> = {
    access: AdminAccess;
    items: T[];
};

type CaseReviewItem = {
    case_id: string;
    category: string;
    state: string;
    requires_reinforced_review: boolean;
    source_description: string;
    created_at: string;
};

type SosItem = {
    case_id: string;
    category: string;
    state: string;
    source_description: string;
    recall_phone: string | null;
    created_at: string;
};

type CorrespondenceItem = {
    correspondence_report_id: string;
    case_id: string;
    case_category: string;
    non_public_description: string;
    created_at: string;
};

type RestitutionDisputeItem = {
    restitution_id: string;
    case_id: string;
    case_category: string;
    dispute_reason: string | null;
    created_at: string;
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
                    {section.items.length === 0 ? (
                        <p className="text-sm text-[var(--text-secondary)]">
                            {emptyLabel}
                        </p>
                    ) : (
                        <div className="space-y-3">{children}</div>
                    )}
                </AdminAccessGate>
            </div>
        </section>
    );
}

function CaseReviewRow({ item }: { item: CaseReviewItem }) {
    const [title, setTitle] = useState('');
    const [summary, setSummary] = useState('');
    const [pending, setPending] = useState(false);

    async function decide(
        decision: 'start_review' | 'publish' | 'restrict' | 'reject',
    ) {
        setPending(true);

        const result = await postJson(
            admin.alerts.cases.decisions.store({ case: item.case_id }).url,
            {
                decision,
                title: decision === 'publish' ? title : undefined,
                summary: decision === 'publish' ? summary : undefined,
                reason:
                    decision === 'restrict' || decision === 'reject'
                        ? 'Décision de modération.'
                        : undefined,
            },
        );

        setPending(false);

        if (result.ok) {
            router.reload({ only: ['caseReview'] });
        } else {
            toast.error("La décision n'a pas pu être enregistrée.");
        }
    }

    return (
        <div className="space-y-2 rounded-lg border border-[var(--border-default)] p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-medium text-[var(--text-primary)]">
                        {item.category}
                        {item.requires_reinforced_review && (
                            <span className="ml-2 rounded-full bg-[var(--status-warning)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-warning)]">
                                Revue renforcée requise
                            </span>
                        )}
                    </p>
                    <p className="text-sm text-[var(--text-secondary)]">
                        {item.source_description}
                    </p>
                    <p className="text-xs text-[var(--text-secondary)]/70">
                        État : {item.state}
                    </p>
                </div>
            </div>

            {item.state === 'submitted' && (
                <button
                    type="button"
                    disabled={pending}
                    onClick={() => decide('start_review')}
                    className="rounded-lg bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                >
                    Passer en revue
                </button>
            )}

            {item.state === 'under_review' && (
                <div className="space-y-2">
                    <input
                        value={title}
                        onChange={(event) => setTitle(event.target.value)}
                        placeholder="Titre public"
                        className="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-1.5 text-sm"
                    />
                    <textarea
                        value={summary}
                        onChange={(event) => setSummary(event.target.value)}
                        placeholder="Résumé public"
                        rows={2}
                        className="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-1.5 text-sm"
                    />
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={pending || title === '' || summary === ''}
                            onClick={() => decide('publish')}
                            className="rounded-lg bg-[var(--status-success)] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                        >
                            Publier
                        </button>
                        <button
                            type="button"
                            disabled={pending}
                            onClick={() => decide('restrict')}
                            className="rounded-lg border border-[var(--border-default)] px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary)] disabled:opacity-50"
                        >
                            Restreindre
                        </button>
                        <button
                            type="button"
                            disabled={pending}
                            onClick={() => decide('reject')}
                            className="rounded-lg border border-[var(--status-danger)]/40 px-3 py-1.5 text-xs font-semibold text-[var(--status-danger)] disabled:opacity-50"
                        >
                            Refuser
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function CorrespondenceRow({ item }: { item: CorrespondenceItem }) {
    const [pending, setPending] = useState(false);

    async function decide(decision: 'validate' | 'reject') {
        setPending(true);

        const result = await postJson(
            admin.alerts.correspondenceReports.decisions.store({
                correspondenceReport: item.correspondence_report_id,
            }).url,
            { decision },
        );

        setPending(false);

        if (result.ok) {
            router.reload({ only: ['correspondenceValidation'] });
        } else {
            toast.error("La décision n'a pas pu être enregistrée.");
        }
    }

    return (
        <div className="space-y-2 rounded-lg border border-[var(--border-default)] p-4">
            <p className="font-medium text-[var(--text-primary)]">
                {item.case_category}
            </p>
            <p className="text-sm text-[var(--text-secondary)]">
                {item.non_public_description}
            </p>
            <div className="flex gap-2">
                <button
                    type="button"
                    disabled={pending}
                    onClick={() => decide('validate')}
                    className="rounded-lg bg-[var(--status-success)] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                >
                    Valider la correspondance
                </button>
                <button
                    type="button"
                    disabled={pending}
                    onClick={() => decide('reject')}
                    className="rounded-lg border border-[var(--status-danger)]/40 px-3 py-1.5 text-xs font-semibold text-[var(--status-danger)] disabled:opacity-50"
                >
                    Rejeter
                </button>
            </div>
        </div>
    );
}

export default function AdminAlerts({
    caseReview,
    sosSupervision,
    correspondenceValidation,
    restitutionDisputes,
}: {
    caseReview: Section<CaseReviewItem>;
    sosSupervision: Section<SosItem>;
    correspondenceValidation: Section<CorrespondenceItem>;
    restitutionDisputes: Section<RestitutionDisputeItem>;
}) {
    return (
        <AdminLayout
            title="Alertes et institutions"
            description="Revue communautaire, supervision SOS, correspondances et contestations de restitution."
        >
            <Head title="Personnel — Alertes et institutions" />

            <div className="space-y-6">
                <SectionCard
                    title="Dossiers communautaires à revoir"
                    description="Revue, publication, restriction ou refus (alert_case.review / alert_case.publish)."
                    section={caseReview}
                    emptyLabel="Aucun dossier en attente."
                >
                    {caseReview.items.map((item) => (
                        <CaseReviewRow key={item.case_id} item={item} />
                    ))}
                </SectionCard>

                <SectionCard
                    title="SOS non routés ou en échec"
                    description="Dossiers sans institution éligible ou sans réponse — supervision seule dans ce lot."
                    section={sosSupervision}
                    emptyLabel="Aucun SOS en supervision."
                >
                    {sosSupervision.items.map((item) => (
                        <div
                            key={item.case_id}
                            className="rounded-lg border border-[var(--border-default)] p-4"
                        >
                            <p className="font-medium text-[var(--text-primary)]">
                                {item.category} — {item.state}
                            </p>
                            <p className="text-sm text-[var(--text-secondary)]">
                                {item.source_description}
                            </p>
                            {item.recall_phone && (
                                <p className="text-xs text-[var(--text-secondary)]/70">
                                    Rappel : {item.recall_phone}
                                </p>
                            )}
                        </div>
                    ))}
                </SectionCard>

                <SectionCard
                    title="Correspondances à valider"
                    description="Une correspondance automatisée reste une hypothèse — décision humaine requise (AMD-0007 §9)."
                    section={correspondenceValidation}
                    emptyLabel="Aucune correspondance en attente."
                >
                    {correspondenceValidation.items.map((item) => (
                        <CorrespondenceRow
                            key={item.correspondence_report_id}
                            item={item}
                        />
                    ))}
                </SectionCard>

                <SectionCard
                    title="Contestations de restitution"
                    description="Suivi seul dans ce lot — aucune action de résolution de litige n'est encore construite."
                    section={restitutionDisputes}
                    emptyLabel="Aucune contestation en cours."
                >
                    {restitutionDisputes.items.map((item) => (
                        <div
                            key={item.restitution_id}
                            className="rounded-lg border border-[var(--border-default)] p-4"
                        >
                            <p className="font-medium text-[var(--text-primary)]">
                                {item.case_category}
                            </p>
                            <p className="text-sm text-[var(--text-secondary)]">
                                {item.dispute_reason}
                            </p>
                        </div>
                    ))}
                </SectionCard>
            </div>
        </AdminLayout>
    );
}
