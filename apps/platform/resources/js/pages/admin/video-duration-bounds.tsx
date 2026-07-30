import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';

type Bounds = {
    min_seconds: number;
    max_seconds: number;
    version: number;
};

export default function AdminVideoDurationBounds({
    access,
    bounds,
}: {
    access: AdminAccess;
    bounds: Bounds | null;
}) {
    const [current, setCurrent] = useState(bounds);
    const [minSeconds, setMinSeconds] = useState(
        String(bounds?.min_seconds ?? 30),
    );
    const [maxSeconds, setMaxSeconds] = useState(
        String(bounds?.max_seconds ?? 60),
    );
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<Bounds>('/admin/video-duration-bounds', {
            min_seconds: Number(minSeconds),
            max_seconds: Number(maxSeconds),
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "L'enregistrement n'a pas abouti. Le maximum doit être supérieur ou égal au minimum.",
            );

            return;
        }

        setCurrent(result.data);
    }

    return (
        <AdminLayout
            title="Durée des vidéos publicitaires"
            description="Bornes de durée (secondes) autorisées pour une vidéo jointe à une création de campagne (advertising.manage_video_duration_bounds)."
        >
            <Head title="Personnel — Durée des vidéos publicitaires" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                        {current && (
                            <p className="mb-4 text-sm text-[var(--text-secondary)]">
                                Borne active (version {current.version}) :{' '}
                                <span className="font-semibold text-[var(--text-primary)]">
                                    {current.min_seconds}–
                                    {current.max_seconds} secondes
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
                                    Minimum (secondes)
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    required
                                    value={minSeconds}
                                    onChange={(event) =>
                                        setMinSeconds(event.target.value)
                                    }
                                    className="w-32 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                                />
                            </label>

                            <label className="space-y-1">
                                <span className="block text-xs font-medium text-[var(--text-primary)]">
                                    Maximum (secondes)
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    required
                                    value={maxSeconds}
                                    onChange={(event) =>
                                        setMaxSeconds(event.target.value)
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
