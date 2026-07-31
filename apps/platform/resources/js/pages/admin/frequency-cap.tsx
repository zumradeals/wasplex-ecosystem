import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';

type Bounds = {
    daily_free_view_limit: number;
    lifetime_free_view_limit: number;
    version: number;
};

export default function AdminFrequencyCap({
    access,
    bounds,
}: {
    access: AdminAccess;
    bounds: Bounds | null;
}) {
    const [current, setCurrent] = useState(bounds);
    const [dailyLimit, setDailyLimit] = useState(
        String(bounds?.daily_free_view_limit ?? 3),
    );
    const [lifetimeLimit, setLifetimeLimit] = useState(
        String(bounds?.lifetime_free_view_limit ?? 10),
    );
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<Bounds>('/admin/frequency-cap', {
            daily_free_view_limit: Number(dailyLimit),
            lifetime_free_view_limit: Number(lifetimeLimit),
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "L'enregistrement n'a pas abouti. Le total doit être supérieur ou égal au quotidien.",
            );

            return;
        }

        setCurrent(result.data);
    }

    return (
        <AdminLayout
            title="Revisionnage gratuit"
            description="Récompense unique par publicité et par personne — nombre de revisionnages gratuits ensuite autorisés, par jour et au total (advertising.manage_frequency_cap)."
        >
            <Head title="Personnel — Revisionnage gratuit" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                        <p className="mb-4 text-sm text-[var(--text-secondary)]">
                            Une personne n'est jamais récompensée deux fois pour
                            la même publicité — c'est automatique. Elle peut
                            ensuite la revoir gratuitement (sans nouvelle
                            récompense) jusqu'aux bornes ci-dessous ; au-delà,
                            la publicité disparaît de son Feed.
                        </p>

                        {current && (
                            <p className="mb-4 text-sm text-[var(--text-secondary)]">
                                Borne active (version {current.version}) :{' '}
                                <span className="font-semibold text-[var(--text-primary)]">
                                    {current.daily_free_view_limit}/jour,{' '}
                                    {current.lifetime_free_view_limit} au total
                                </span>
                            </p>
                        )}

                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-3"
                        >
                            {error && (
                                <p className="w-full text-sm text-[var(--status-danger)]">
                                    {error}
                                </p>
                            )}

                            <label className="space-y-1">
                                <span className="block text-xs font-medium text-[var(--text-primary)]">
                                    Revisionnages gratuits par jour
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    required
                                    value={dailyLimit}
                                    onChange={(event) =>
                                        setDailyLimit(event.target.value)
                                    }
                                    className="w-32 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                                />
                            </label>

                            <label className="space-y-1">
                                <span className="block text-xs font-medium text-[var(--text-primary)]">
                                    Revisionnages gratuits au total
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    required
                                    value={lifetimeLimit}
                                    onChange={(event) =>
                                        setLifetimeLimit(event.target.value)
                                    }
                                    className="w-32 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                                />
                            </label>

                            <button
                                type="submit"
                                disabled={submitting}
                                className="rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                            >
                                {submitting
                                    ? 'Enregistrement...'
                                    : 'Enregistrer une nouvelle borne'}
                            </button>
                        </form>
                    </section>
                </AdminAccessGate>
            </div>
        </AdminLayout>
    );
}
