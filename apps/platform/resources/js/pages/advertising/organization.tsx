import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { postJson } from '@/lib/api';
import advertiserProfile from '@/routes/advertising/advertiser-profile';

const inputClass =
    'w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none';

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block space-y-1.5">
            <span className="text-xs font-medium text-[var(--text-primary)]">
                {label}
            </span>
            {children}
        </label>
    );
}

function AdvertiserOnboardingForm() {
    const [legalName, setLegalName] = useState('');
    const [legalIdentifier, setLegalIdentifier] = useState('');
    const [countryCode, setCountryCode] = useState('CI');
    const [territories, setTerritories] = useState('CI');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson(advertiserProfile.store.url(), {
            legal_name: legalName,
            legal_identifier: legalIdentifier || null,
            country_code: countryCode,
            territories: territories
                .split(',')
                .map((value) => value.trim().toUpperCase())
                .filter(Boolean),
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La déclaration de votre dossier annonceur n'a pas abouti. Vérifiez les champs et réessayez.",
            );

            return;
        }

        router.reload();
    }

    return (
        <div className="max-w-xl rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-6">
            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                Déclarer votre dossier annonceur
            </h2>
            <p className="mt-1 mb-5 text-sm text-[var(--text-secondary)]">
                Une seule fois : vous devenez alors représentant de ce dossier
                et pouvez créer des campagnes en votre nom.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4">
                {error && (
                    <div className="rounded-lg border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-4 py-3 text-sm text-[var(--status-danger)]">
                        {error}
                    </div>
                )}

                <Field label="Raison sociale">
                    <input
                        required
                        value={legalName}
                        onChange={(event) => setLegalName(event.target.value)}
                        className={inputClass}
                    />
                </Field>

                <Field label="Identifiant légal (optionnel)">
                    <input
                        value={legalIdentifier}
                        onChange={(event) =>
                            setLegalIdentifier(event.target.value)
                        }
                        className={inputClass}
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Pays (code ISO à 2 lettres)">
                        <input
                            required
                            maxLength={2}
                            value={countryCode}
                            onChange={(event) =>
                                setCountryCode(event.target.value.toUpperCase())
                            }
                            className={`${inputClass} uppercase`}
                        />
                    </Field>
                    <Field label="Territoires (codes séparés par des virgules)">
                        <input
                            required
                            value={territories}
                            onChange={(event) =>
                                setTerritories(event.target.value)
                            }
                            className={inputClass}
                        />
                    </Field>
                </div>

                <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                >
                    {submitting ? 'Envoi...' : 'Déclarer mon dossier'}
                </button>
            </form>
        </div>
    );
}

export default function AdvertisingOrganization({
    access,
    advertiserProfile: profile,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
}) {
    return (
        <AdvertiserLayout
            title="Organisation et accès"
            description="Votre dossier annonceur."
        >
            <Head title="Espace annonceur — Organisation et accès" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={profile}
                onMissingProfile={<AdvertiserOnboardingForm />}
            >
                {profile && (
                    <div className="max-w-xl rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-6">
                        <div className="mb-5 flex items-center justify-between">
                            <h2 className="text-base font-semibold text-[var(--text-primary)]">
                                {profile.legal_name}
                            </h2>
                            <span
                                className={
                                    profile.status === 'active'
                                        ? 'rounded-full bg-[var(--status-success)]/10 px-2.5 py-1 text-xs font-semibold text-[var(--status-success)]'
                                        : 'rounded-full bg-[var(--status-warning)]/10 px-2.5 py-1 text-xs font-semibold text-[var(--status-warning)]'
                                }
                            >
                                {profile.status === 'active'
                                    ? 'Actif'
                                    : 'Suspendu'}
                            </span>
                        </div>

                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-[var(--text-secondary)]">
                                    Pays
                                </dt>
                                <dd className="font-medium text-[var(--text-primary)]">
                                    {profile.country_code}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-[var(--text-secondary)]">
                                    Territoires
                                </dt>
                                <dd className="font-medium text-[var(--text-primary)]">
                                    {profile.territories.join(', ')}
                                </dd>
                            </div>
                        </dl>
                    </div>
                )}
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}
