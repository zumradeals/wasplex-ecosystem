import { Head, router } from '@inertiajs/react';
import { Eye, Image as ImageIcon, Info, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { FORMAT_LABELS } from '@/lib/advertising-labels';
import { postFormData, postJson } from '@/lib/api';
import { COUNTRIES } from '@/lib/countries';
import { CURRENCIES } from '@/lib/currencies';
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
// Lot 7 : `targetCountries` est désormais une liste de codes déjà propres
// (choisis via menu déroulant), plus une chaîne CSV à analyser.
function buildAudienceCriteria(params: {
    targetCountries: string[];
    targetCity: string;
    targetNeighborhood: string;
    ageBrackets: string[];
    genders: string[];
    interests: string[];
}): Record<string, unknown> {
    const criteria: Record<string, unknown> = {};

    const countryCodes = params.targetCountries.filter(Boolean);

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

// Lot 7 : quelques suggestions de saisie réelles et non exhaustives
// (grandes villes ivoiriennes, communes d'Abidjan déjà citées dans le
// code existant) — un simple confort via `<datalist>`, jamais une liste
// fermée : toute autre valeur (n'importe quel pays) reste acceptée.
const CITY_SUGGESTIONS = [
    'Abidjan',
    'Bouaké',
    'Yamoussoukro',
    'San-Pédro',
    'Korhogo',
    'Daloa',
];

const NEIGHBORHOOD_SUGGESTIONS = [
    'Abobo',
    'Adjamé',
    'Attécoubé',
    'Cocody',
    'Koumassi',
    'Marcory',
    'Plateau',
    'Port-Bouët',
    'Treichville',
    'Yopougon',
];

const STEP_LABELS = [
    'Votre publicité',
    'Qui doit la voir',
    'Vérifier et publier',
];

type InterestOption = { code: string; label: string };

type AudienceEstimate = {
    estimated_size: number | null;
    below_threshold: boolean;
};

type VideoDurationBounds = { min_seconds: number; max_seconds: number };

type IndicativePricing = {
    unit_price: number;
    user_share: number;
    wasplex_share: number;
};

type VideoUploadResult = {
    path: string;
    url: string;
    duration_seconds: number;
};

type ImageUploadResult = {
    path: string;
    url: string;
    width: number;
    height: number;
};

export default function AdvertisingCampaignCreate({
    access,
    advertiserProfile,
    sectorClassifications,
    audienceSizeThreshold,
    interestTaxonomy,
    videoDurationBounds,
    indicativePricing,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    sectorClassifications: SectorOption[];
    audienceSizeThreshold: number | null;
    interestTaxonomy: InterestOption[];
    videoDurationBounds: VideoDurationBounds | null;
    indicativePricing: IndicativePricing | null;
}) {
    const [step, setStep] = useState(0);

    // Lot 8 (instruction explicite du fondateur 2026-07-30) : le code de
    // campagne n'a jamais eu de signification métier — un simple
    // identifiant unique (`unique:campaigns,code`) utilisé dans les
    // messages du grand livre comptable. Un non-tech n'a plus à en
    // inventer un : généré une seule fois par instance de formulaire
    // (mirroir du générateur de clé d'idempotence de `dashboard.tsx`),
    // jamais recalculé à chaque rendu.
    const [code] = useState(
        () =>
            `campaign-${
                typeof crypto.randomUUID === 'function'
                    ? crypto.randomUUID()
                    : `${Date.now()}-${Math.random().toString(36).slice(2)}`
            }`,
    );
    const [currency, setCurrency] = useState('XOF');
    const [sectorId, setSectorId] = useState(
        sectorClassifications[0]?.id ?? '',
    );
    // Lot 7 : le territoire de diffusion devient une liste de menus
    // déroulants (un pays par ligne) plutôt qu'un texte libre CSV — un
    // non-tech n'a plus à connaître les codes ISO à deux lettres.
    const [territories, setTerritories] = useState<string[]>([
        sectorClassifications[0]?.country_code ?? 'CI',
    ]);
    const [headline, setHeadline] = useState('');
    const [format, setFormat] = useState('');
    const [destinationUrl, setDestinationUrl] = useState('');

    // Lot 3 (véto du dirigeant) : ciblage réel — pays cible (indépendant
    // du territoire de diffusion ci-dessus, qui reste une contrainte
    // légale/sectorielle), ville et quartier (texte libre, comme le
    // profil publicitaire), tranche d'âge/genre/centres d'intérêt à
    // cocher. `estimatedSize` n'est plus saisi : calculé par le serveur.
    const [targetCountries, setTargetCountries] = useState<string[]>([]);
    const [targetCity, setTargetCity] = useState('');
    const [targetNeighborhood, setTargetNeighborhood] = useState('');
    const [ageBrackets, setAgeBrackets] = useState<string[]>([]);
    const [genders, setGenders] = useState<string[]>([]);
    const [interests, setInterests] = useState<string[]>([]);
    const [estimate, setEstimate] = useState<AudienceEstimate | null>(null);
    const [estimating, setEstimating] = useState(false);

    // Lot 4/6 (instruction explicite du fondateur 2026-07-30) : upload
    // immédiat dès sélection du fichier (mirroir du flux GeniusPay :
    // upload d'abord, référence ensuite) — jamais rattaché à une
    // campagne tant que la création elle-même n'est pas soumise. Un seul
    // média à la fois (vidéo OU image, jamais les deux) : accepter l'un
    // remet l'autre à `null`.
    const [video, setVideo] = useState<VideoUploadResult | null>(null);
    const [image, setImage] = useState<ImageUploadResult | null>(null);
    const [mediaUploading, setMediaUploading] = useState(false);
    const [mediaError, setMediaError] = useState<string | null>(null);

    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const sector =
        sectorClassifications.find((candidate) => candidate.id === sectorId) ??
        null;

    // Chantier « espace annonceur cohérent avec le modèle économique »
    // (véto du dirigeant) : le format n'est plus un choix libre découplé
    // du média réellement envoyé (cause racine de l'incohérence vidéo
    // ~29s / « Affichage » / « vue complète » constatée en capture) — une
    // vidéo impose le format « Vidéo ». Sans vidéo (image ou aucun média),
    // le format reste un choix manuel parmi les formats non-vidéo
    // autorisés par le secteur. Dérivé au rendu (jamais via un effet qui
    // réécrirait l'état — un ancien choix « video » resté d'un média
    // retiré est simplement ignoré ici, jamais persisté).
    const mediaLockedFormat = video ? 'video' : null;
    const formatOptions = (
        sector?.allowed_formats.length
            ? sector.allowed_formats
            : Object.keys(FORMAT_LABELS)
    ).filter((value) => value !== 'video');
    const effectiveFormat =
        mediaLockedFormat ?? (formatOptions.includes(format) ? format : '');
    const formatMismatch =
        mediaLockedFormat !== null &&
        sector !== null &&
        sector.allowed_formats.length > 0 &&
        !sector.allowed_formats.includes(mediaLockedFormat);

    const criteria = buildAudienceCriteria({
        targetCountries,
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
    // Même valeur exacte que celle réellement soumise ci-dessous
    // (`handleSubmit`) — un aperçu qui diffère de ce qui part au serveur
    // serait lui-même une nouvelle incohérence du type de celle que ce
    // chantier corrige.
    const submittedFormat =
        effectiveFormat || sector?.allowed_formats[0] || 'banner';
    const previewFormat = submittedFormat;
    const previewFormatLabel = FORMAT_LABELS[previewFormat] ?? previewFormat;
    const previewDestinationHost = destinationHost(destinationUrl);

    // Lot 7 : navigation par étapes — validée directement sur l'état React
    // (jamais une validation HTML5 sur des champs masqués d'une autre
    // étape, un piège classique des formulaires en plusieurs pages).
    const step1Valid = Boolean(
        currency.trim().length === 3 &&
        headline.trim() &&
        destinationUrl.trim() &&
        sectorId &&
        !formatMismatch,
    );
    const step2Valid = territories.length > 0 && territories.every(Boolean);

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
        targetCountries,
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

    async function uploadVideo(file: File) {
        const formData = new FormData();
        formData.append('advertiser_profile_id', advertiserProfile!.id);
        formData.append('video', file);

        const result = await postFormData<
            VideoUploadResult & { reason?: string }
        >('/advertising/campaign-videos', formData);

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setMediaError(
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

    async function uploadImage(file: File) {
        const formData = new FormData();
        formData.append('advertiser_profile_id', advertiserProfile!.id);
        formData.append('image', file);

        const result = await postFormData<
            ImageUploadResult & { reason?: string }
        >('/advertising/campaign-images', formData);

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setMediaError(
                data?.reason === 'image_orientation_refused'
                    ? 'Format paysage refusé : une image verticale (portrait ou carrée) est requise.'
                    : data?.reason === 'image_unreadable'
                      ? "Le fichier n'a pas pu être lu comme une image valide."
                      : "L'envoi de l'image n'a pas abouti.",
            );

            return;
        }

        setImage(result.data);
    }

    // Lot 6 (instruction explicite du fondateur 2026-07-30) : un seul
    // champ « Média » — le type du fichier choisi détermine la route
    // d'upload et la vérification appliquée. Le rejet d'un type non
    // reconnu ici n'est qu'un confort d'UX : le serveur reste la seule
    // autorité.
    async function handleMediaSelected(
        event: React.ChangeEvent<HTMLInputElement>,
    ) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file || !advertiserProfile) {
            return;
        }

        setVideo(null);
        setImage(null);
        setMediaError(null);

        if (file.type.startsWith('video/')) {
            setMediaUploading(true);
            await uploadVideo(file);
            setMediaUploading(false);

            return;
        }

        if (file.type.startsWith('image/')) {
            setMediaUploading(true);
            await uploadImage(file);
            setMediaUploading(false);

            return;
        }

        setMediaError('Type de fichier non reconnu : vidéo ou image attendue.');
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
                ...(image ? { image_path: image.path } : {}),
            },
            expected_event: {
                format: submittedFormat,
                condition: 'completion',
            },
            destination: { url: destinationUrl },
            territory: territories.filter(Boolean),
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

    const previewCard = (
        <AdPreviewCard
            advertiserName={advertiserProfile?.legal_name ?? null}
            headline={headline}
            formatLabel={previewFormatLabel}
            video={video}
            image={image}
            destinationHost={previewDestinationHost}
        />
    );

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
                        <div className="space-y-5 lg:col-span-2">
                            {error && (
                                <div className="rounded-lg border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-4 py-3 text-sm text-[var(--status-danger)]">
                                    {error}
                                </div>
                            )}

                            <StepIndicator step={step} />

                            <form onSubmit={handleSubmit} className="space-y-5">
                                <div
                                    className={
                                        step === 0 ? 'space-y-5' : 'hidden'
                                    }
                                >
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Votre publicité
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <Field label="Devise du budget">
                                                <Select
                                                    value={
                                                        currency || undefined
                                                    }
                                                    onValueChange={setCurrency}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Choisir une devise" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {[...CURRENCIES]
                                                            .sort((a, b) =>
                                                                a.name.localeCompare(
                                                                    b.name,
                                                                    'fr',
                                                                ),
                                                            )
                                                            .map((option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.code
                                                                    }
                                                                    value={
                                                                        option.code
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        option.code
                                                                    }
                                                                    )
                                                                </SelectItem>
                                                            ))}
                                                    </SelectContent>
                                                </Select>
                                            </Field>

                                            <Field label="Titre de la création">
                                                <input
                                                    value={headline}
                                                    onChange={(event) =>
                                                        setHeadline(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Ce que vous annoncez"
                                                    className={inputClass}
                                                />
                                            </Field>

                                            <Field label="URL de destination">
                                                <input
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

                                            <Field label="Secteur">
                                                <Select
                                                    value={
                                                        sectorId || undefined
                                                    }
                                                    onValueChange={setSectorId}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Choisir un secteur" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {sectorClassifications.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </Field>

                                            {sector &&
                                                (sector.allowed_formats.length >
                                                    0 ||
                                                    sector.minimum_age ||
                                                    sector.warnings.length >
                                                        0) && (
                                                    <div className="flex gap-2 rounded-lg bg-[var(--bg-raised)] px-3 py-2.5 text-xs text-[var(--text-secondary)]">
                                                        <Info
                                                            size={14}
                                                            className="mt-0.5 shrink-0"
                                                        />
                                                        <div className="space-y-1">
                                                            {sector
                                                                .allowed_formats
                                                                .length > 0 && (
                                                                <p>
                                                                    Formats
                                                                    autorisés
                                                                    pour ce
                                                                    secteur :{' '}
                                                                    {sector.allowed_formats
                                                                        .map(
                                                                            (
                                                                                value,
                                                                            ) =>
                                                                                FORMAT_LABELS[
                                                                                    value
                                                                                ] ??
                                                                                value,
                                                                        )
                                                                        .join(
                                                                            ', ',
                                                                        )}
                                                                </p>
                                                            )}
                                                            {sector.minimum_age && (
                                                                <p>
                                                                    Âge minimal
                                                                    requis :{' '}
                                                                    {
                                                                        sector.minimum_age
                                                                    }{' '}
                                                                    ans
                                                                </p>
                                                            )}
                                                            {sector.warnings
                                                                .length > 0 && (
                                                                <p>
                                                                    Avertissements
                                                                    :{' '}
                                                                    {sector.warnings.join(
                                                                        ', ',
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                            <Field
                                                label={`Média (facultatif — vidéo${videoDurationBounds ? ` ${videoDurationBounds.min_seconds}-${videoDurationBounds.max_seconds}s` : ''} ou image verticale)`}
                                            >
                                                <input
                                                    type="file"
                                                    accept="video/mp4,image/jpeg,image/png,image/webp"
                                                    onChange={
                                                        handleMediaSelected
                                                    }
                                                    disabled={
                                                        mediaUploading ||
                                                        !advertiserProfile
                                                    }
                                                    className={inputClass}
                                                />
                                            </Field>

                                            {mediaUploading && (
                                                <p className="text-xs text-[var(--text-secondary)]">
                                                    Envoi et vérification en
                                                    cours…
                                                </p>
                                            )}

                                            {mediaError && (
                                                <p className="text-xs text-[var(--status-danger)]">
                                                    {mediaError}
                                                </p>
                                            )}

                                            {video && (
                                                <p className="text-xs text-[var(--status-success)]">
                                                    Vidéo acceptée —{' '}
                                                    {video.duration_seconds}{' '}
                                                    secondes. Visible dans
                                                    l'aperçu.
                                                </p>
                                            )}

                                            {image && (
                                                <p className="text-xs text-[var(--status-success)]">
                                                    Image acceptée —{' '}
                                                    {image.width}×{image.height}
                                                    . Visible dans l'aperçu.
                                                </p>
                                            )}

                                            {mediaLockedFormat ? (
                                                <Field label="Format de la publicité">
                                                    <p className="text-sm text-[var(--text-primary)]">
                                                        {FORMAT_LABELS[
                                                            mediaLockedFormat
                                                        ] ??
                                                            mediaLockedFormat}{' '}
                                                        <span className="text-xs text-[var(--text-secondary)]">
                                                            (déterminé par le
                                                            média envoyé
                                                            ci-dessus)
                                                        </span>
                                                    </p>
                                                </Field>
                                            ) : (
                                                <Field label="Format de la publicité">
                                                    <Select
                                                        value={
                                                            effectiveFormat ||
                                                            undefined
                                                        }
                                                        onValueChange={
                                                            setFormat
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Choisir un format" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {formatOptions.map(
                                                                (value) => (
                                                                    <SelectItem
                                                                        key={
                                                                            value
                                                                        }
                                                                        value={
                                                                            value
                                                                        }
                                                                    >
                                                                        {FORMAT_LABELS[
                                                                            value
                                                                        ] ??
                                                                            value}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                            )}

                                            {formatMismatch && (
                                                <p className="text-xs text-[var(--status-danger)]">
                                                    Le secteur choisi n'autorise
                                                    pas le format «
                                                    {FORMAT_LABELS[
                                                        mediaLockedFormat ?? ''
                                                    ] ?? mediaLockedFormat}
                                                    » déterminé par ce média :
                                                    choisissez un autre secteur
                                                    ou un autre média.
                                                </p>
                                            )}

                                            <p className="text-xs text-[var(--text-secondary)]">
                                                Condition de crédit : vue
                                                complète requise — la seule
                                                condition prise en charge
                                                aujourd'hui.
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div
                                    className={
                                        step === 1 ? 'space-y-5' : 'hidden'
                                    }
                                >
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Territoire de diffusion
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <CountrySelectList
                                                label="Pays où la campagne peut être diffusée (obligatoire)"
                                                values={territories}
                                                onChange={setTerritories}
                                                minRows={1}
                                            />
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Ciblage précis (facultatif)
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <p className="text-xs text-[var(--text-secondary)]">
                                                Différent du territoire de
                                                diffusion ci-dessus : ici, vous
                                                choisissez qui, parmi les
                                                personnes de ce territoire,
                                                devrait voir votre publicité en
                                                priorité. Laissez vide pour
                                                toucher tout le monde.
                                            </p>

                                            <CountrySelectList
                                                label="Pays ciblé"
                                                values={targetCountries}
                                                onChange={setTargetCountries}
                                            />

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Field label="Ville ciblée (ex. Abidjan)">
                                                    <input
                                                        value={targetCity}
                                                        onChange={(event) =>
                                                            setTargetCity(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        list="campaign-create-city-suggestions"
                                                        placeholder="Abidjan"
                                                        className={inputClass}
                                                    />
                                                </Field>
                                                <Field label="Quartier ciblé (ex. Abobo)">
                                                    <input
                                                        value={
                                                            targetNeighborhood
                                                        }
                                                        onChange={(event) =>
                                                            setTargetNeighborhood(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        list="campaign-create-neighborhood-suggestions"
                                                        placeholder="Abobo"
                                                        className={inputClass}
                                                    />
                                                </Field>
                                            </div>

                                            <datalist id="campaign-create-city-suggestions">
                                                {CITY_SUGGESTIONS.map(
                                                    (city) => (
                                                        <option
                                                            key={city}
                                                            value={city}
                                                        />
                                                    ),
                                                )}
                                            </datalist>
                                            <datalist id="campaign-create-neighborhood-suggestions">
                                                {NEIGHBORHOOD_SUGGESTIONS.map(
                                                    (neighborhood) => (
                                                        <option
                                                            key={neighborhood}
                                                            value={neighborhood}
                                                        />
                                                    ),
                                                )}
                                            </datalist>

                                            <div className="space-y-1.5">
                                                <span className="text-xs font-medium text-[var(--text-primary)]">
                                                    Tranche d'âge
                                                </span>
                                                <div className="flex flex-wrap gap-2">
                                                    {AGE_BRACKETS.map(
                                                        (bracket) => (
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
                                                        ),
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <span className="text-xs font-medium text-[var(--text-primary)]">
                                                    Genre
                                                </span>
                                                <div className="flex flex-wrap gap-2">
                                                    {Object.entries(
                                                        GENDERS,
                                                    ).map(([value, label]) => (
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
                                                                genders.includes(
                                                                    value,
                                                                )
                                                                    ? 'rounded-full bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-white'
                                                                    : 'rounded-full border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]'
                                                            }
                                                        >
                                                            {label}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>

                                            {interestTaxonomy.length > 0 && (
                                                <div className="space-y-1.5">
                                                    <span className="text-xs font-medium text-[var(--text-primary)]">
                                                        Centres d'intérêt
                                                    </span>
                                                    <div className="flex flex-wrap gap-2">
                                                        {interestTaxonomy.map(
                                                            (option) => (
                                                                <button
                                                                    key={
                                                                        option.code
                                                                    }
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
                                                                    {
                                                                        option.label
                                                                    }
                                                                </button>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            )}

                                            <div className="rounded-lg bg-[var(--bg-raised)] px-3 py-2.5 text-xs text-[var(--text-secondary)]">
                                                {!hasCriteria ? (
                                                    <p>
                                                        Aucun critère de ciblage
                                                        choisi : l'audience
                                                        calculée portera sur
                                                        l'ensemble des profils
                                                        publicitaires consentis.
                                                    </p>
                                                ) : estimating ? (
                                                    <p>Calcul de l'audience…</p>
                                                ) : estimate?.below_threshold ? (
                                                    <p>
                                                        Sous le seuil minimal
                                                        actif
                                                        {audienceSizeThreshold !==
                                                        null
                                                            ? ` (${audienceSizeThreshold})`
                                                            : ''}
                                                        : la taille ne vous sera
                                                        pas communiquée
                                                        (AMD-0009 §13).
                                                    </p>
                                                ) : estimate?.estimated_size !==
                                                      null &&
                                                  estimate?.estimated_size !==
                                                      undefined ? (
                                                    <p>
                                                        Audience estimée :{' '}
                                                        <span className="font-semibold text-[var(--text-primary)]">
                                                            {
                                                                estimate.estimated_size
                                                            }
                                                        </span>{' '}
                                                        profil(s)
                                                        correspondant(s)
                                                        (calculée depuis les
                                                        profils publicitaires
                                                        consentis, jamais
                                                        devinée).
                                                    </p>
                                                ) : (
                                                    <p>—</p>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div
                                    className={
                                        step === 2 ? 'space-y-5' : 'hidden'
                                    }
                                >
                                    <div className="lg:hidden">
                                        {previewCard}
                                    </div>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Récapitulatif</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <dl className="space-y-2 text-sm">
                                                <SummaryRow
                                                    label="Titre"
                                                    value={headline || '—'}
                                                />
                                                <SummaryRow
                                                    label="Devise"
                                                    value={currency || '—'}
                                                />
                                                <SummaryRow
                                                    label="Secteur"
                                                    value={sector?.label ?? '—'}
                                                />
                                                <SummaryRow
                                                    label="Format"
                                                    value={previewFormatLabel}
                                                />
                                                <SummaryRow
                                                    label="Média"
                                                    value={
                                                        video
                                                            ? 'Vidéo ajoutée'
                                                            : image
                                                              ? 'Image ajoutée'
                                                              : 'Aucun'
                                                    }
                                                />
                                                <SummaryRow
                                                    label="Territoire de diffusion"
                                                    value={
                                                        territories
                                                            .filter(Boolean)
                                                            .map(
                                                                (code) =>
                                                                    COUNTRIES.find(
                                                                        (c) =>
                                                                            c.code ===
                                                                            code,
                                                                    )?.name ??
                                                                    code,
                                                            )
                                                            .join(', ') || '—'
                                                    }
                                                />
                                            </dl>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Devis indicatif
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {indicativePricing ? (
                                                <>
                                                    <dl className="space-y-2 text-sm">
                                                        <SummaryRow
                                                            label="Prix par vue qualifiée"
                                                            value={`${indicativePricing.unit_price} ${currency || '—'}`}
                                                        />
                                                        <SummaryRow
                                                            label="Dont récompense utilisateur (50 %)"
                                                            value={`${indicativePricing.user_share} ${currency || '—'}`}
                                                        />
                                                        <SummaryRow
                                                            label="Dont part Wasplex (50 %)"
                                                            value={`${indicativePricing.wasplex_share} ${currency || '—'}`}
                                                        />
                                                    </dl>
                                                    <p className="text-xs text-[var(--text-secondary)]">
                                                        Valeur de la
                                                        configuration actuelle,
                                                        encore démonstrative —
                                                        pas un tarif commercial
                                                        validé, ni un catalogue
                                                        par format ou durée. Le
                                                        nombre de vues
                                                        achetables avec votre
                                                        budget et le traitement
                                                        du reliquat non consommé
                                                        seront visibles une fois
                                                        la campagne financée,
                                                        sur l'écran Budget.
                                                    </p>
                                                </>
                                            ) : (
                                                <p className="text-xs text-[var(--text-secondary)]">
                                                    Aucun prix n'est
                                                    actuellement configuré :
                                                    cette campagne ne pourra pas
                                                    encore accepter d'événement
                                                    qualifié.
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Avant de publier
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm text-[var(--text-secondary)]">
                                            <p>
                                                La campagne est créée à l'état
                                                brouillon.
                                            </p>
                                            <p>
                                                Elle doit ensuite être soumise
                                                pour revue, puis approuvée.
                                            </p>
                                            <p>
                                                Elle ne peut être activée qu'une
                                                fois financée intégralement.
                                            </p>
                                            <p>
                                                Aucun gain ni disponibilité de
                                                campagne n'est garanti.
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        {step > 0 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setStep(step - 1)
                                                }
                                            >
                                                Précédent
                                            </Button>
                                        )}
                                    </div>

                                    {step < 2 ? (
                                        <Button
                                            type="button"
                                            disabled={
                                                (step === 0 && !step1Valid) ||
                                                (step === 1 && !step2Valid)
                                            }
                                            onClick={() => setStep(step + 1)}
                                        >
                                            Suivant
                                        </Button>
                                    ) : (
                                        <Button
                                            type="submit"
                                            disabled={submitting}
                                        >
                                            {submitting
                                                ? 'Création...'
                                                : 'Créer la campagne (brouillon)'}
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </div>

                        <aside className="hidden space-y-5 lg:block">
                            {previewCard}
                        </aside>
                    </div>
                )}
            </AdvertiserAccessGate>

            {sectorClassifications.length > 0 && step !== 2 && (
                <div className="lg:hidden">
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                type="button"
                                className="fixed right-4 bottom-4 z-20 shadow-lg"
                            >
                                <Eye size={16} />
                                Voir l'aperçu
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="bottom"
                            className="max-h-[85vh] overflow-y-auto"
                        >
                            <SheetHeader>
                                <SheetTitle>Aperçu de la publicité</SheetTitle>
                            </SheetHeader>
                            <div className="px-4 pb-4">{previewCard}</div>
                        </SheetContent>
                    </Sheet>
                </div>
            )}
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
        <div className="block space-y-1.5">
            <Label className="text-xs font-medium text-[var(--text-primary)]">
                {label}
            </Label>
            {children}
        </div>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3 border-b border-[var(--border-default)] pb-2 last:border-0 last:pb-0">
            <dt className="text-[var(--text-secondary)]">{label}</dt>
            <dd className="text-right font-medium text-[var(--text-primary)]">
                {value}
            </dd>
        </div>
    );
}

// Lot 7 : indicateur d'étape, mirroir visuel des puces déjà utilisées
// dans ce fichier pour âge/genre/intérêts.
function StepIndicator({ step }: { step: number }) {
    return (
        <div className="flex flex-wrap gap-2">
            {STEP_LABELS.map((label, index) => (
                <span
                    key={label}
                    className={
                        index === step
                            ? 'rounded-full bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-white'
                            : index < step
                              ? 'rounded-full border border-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-[var(--brand-blue)]'
                              : 'rounded-full border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]'
                    }
                >
                    {index + 1}. {label}
                </span>
            ))}
        </div>
    );
}

// Lot 7 : sélection d'un-ou-plusieurs pays via menus déroulants (jamais un
// texte libre à codes ISO) — mirroir du pattern `toggleFromList` déjà
// présent dans ce fichier, adapté ici à une liste de menus (une liste de
// puces serait inutilisable avec ~195 pays).
function CountrySelectList({
    label,
    values,
    onChange,
    minRows = 0,
}: {
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    minRows?: number;
}) {
    function updateAt(index: number, code: string) {
        const next = [...values];
        next[index] = code;
        onChange(next);
    }

    function removeAt(index: number) {
        onChange(values.filter((_, i) => i !== index));
    }

    return (
        <div className="space-y-2">
            <Label className="text-xs font-medium text-[var(--text-primary)]">
                {label}
            </Label>

            <div className="space-y-2">
                {values.map((value, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <Select
                            value={value || undefined}
                            onValueChange={(next) => updateAt(index, next)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Choisir un pays" />
                            </SelectTrigger>
                            <SelectContent>
                                {COUNTRIES.map((country) => (
                                    <SelectItem
                                        key={country.code}
                                        value={country.code}
                                    >
                                        {country.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {values.length > minRows && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Retirer ce pays"
                                onClick={() => removeAt(index)}
                            >
                                <X size={16} />
                            </Button>
                        )}
                    </div>
                ))}
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...values, ''])}
            >
                <Plus size={14} />
                Ajouter un pays
            </Button>
        </div>
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
    image,
    destinationHost,
}: {
    advertiserName: string | null;
    headline: string;
    formatLabel: string;
    video: VideoUploadResult | null;
    image: ImageUploadResult | null;
    destinationHost: string;
}) {
    const displayName = advertiserName ?? 'Votre entreprise';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Aperçu de la publicité</CardTitle>
            </CardHeader>
            <CardContent>
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
                    ) : image ? (
                        <img
                            src={image.url}
                            alt=""
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
                                Aucun média — ajoutez une vidéo ou une image
                                pour l'aperçu.
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
            </CardContent>
        </Card>
    );
}
