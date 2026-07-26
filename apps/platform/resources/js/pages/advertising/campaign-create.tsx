import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Info } from 'lucide-react';
import { useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { FORMAT_LABELS } from '@/lib/advertising-labels';
import { postJson } from '@/lib/api';
import advertising from '@/routes/advertising';
import campaigns from '@/routes/advertising/campaigns';

type SectorOption = {
    id: string;
    label: string;
    country_code: string;
    allowed_formats: string[];
    minimum_age: number | null;
    warnings: string[];
};

// Référence encore unique et démonstrative du registre de configuration
// (AdvertisingDemoConfigurationSeeder) : aucun catalogue tarifaire
// annonceur n'existe aujourd'hui (`02-cycle-financier-campagne.md` §6 —
// « la formule exacte n'est pas codée en dur »). Fixée ici plutôt que
// choisie par vous : il n'y a rien de réel à choisir tant que ce
// catalogue n'existe pas.
const PRICING_CONFIGURATION_KEY = 'advertising.qualified_event_base_price';
const PRICING_CONFIGURATION_VERSION = 1;

export default function AdvertisingCampaignCreate({
    access,
    advertiserProfile,
    sectorClassifications,
    audienceSizeThreshold,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    sectorClassifications: SectorOption[];
    audienceSizeThreshold: number | null;
}) {
    const [code, setCode] = useState('');
    const [currency, setCurrency] = useState('XOF');
    const [sectorId, setSectorId] = useState(
        sectorClassifications[0]?.id ?? '',
    );
    const [territory, setTerritory] = useState(
        sectorClassifications[0]?.country_code ?? 'CI',
    );
    const [headline, setHeadline] = useState('');
    const [format, setFormat] = useState('');
    const [destinationUrl, setDestinationUrl] = useState('');
    const [estimatedSize, setEstimatedSize] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const sector =
        sectorClassifications.find((candidate) => candidate.id === sectorId) ??
        null;
    const belowThreshold =
        audienceSizeThreshold !== null &&
        estimatedSize > 0 &&
        estimatedSize < audienceSizeThreshold;

    async function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (!advertiserProfile) {
            return;
        }

        setSubmitting(true);
        setError(null);

        const result = await postJson(campaigns.store.url(), {
            advertiser_profile_id: advertiserProfile.id,
            code,
            currency,
            sector_classification_id: sectorId,
            creations: { headline },
            expected_event: {
                format: format || 'banner',
                condition: 'completion',
            },
            destination: { url: destinationUrl },
            territory: territory
                .split(',')
                .map((value) => value.trim().toUpperCase())
                .filter(Boolean),
            pricing_configuration_key: PRICING_CONFIGURATION_KEY,
            pricing_configuration_version: PRICING_CONFIGURATION_VERSION,
            audience: {
                criteria: {
                    country: territory
                        .split(',')
                        .map((value) => value.trim().toUpperCase())
                        .filter(Boolean),
                },
                estimated_size: estimatedSize,
            },
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La création de la campagne n'a pas abouti. Vérifiez les champs et réessayez.",
            );

            return;
        }

        router.visit(advertising.campaigns.index().url);
    }

    return (
        <AdvertiserLayout
            title="Nouvelle campagne"
            description="Créée à l'état brouillon : aucune diffusion, aucun budget engagé tant qu'elle n'est ni approuvée ni financée."
        >
            <Head title="Espace annonceur — Nouvelle campagne" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {sectorClassifications.length === 0 ? (
                    <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 text-sm text-[var(--text-secondary)]">
                        Aucun secteur actif n'est configuré : la création de
                        campagne est indisponible pour le moment.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-5 lg:col-span-2"
                        >
                            {error && (
                                <div className="rounded-lg border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-4 py-3 text-sm text-[var(--status-danger)]">
                                    {error}
                                </div>
                            )}

                            <section className="space-y-4 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                    Identité
                                </h2>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Code de la campagne">
                                        <input
                                            required
                                            value={code}
                                            onChange={(event) =>
                                                setCode(event.target.value)
                                            }
                                            className={inputClass}
                                        />
                                    </Field>
                                    <Field label="Devise (3 lettres)">
                                        <input
                                            required
                                            maxLength={3}
                                            value={currency}
                                            onChange={(event) =>
                                                setCurrency(
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className={`${inputClass} uppercase`}
                                        />
                                    </Field>
                                </div>

                                <Field label="Titre de la création">
                                    <input
                                        required
                                        value={headline}
                                        onChange={(event) =>
                                            setHeadline(event.target.value)
                                        }
                                        placeholder="Ce que vous annoncez"
                                        className={inputClass}
                                    />
                                </Field>

                                <Field label="URL de destination">
                                    <input
                                        required
                                        type="url"
                                        value={destinationUrl}
                                        onChange={(event) =>
                                            setDestinationUrl(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="https://…"
                                        className={inputClass}
                                    />
                                </Field>
                            </section>

                            <section className="space-y-4 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                    Secteur et conformité
                                </h2>

                                <Field label="Secteur">
                                    <select
                                        value={sectorId}
                                        onChange={(event) =>
                                            setSectorId(event.target.value)
                                        }
                                        className={inputClass}
                                    >
                                        {sectorClassifications.map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                {sector &&
                                    (sector.allowed_formats.length > 0 ||
                                        sector.minimum_age ||
                                        sector.warnings.length > 0) && (
                                        <div className="flex gap-2 rounded-lg bg-[var(--bg-raised)] px-3 py-2.5 text-xs text-[var(--text-secondary)]">
                                            <Info
                                                size={14}
                                                className="mt-0.5 shrink-0"
                                            />
                                            <div className="space-y-1">
                                                {sector.allowed_formats.length >
                                                    0 && (
                                                    <p>
                                                        Formats autorisés pour
                                                        ce secteur :{' '}
                                                        {sector.allowed_formats
                                                            .map(
                                                                (value) =>
                                                                    FORMAT_LABELS[
                                                                        value
                                                                    ] ?? value,
                                                            )
                                                            .join(', ')}
                                                    </p>
                                                )}
                                                {sector.minimum_age && (
                                                    <p>
                                                        Âge minimal requis :{' '}
                                                        {sector.minimum_age} ans
                                                    </p>
                                                )}
                                                {sector.warnings.length > 0 && (
                                                    <p>
                                                        Avertissements :{' '}
                                                        {sector.warnings.join(
                                                            ', ',
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                <Field label="Format de la publicité">
                                    <input
                                        value={format}
                                        onChange={(event) =>
                                            setFormat(event.target.value)
                                        }
                                        placeholder={
                                            sector?.allowed_formats[0] ??
                                            'banner'
                                        }
                                        className={inputClass}
                                    />
                                </Field>

                                <p className="text-xs text-[var(--text-secondary)]">
                                    Condition de crédit : vue complète requise —
                                    la seule condition prise en charge
                                    aujourd'hui.
                                </p>
                            </section>

                            <section className="space-y-4 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                    Audience
                                </h2>

                                <Field label="Territoires (codes pays séparés par des virgules)">
                                    <input
                                        required
                                        value={territory}
                                        onChange={(event) =>
                                            setTerritory(event.target.value)
                                        }
                                        className={inputClass}
                                    />
                                </Field>

                                <Field label="Taille d'audience estimée">
                                    <input
                                        required
                                        type="number"
                                        min={0}
                                        value={estimatedSize}
                                        onChange={(event) =>
                                            setEstimatedSize(
                                                Number(event.target.value),
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </Field>

                                {audienceSizeThreshold !== null && (
                                    <p className="text-xs text-[var(--text-secondary)]">
                                        Seuil minimal actuellement actif :{' '}
                                        {audienceSizeThreshold}. En dessous, la
                                        taille du segment ne vous sera pas
                                        communiquée (AMD-0009 §13).
                                    </p>
                                )}

                                {belowThreshold && (
                                    <div className="flex gap-2 rounded-lg bg-[var(--status-warning)]/10 px-3 py-2.5 text-xs text-[var(--status-warning)]">
                                        <AlertTriangle
                                            size={14}
                                            className="mt-0.5 shrink-0"
                                        />
                                        <p>
                                            La taille indiquée est sous le seuil
                                            minimal actif.
                                        </p>
                                    </div>
                                )}
                            </section>

                            <button
                                type="submit"
                                disabled={submitting}
                                className="w-full rounded-xl bg-[var(--brand-blue)] py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50 sm:w-auto sm:px-6"
                            >
                                {submitting
                                    ? 'Création...'
                                    : 'Créer la campagne (brouillon)'}
                            </button>
                        </form>

                        <aside className="space-y-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5 text-sm text-[var(--text-secondary)]">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                Avant de publier
                            </h2>
                            <p>La campagne est créée à l'état brouillon.</p>
                            <p>
                                Elle doit ensuite être soumise pour revue, puis
                                approuvée.
                            </p>
                            <p>
                                Elle ne peut être activée qu'une fois financée
                                intégralement.
                            </p>
                            <p>
                                Aucun gain ni disponibilité de campagne n'est
                                garanti.
                            </p>
                        </aside>
                    </div>
                )}
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}

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
