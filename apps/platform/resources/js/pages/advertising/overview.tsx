import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { postJson } from '@/lib/api';
import advertising from '@/routes/advertising';
import advertiserProfile from '@/routes/advertising/advertiser-profile';
import campaignVersions from '@/routes/advertising/campaign-versions';
import campaigns from '@/routes/advertising/campaigns';
import type { BreadcrumbItem } from '@/types';

type Access = {
    allowed: boolean;
    reason: string | null;
};

type AdvertiserProfile = {
    id: string;
    legal_name: string;
    status: string;
};

type Budget = {
    available: number;
    reserved: number;
    consumed: number;
};

type Campaign = {
    id: string;
    code: string;
    currency: string;
    state: string;
    latest_version_id: string | null;
    latest_version_state: string | null;
    budget: Budget;
};

type SectorClassification = {
    id: string;
    label: string;
};

const amountFormatter = new Intl.NumberFormat('fr-FR');

// Wasplex ne présente jamais un motif de refus interne (grant, politique,
// définition de capacité — ADR-0004 §"décision explicable") : seul un texte
// destiné à la personne, jamais le code technique brut, est affiché (même
// discipline que wallet/overview.tsx).
const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant:
        "L'accès à votre espace annonceur n'est pas encore activé sur ce compte.",
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

const CAMPAIGN_STATE_LABELS: Record<string, string> = {
    active: 'Active',
    suspended: 'Suspendue',
    closed: 'Terminée',
};

const VERSION_STATE_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    in_review: 'En revue',
    approved: 'Approuvée',
    suspended: 'Suspendue',
    retired: 'Retirée',
};

function displayStatus(campaign: Campaign): string {
    if (
        campaign.latest_version_state === 'draft' ||
        campaign.latest_version_state === 'in_review'
    ) {
        return VERSION_STATE_LABELS[campaign.latest_version_state];
    }

    return CAMPAIGN_STATE_LABELS[campaign.state] ?? campaign.state;
}

