import { Head, router } from '@inertiajs/react';
import { Image as ImageIcon, Info } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { FORMAT_LABELS } from '@/lib/advertising-labels';
import { postFormData, postJson } from '@/lib/api';
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

// Lot 3 (véto du dirigeant) : construit `audience.criteria` à partir des
// champs de ciblage — fonction pure, hors du composant, pour rester
// utilisable telle quelle à la fois dans l'aperçu en direct (useEffect) et
// à la soumission, sans dépendance instable dans un tableau de deps.
function buildAudienceCriteria(params: {
    targetCountry: string;
    targetCity: string;
    targetNeighborhood: string;
    ageBrackets: string[];
    genders: string[];
    interests: string[];
}): Record<string, unknown> {
    const criteria: Record<string, unknown> = {};

    const countryCodes = params.targetCountry
        .split(',')
        .map((value) => value.trim().toUpperCase())
        .filter(Boolean);

    if (countryCodes.length > 0) {
        criteria.country = countryCodes;
    }

    if (params.targetCity.trim()) {
        criteria.city = params.targetCity.trim();
    }

    if (params.targetNeighborhood.trim()) {
        criteria.neighborhood = params.targetNeighborhood.trim();
    }

    if (params.ageBrackets.length > 0) {
        criteria.age_bracket = params.ageBrackets;
    }

    if (params.genders.length > 0) {
        criteria.gender = params.genders;
    }

    if (params.interests.length > 0) {
        criteria.interests = params.interests;
    }

    return criteria;
}

// Lot 5 : jamais l'URL brute complète dans l'aperçu — seul le domaine,
// plus proche de ce qu'affiche réellement un réseau publicitaire.
// `—` si vide ou invalide, jamais une valeur devinée.
function destinationHost(url: string): string {
    if (!url.trim()) {
        return '—';
    }

    try {
        return new URL(url).hostname || '—';
    } catch {
        return '—';
    }
}

const AGE_BRACKETS = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

const GENDERS: Record<string, string> = {
    woman: 'Femme',
    man: 'Homme',
    other: 'Autre',
    prefer_not_to_say: 'Je préfère ne pas préciser',
};

type InterestOption = { code: string; label: string };

type AudienceEstimate = {
    estimated_size: number | null;
    below_threshold: boolean;
};

type VideoDurationBounds = { min_seconds: number; max_seconds: number };

type VideoUploadResult = {
    path: string;
    url: string;
    duration_seconds: number;
};

