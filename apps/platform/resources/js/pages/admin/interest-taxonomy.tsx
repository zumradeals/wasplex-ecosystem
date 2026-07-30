import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';

type Entry = {
    id: string;
    code: string;
    label: string;
    state: 'active' | 'retired';
};

function AddEntryForm({ onAdded }: { onAdded: (entry: Entry) => void }) {
    const [code, setCode] = useState('');
    const [label, setLabel] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<Entry & { errors?: Record<string, string[]> }>(
            '/admin/interest-taxonomy',
            { code, label },
        );

        setSubmitting(false);

        if (!result.ok) {
            const data = result.data as { errors?: Record<string, string[]> } | null;
            setError(
                data?.errors?.code?.[0] ??
                    data?.errors?.label?.[0] ??
                    "L'ajout n'a pas abouti. Le code doit être unique, en minuscules (ex. \"sport\").",
            );

            return;
        }

        onAdded(result.data);
        setCode('');
        setLabel('');
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            {error && (
                <p className="w-full text-sm text-[var(--status-danger)]">
                    {error}
                </p>
            )}

            <label className="space-y-1">
                <span className="block text-xs font-medium text-[var(--text-primary)]">
                    Code (ex. sport)
                </span>
                <input
                    type="text"
                    required
                    value={code}
                    onChange={(event) => setCode(event.target.value)}
                    className="w-40 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                />
            </label>

            <label className="space-y-1">
                <span className="block text-xs font-medium text-[var(--text-primary)]">
                    Libellé (ex. Sport)
                </span>
                <input
                    type="text"
                    required
                    value={label}
                    onChange={(event) => setLabel(event.target.value)}
                    className="w-56 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                />
            </label>

            <button
                type="submit"
                disabled={submitting}
                className="rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
                {submitting ? 'Ajout...' : 'Ajouter'}
            </button>
        </form>
    );
}

function EntryRow({
    entry,
    onToggled,
}: {
    entry: Entry;
    onToggled: (entry: Entry) => void;
}) {
    const [submitting, setSubmitting] = useState(false);

    async function toggle() {
        setSubmitting(true);

        const result = await postJson<Entry>(
            `/admin/interest-taxonomy/${entry.id}/toggle`,
            {},
        );

        setSubmitting(false);

        if (result.ok) {
            onToggled(result.data);
        }
    }

    return (
        <div className="flex items-center justify-between rounded-lg border border-[var(--border-default)] px-4 py-3">
            <div>
                <p className="text-sm font-medium text-[var(--text-primary)]">
                    {entry.label}
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                    {entry.code}
                </p>
            </div>
            <div className="flex items-center gap-3">
                <span
                    className={
                        entry.state === 'active'
                            ? 'rounded-full bg-[var(--status-success)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-success)]'
                            : 'rounded-full bg-[var(--status-warning)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-warning)]'
                    }
                >
                    {entry.state === 'active' ? 'active' : 'retirée'}
                </span>
                <button
                    type="button"
                    onClick={toggle}
                    disabled={submitting}
                    className="rounded-md border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-subtle)] disabled:opacity-50"
                >
                    {entry.state === 'active' ? 'Retirer' : 'Réactiver'}
                </button>
            </div>
        </div>
    );
}

export default function AdminInterestTaxonomy({
    access,
    entries,
}: {
    access: AdminAccess;
    entries: Entry[];
}) {
    const [list, setList] = useState(entries);

    return (
        <AdminLayout
            title="Centres d'intérêt — profil publicitaire"
            description="Référence utilisée par les utilisateurs pour renseigner leurs centres d'intérêt (advertising.manage_interest_taxonomy)."
        >
            <Head title="Personnel — Centres d'intérêt" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <>
                        <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                            <h2 className="mb-4 text-sm font-semibold text-[var(--text-primary)]">
                                Ajouter un centre d'intérêt
                            </h2>
                            <AddEntryForm
                                onAdded={(entry) =>
                                    setList((current) => [...current, entry])
                                }
                            />
                        </section>

                        <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                            <h2 className="mb-4 text-sm font-semibold text-[var(--text-primary)]">
                                Entrées existantes
                            </h2>
                            {list.length === 0 ? (
                                <p className="text-sm text-[var(--text-secondary)]">
                                    Aucun centre d'intérêt n'est encore
                                    proposé.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {list.map((entry) => (
                                        <EntryRow
                                            key={entry.id}
                                            entry={entry}
                                            onToggled={(updated) =>
                                                setList((current) =>
                                                    current.map((item) =>
                                                        item.id === updated.id
                                                            ? updated
                                                            : item,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </>
                </AdminAccessGate>
            </div>
        </AdminLayout>
    );
}