function AdvertiserOnboardingForm() {
    const [legalName, setLegalName] = useState('');
    const [legalIdentifier, setLegalIdentifier] = useState('');
    const [countryCode, setCountryCode] = useState('CI');
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
            territories: [countryCode],
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
        <Card>
            <CardHeader>
                <CardTitle>Déclarer votre dossier annonceur</CardTitle>
                <CardDescription>
                    Une seule fois : vous devenez alors représentant de ce
                    dossier et pouvez créer des campagnes en votre nom.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="legal_name">Raison sociale</Label>
                        <Input
                            id="legal_name"
                            required
                            value={legalName}
                            onChange={(event) =>
                                setLegalName(event.target.value)
                            }
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="legal_identifier">
                            Identifiant légal (optionnel)
                        </Label>
                        <Input
                            id="legal_identifier"
                            value={legalIdentifier}
                            onChange={(event) =>
                                setLegalIdentifier(event.target.value)
                            }
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="country_code">
                            Pays (code ISO à 2 lettres)
                        </Label>
                        <Input
                            id="country_code"
                            required
                            maxLength={2}
                            className="w-24 uppercase"
                            value={countryCode}
                            onChange={(event) =>
                                setCountryCode(event.target.value.toUpperCase())
                            }
                        />
                    </div>

                    <Button type="submit" disabled={submitting}>
                        {submitting ? 'Envoi...' : 'Déclarer mon dossier'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function CreateCampaignForm({
    advertiserProfileId,
    sectorClassifications,
    onCreated,
}: {
    advertiserProfileId: string;
    sectorClassifications: SectorClassification[];
    onCreated: () => void;
}) {
    const [code, setCode] = useState('');
    const [currency, setCurrency] = useState('XOF');
    const [sectorClassificationId, setSectorClassificationId] = useState(
        sectorClassifications[0]?.id ?? '',
    );
    const [territory, setTerritory] = useState('CI');
    const [headline, setHeadline] = useState('');
    const [destinationUrl, setDestinationUrl] = useState('');
    const [estimatedSize, setEstimatedSize] = useState(10_000);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson(campaigns.store.url(), {
            advertiser_profile_id: advertiserProfileId,
            code,
            currency,
            sector_classification_id: sectorClassificationId,
            creations: { headline },
            expected_event: { format: 'banner', condition: 'completion' },
            destination: { url: destinationUrl },
            territory: territory
                .split(',')
                .map((code) => code.trim().toUpperCase())
                .filter(Boolean),
            // Référence une Definition de démonstration réellement
            // résolvable par QualifiedEventPricingResolver (W2,
            // AdvertisingDemoConfigurationSeeder) — value=1, la plus
            // petite unité monétaire non nulle possible, jamais un vrai
            // tarif décidé (ADR-0010 §6, §7 : coefficients de format,
            // fréquence, rareté de segment restent hors périmètre, TD-0006).
            pricing_configuration_key: 'advertising.qualified_event_base_price',
            pricing_configuration_version: 1,
            audience: {
                criteria: { country: territory.split(',')[0]?.trim() },
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

        onCreated();
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Nouvelle campagne (brouillon)</CardTitle>
                <CardDescription>
                    Créée à l'état brouillon : aucune diffusion, aucun budget
                    engagé tant qu'elle n'est ni approuvée ni financée.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="code">Code de la campagne</Label>
                            <Input
                                id="code"
                                required
                                value={code}
                                onChange={(event) =>
                                    setCode(event.target.value)
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="currency">Devise (3 lettres)</Label>
                            <Input
                                id="currency"
                                required
                                maxLength={3}
                                className="uppercase"
                                value={currency}
                                onChange={(event) =>
                                    setCurrency(
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="sector">Secteur</Label>
                        <Select
                            value={sectorClassificationId}
                            onValueChange={setSectorClassificationId}
                        >
                            <SelectTrigger id="sector" className="w-full">
                                <SelectValue placeholder="Choisir un secteur" />
                            </SelectTrigger>
                            <SelectContent>
                                {sectorClassifications.map((sector) => (
                                    <SelectItem
                                        key={sector.id}
                                        value={sector.id}
                                    >
                                        {sector.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {sectorClassifications.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Aucun secteur actif n'est configuré : la
                                création de campagne est indisponible.
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="territory">
                            Territoires (codes pays séparés par des virgules)
                        </Label>
                        <Input
                            id="territory"
                            required
                            value={territory}
                            onChange={(event) =>
                                setTerritory(event.target.value)
                            }
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="headline">Titre de la création</Label>
                        <Input
                            id="headline"
                            required
                            value={headline}
                            onChange={(event) =>
                                setHeadline(event.target.value)
                            }
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="destination">URL de destination</Label>
                        <Input
                            id="destination"
                            type="url"
                            required
                            value={destinationUrl}
                            onChange={(event) =>
                                setDestinationUrl(event.target.value)
                            }
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="estimated_size">
                            Taille d'audience estimée
                        </Label>
                        <Input
                            id="estimated_size"
                            type="number"
                            min={0}
                            required
                            value={estimatedSize}
                            onChange={(event) =>
                                setEstimatedSize(Number(event.target.value))
                            }
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={
                            submitting || sectorClassifications.length === 0
                        }
                    >
                        {submitting ? 'Création...' : 'Créer la campagne'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function SubmitForReviewButton({
    campaignVersionId,
    onSubmitted,
}: {
    campaignVersionId: string;
    onSubmitted: () => void;
}) {
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleClick() {
        setSubmitting(true);
        setError(null);

        const result = await postJson(
            campaignVersions.submitForReview.url(campaignVersionId),
            {},
        );

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La soumission n'a pas abouti. Cette campagne n'est peut-être plus à l'état brouillon.",
            );

            return;
        }

        onSubmitted();
    }

    return (
        <div className="space-y-2">
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button
                size="sm"
                variant="outline"
                onClick={handleClick}
                disabled={submitting}
            >
                {submitting ? 'Envoi...' : 'Soumettre pour revue'}
            </Button>
        </div>
    );
}

export default function AdvertisingOverview({
    access,
    advertiserProfile: profile,
    campaigns: campaignList,
    sectorClassifications,
}: {
    access: Access;
    advertiserProfile: AdvertiserProfile | null;
    campaigns: Campaign[];
    sectorClassifications: SectorClassification[];
}) {
    const [showCreateForm, setShowCreateForm] = useState(false);

    return (
        <>
            <Head title="Publicité" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Votre espace annonceur"
                    description="Vos campagnes et leur budget projeté, reconstruit depuis le registre comptable."
                />

                {!access.allowed && (
                    <Alert>
                        <AlertTitle>
                            Consultation indisponible pour le moment
                        </AlertTitle>
                        <AlertDescription>
                            {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                                "Cet écran n'est pas encore disponible pour votre compte."}
                        </AlertDescription>
                    </Alert>
                )}

                {access.allowed && !profile && <AdvertiserOnboardingForm />}

                {access.allowed && profile && (
                    <>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{profile.legal_name}</CardTitle>
                                    <CardDescription>
                                        Dossier annonceur —{' '}
                                        {profile.status === 'active'
                                            ? 'actif'
                                            : 'suspendu'}
                                    </CardDescription>
                                </div>
                                <Button
                                    onClick={() =>
                                        setShowCreateForm((value) => !value)
                                    }
                                >
                                    {showCreateForm
                                        ? 'Annuler'
                                        : 'Nouvelle campagne'}
                                </Button>
                            </CardHeader>
                        </Card>

                        {showCreateForm && (
                            <CreateCampaignForm
                                advertiserProfileId={profile.id}
                                sectorClassifications={sectorClassifications}
                                onCreated={() => {
                                    setShowCreateForm(false);
                                    router.reload();
                                }}
                            />
                        )}

                        {campaignList.length === 0 && (
                            <Alert>
                                <AlertTitle>
                                    Aucune campagne pour le moment
                                </AlertTitle>
                                <AlertDescription>
                                    Créez votre première campagne pour la voir
                                    apparaître ici.
                                </AlertDescription>
                            </Alert>
                        )}

                        {campaignList.length > 0 && (
                            <div className="grid gap-4 md:grid-cols-2">
                                {campaignList.map((campaign) => (
                                    <Card key={campaign.id}>
                                        <CardHeader className="flex flex-row items-center justify-between">
                                            <CardTitle className="text-base">
                                                {campaign.code}
                                            </CardTitle>
                                            <Badge variant="secondary">
                                                {displayStatus(campaign)}
                                            </Badge>
                                        </CardHeader>
                                        <CardContent className="space-y-1 text-sm">
                                            <p>
                                                Disponible :{' '}
                                                {amountFormatter.format(
                                                    campaign.budget.available,
                                                )}{' '}
                                                {campaign.currency}
                                            </p>
                                            <p>
                                                Réservé :{' '}
                                                {amountFormatter.format(
                                                    campaign.budget.reserved,
                                                )}{' '}
                                                {campaign.currency}
                                            </p>
                                            <p>
                                                Consommé :{' '}
                                                {amountFormatter.format(
                                                    campaign.budget.consumed,
                                                )}{' '}
                                                {campaign.currency}
                                            </p>

                                            {campaign.latest_version_state ===
                                                'draft' &&
                                                campaign.latest_version_id && (
                                                    <div className="pt-2">
                                                        <SubmitForReviewButton
                                                            campaignVersionId={
                                                                campaign.latest_version_id
                                                            }
                                                            onSubmitted={() =>
                                                                router.reload()
                                                            }
                                                        />
                                                    </div>
                                                )}
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

AdvertisingOverview.layout = {
    breadcrumbs: [
        {
            title: 'Publicité',
            href: advertising.overview(),
        },
    ] satisfies BreadcrumbItem[],
};