export default function AdvertisingCampaignCreate({
    access,
    advertiserProfile,
    sectorClassifications,
    audienceSizeThreshold,
    interestTaxonomy,
    videoDurationBounds,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    sectorClassifications: SectorOption[];
    audienceSizeThreshold: number | null;
    interestTaxonomy: InterestOption[];
    videoDurationBounds: VideoDurationBounds | null;
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

    // Lot 3 (véto du dirigeant) : ciblage réel — pays cible (indépendant
    // du territoire de diffusion ci-dessus, qui reste une contrainte
    // légale/sectorielle), ville et quartier (texte libre, comme le
    // profil publicitaire), tranche d'âge/genre/centres d'intérêt à
    // cocher. `estimatedSize` n'est plus saisi : calculé par le serveur.
    const [targetCountry, setTargetCountry] = useState('');
    const [targetCity, setTargetCity] = useState('');
    const [targetNeighborhood, setTargetNeighborhood] = useState('');
    const [ageBrackets, setAgeBrackets] = useState<string[]>([]);
    const [genders, setGenders] = useState<string[]>([]);
    const [interests, setInterests] = useState<string[]>([]);
    const [estimate, setEstimate] = useState<AudienceEstimate | null>(null);
    const [estimating, setEstimating] = useState(false);

    // Lot 4 (instruction explicite du fondateur 2026-07-30) : upload
    // immédiat dès sélection du fichier (mirroir du flux GeniusPay :
    // upload d'abord, référence ensuite) — jamais rattaché à une
    // campagne tant que la création elle-même n'est pas soumise.
    const [video, setVideo] = useState<VideoUploadResult | null>(null);
    const [videoUploading, setVideoUploading] = useState(false);
    const [videoError, setVideoError] = useState<string | null>(null);

    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const sector =
        sectorClassifications.find((candidate) => candidate.id === sectorId) ??
        null;

    const criteria = buildAudienceCriteria({
        targetCountry,
        targetCity,
        targetNeighborhood,
        ageBrackets,
        genders,
        interests,
    });
    const hasCriteria = Object.keys(criteria).length > 0;

    // Lot 5 (véto du dirigeant) : aperçu en direct, mirroir du rendu réel du
    // Feed (dashboard.tsx) — dérivé de l'état déjà présent, jamais un
    // nouveau champ ni un appel serveur supplémentaire.
    const previewFormat = format || sector?.allowed_formats[0] || 'banner';
    const previewFormatLabel = FORMAT_LABELS[previewFormat] ?? previewFormat;
    const previewDestinationHost = destinationHost(destinationUrl);

    useEffect(() => {
        // `hasCriteria` gouverne déjà l'affichage ci-dessous (le message
        // "aucun critère" prime sur `estimate` obsolète) : pas besoin de
        // réinitialiser l'état ici, ce qui éviterait un rendu en cascade.
        if (!advertiserProfile || !hasCriteria) {
            return;
        }

        const timeout = setTimeout(async () => {
            setEstimating(true);

            const result = await postJson<AudienceEstimate>(
                '/advertising/audience-estimate',
                {
                    advertiser_profile_id: advertiserProfile.id,
                    criteria,
                },
            );

            setEstimating(false);

            if (result.ok) {
                setEstimate(result.data);
            }
        }, 500);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        advertiserProfile,
        targetCountry,
        targetCity,
        targetNeighborhood,
        ageBrackets,
        genders,
        interests,
    ]);

    function toggleFromList(
        list: string[],
        setList: (value: string[]) => void,
        value: string,
    ) {
        setList(
            list.includes(value)
                ? list.filter((entry) => entry !== value)
                : [...list, value],
        );
    }

    async function handleVideoSelected(
        event: React.ChangeEvent<HTMLInputElement>,
    ) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file || !advertiserProfile) {
            return;
        }

        setVideo(null);
        setVideoError(null);
        setVideoUploading(true);

        const formData = new FormData();
        formData.append('advertiser_profile_id', advertiserProfile.id);
        formData.append('video', file);

        const result = await postFormData<
            VideoUploadResult & { reason?: string }
        >('/advertising/campaign-videos', formData);

        setVideoUploading(false);

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setVideoError(
                data?.reason === 'video_duration_out_of_bounds'
                    ? `Durée hors bornes${videoDurationBounds ? ` (${videoDurationBounds.min_seconds}-${videoDurationBounds.max_seconds}s attendues)` : ''}.`
                    : data?.reason === 'video_duration_unreadable'
                      ? "Le fichier n'a pas pu être lu comme une vidéo valide."
                      : "L'envoi de la vidéo n'a pas abouti.",
            );

            return;
        }

        setVideo(result.data);
    }

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
            creations: {
                headline,
                ...(video
                    ? {
                          video_path: video.path,
                          video_duration_seconds: video.duration_seconds,
                      }
                    : {}),
            },
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
                criteria,
            },
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La création de la campagne n'a pas abouti. Vérifiez les champs et réessayez.",
            );

            return;
        }

        // Véto du dirigeant 2026-07-30 : redirige directement vers le
        // budget plutôt que la liste des campagnes, pour que le geste de
        // financement (campaign.fund_self) soit immédiatement accessible
        // après la création — jamais un budget engagé automatiquement,
        // seulement la page où l'annonceur peut choisir de financer.
        router.visit('/advertising/budget');
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

                                <Field
                                    label={`Vidéo (facultative${videoDurationBounds ? ` — ${videoDurationBounds.min_seconds}-${videoDurationBounds.max_seconds}s` : ''})`}
                                >
                                    <input
                                        type="file"
                                        accept="video/mp4"
                                        onChange={handleVideoSelected}
                                        disabled={
                                            videoUploading || !advertiserProfile
                                        }
                                        className={inputClass}
                                    />
                                </Field>

                                {videoUploading && (
                                    <p className="text-xs text-[var(--text-secondary)]">
                                        Envoi et vérification de la durée en
                                        cours…
                                    </p>
                                )}

                                {videoError && (
                                    <p className="text-xs text-[var(--status-danger)]">
                                        {videoError}
                                    </p>
                                )}

                                {video && (
                                    <p className="text-xs text-[var(--status-success)]">
                                        Vidéo acceptée —{' '}
                                        {video.duration_seconds} secondes.
                                        Visible dans l'aperçu ci-contre.
                                    </p>
                                )}
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

                                <h3 className="text-xs font-semibold tracking-wide text-[var(--text-secondary)] uppercase">
                                    Ciblage (facultatif)
                                </h3>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field label="Pays ciblé (codes séparés par virgules)">
                                        <input
                                            value={targetCountry}
                                            onChange={(event) =>
                                                setTargetCountry(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="CI"
                                            className={inputClass}
                                        />
                                    </Field>
                                    <Field label="Ville ciblée">
                                        <input
                                            value={targetCity}
                                            onChange={(event) =>
                                                setTargetCity(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Abidjan"
                                            className={inputClass}
                                        />
                                    </Field>
                                    <Field label="Quartier ciblé">
                                        <input
                                            value={targetNeighborhood}
                                            onChange={(event) =>
                                                setTargetNeighborhood(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Abobo"
                                            className={inputClass}
                                        />
                                    </Field>
                                </div>

                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-[var(--text-primary)]">
                                        Tranche d'âge
                                    </span>
                                    <div className="flex flex-wrap gap-2">
                                        {AGE_BRACKETS.map((bracket) => (
                                            <button
                                                key={bracket}
                                                type="button"
                                                onClick={() =>
                                                    toggleFromList(
                                                        ageBrackets,
                                                        setAgeBrackets,
                                                        bracket,
                                                    )
                                                }
                                                className={
                                                    ageBrackets.includes(
                                                        bracket,
                                                    )
                                                        ? 'rounded-full bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-white'
                                                        : 'rounded-full border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]'
                                                }
                                            >
                                                {bracket}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-[var(--text-primary)]">
                                        Genre
                                    </span>
                                    <div className="flex flex-wrap gap-2">
                                        {Object.entries(GENDERS).map(
                                            ([value, label]) => (
                                                <button
                                                    key={value}
                                                    type="button"
                                                    onClick={() =>
                                                        toggleFromList(
                                                            genders,
                                                            setGenders,
                                                            value,
                                                        )
                                                    }
                                                    className={
                                                        genders.includes(value)
                                                            ? 'rounded-full bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-white'
                                                            : 'rounded-full border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]'
                                                    }
                                                >
                                                    {label}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                </div>

                                {interestTaxonomy.length > 0 && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-[var(--text-primary)]">
                                            Centres d'intérêt
                                        </span>
                                        <div className="flex flex-wrap gap-2">
                                            {interestTaxonomy.map((option) => (
                                                <button
                                                    key={option.code}
                                                    type="button"
                                                    onClick={() =>
                                                        toggleFromList(
                                                            interests,
                                                            setInterests,
                                                            option.code,
                                                        )
                                                    }
                                                    className={
                                                        interests.includes(
                                                            option.code,
                                                        )
                                                            ? 'rounded-full bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-white'
                                                            : 'rounded-full border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]'
                                                    }
                                                >
                                                    {option.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="rounded-lg bg-[var(--bg-raised)] px-3 py-2.5 text-xs text-[var(--text-secondary)]">
                                    {!hasCriteria ? (
                                        <p>
                                            Aucun critère de ciblage choisi :
                                            l'audience calculée portera sur
                                            l'ensemble des profils publicitaires
                                            consentis.
                                        </p>
                                    ) : estimating ? (
                                        <p>Calcul de l'audience…</p>
                                    ) : estimate?.below_threshold ? (
                                        <p>
                                            Sous le seuil minimal actif
                                            {audienceSizeThreshold !== null
                                                ? ` (${audienceSizeThreshold})`
                                                : ''}
                                            : la taille ne vous sera pas
                                            communiquée (AMD-0009 §13).
                                        </p>
                                    ) : estimate?.estimated_size !== null &&
                                      estimate?.estimated_size !== undefined ? (
                                        <p>
                                            Audience estimée :{' '}
                                            <span className="font-semibold text-[var(--text-primary)]">
                                                {estimate.estimated_size}
                                            </span>{' '}
                                            profil(s) correspondant(s) (calculée
                                            depuis les profils publicitaires
                                            consentis, jamais devinée).
                                        </p>
                                    ) : (
                                        <p>—</p>
                                    )}
                                </div>
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

                        <aside className="space-y-5">
                            <AdPreviewCard
                                advertiserName={
                                    advertiserProfile?.legal_name ?? null
                                }
                                headline={headline}
                                formatLabel={previewFormatLabel}
                                video={video}
                                destinationHost={previewDestinationHost}
                            />

                            <div className="space-y-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5 text-sm text-[var(--text-secondary)]">
                                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                    Avant de publier
                                </h2>
                                <p>La campagne est créée à l'état brouillon.</p>
                                <p>
                                    Elle doit ensuite être soumise pour revue,
                                    puis approuvée.
                                </p>
                                <p>
                                    Elle ne peut être activée qu'une fois
                                    financée intégralement.
                                </p>
                                <p>
                                    Aucun gain ni disponibilité de campagne
                                    n'est garanti.
                                </p>
                            </div>
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

// Lot 5 (véto du dirigeant) : aperçu en direct, mirroir visuel du vrai
// rendu Feed (dashboard.tsx, dégradé + bandeau bas) — jamais une maquette
// inventée. Aucun élément propre au Feed (gain WP, condition de
// visionnage, boutons sociaux) : rien de tout cela n'existe encore à ce
// stade (pas de campagne créée, pas de tarification résolue).
function AdPreviewCard({
    advertiserName,
    headline,
    formatLabel,
    video,
    destinationHost,
}: {
    advertiserName: string | null;
    headline: string;
    formatLabel: string;
    video: VideoUploadResult | null;
    destinationHost: string;
}) {
    const displayName = advertiserName ?? 'Votre entreprise';

    return (
        <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
            <h2 className="mb-3 text-sm font-semibold text-[var(--text-primary)]">
                Aperçu de la publicité
            </h2>

            <div className="relative aspect-9/16 w-full overflow-hidden rounded-2xl bg-gradient-to-br from-[#173251] via-[#0C2340] to-[#0A1E38]">
                <span className="absolute top-3 left-3 z-10 rounded-md bg-[#0E2542]/80 px-2 py-0.5 text-[10px] font-semibold tracking-widest text-[#4FA3FF] uppercase backdrop-blur-sm">
                    {formatLabel}
                </span>

                {video ? (
                    <video
                        src={video.url}
                        muted
                        loop
                        controls
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                ) : (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center">
                        <ImageIcon
                            size={28}
                            className="text-[#4FA3FF]/60"
                            aria-hidden="true"
                        />
                        <p className="text-xs text-[#A9B7C8]">
                            Aucun média — ajoutez une vidéo pour l'aperçu.
                        </p>
                    </div>
                )}

                <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-[#07182D]/95 via-[#07182D]/55 to-transparent px-3 pt-10 pb-3">
                    <div className="flex items-center gap-2">
                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#4FA3FF] to-[#075CCF] text-xs font-bold text-white">
                            {displayName.charAt(0).toUpperCase()}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-xs font-bold text-white">
                                {displayName}
                            </p>
                            <p className="truncate text-xs text-[#A9B7C8]">
                                {headline.trim() ||
                                    'Votre titre apparaîtra ici'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p className="mt-3 truncate text-xs text-[var(--text-secondary)]">
                Renvoie vers{' '}
                <span className="text-[var(--text-primary)]">
                    {destinationHost}
                </span>
            </p>
        </div>
    );
}
