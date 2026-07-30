import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { Badge } from '@/components/ui/badge';
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
import AdminLayout from '@/layouts/admin-layout';
import { FORMAT_LABELS } from '@/lib/advertising-labels';
import { postJson } from '@/lib/api';
import { COUNTRIES } from '@/lib/countries';

type Classification = {
    id: string;
    country_code: string;
    sector: string;
    version: number;
    sector_class: string;
    minimum_age: number | null;
    required_evidence: string[];
    warnings: string[];
    allowed_formats: string[];
    allowed_targeting: string[];
    review_level: string;
    minimum_approvals: number;
    state: 'active' | 'retired';
};

const SECTOR_CLASS_LABELS: Record<string, string> = {
    enhanced_authorization: 'Autorisation renforcée',
    standard_authorization: 'Autorisation standard',
    institutional_information: 'Information institutionnelle',
};

const REVIEW_LEVEL_LABELS: Record<string, string> = {
    standard: 'Standard',
    enhanced: 'Renforcé',
};

// Une valeur par ligne — jamais un vocabulaire fermé imposé ici (voir le
// plan du Lot 9 : `allowed_targeting`/`required_evidence`/`warnings` ne
// sont contraints nulle part ailleurs dans le code aujourd'hui).
function linesToArray(text: string): string[] {
    return text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

function PublishForm({
    onPublished,
}: {
    onPublished: (classification: Classification) => void;
}) {
    const [countryCode, setCountryCode] = useState('');
    const [sector, setSector] = useState('');
    const [sectorClass, setSectorClass] = useState('');
    const [minimumAge, setMinimumAge] = useState('');
    const [requiredEvidence, setRequiredEvidence] = useState('');
    const [warnings, setWarnings] = useState('');
    const [allowedFormats, setAllowedFormats] = useState<string[]>([]);
    const [allowedTargeting, setAllowedTargeting] = useState('');
    const [reviewLevel, setReviewLevel] = useState('');
    const [minimumApprovals, setMinimumApprovals] = useState('1');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function toggleFormat(value: string) {
        setAllowedFormats((current) =>
            current.includes(value)
                ? current.filter((entry) => entry !== value)
                : [...current, value],
        );
    }

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<Classification>(
            '/admin/sector-classifications',
            {
                country_code: countryCode,
                sector,
                sector_class: sectorClass,
                minimum_age: minimumAge.trim() ? Number(minimumAge) : null,
                required_evidence: linesToArray(requiredEvidence),
                warnings: linesToArray(warnings),
                allowed_formats: allowedFormats,
                allowed_targeting: linesToArray(allowedTargeting),
                review_level: reviewLevel,
                minimum_approvals: Number(minimumApprovals),
            },
        );

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La publication n'a pas abouti. Vérifiez que le pays, le secteur, la classe et le niveau de revue sont bien renseignés.",
            );

            return;
        }

        onPublished(result.data);
        setSector('');
        setMinimumAge('');
        setRequiredEvidence('');
        setWarnings('');
        setAllowedFormats([]);
        setAllowedTargeting('');
        setMinimumApprovals('1');
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {error && (
                <p className="text-sm text-[var(--status-danger)]">{error}</p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Pays
                    </Label>
                    <Select
                        value={countryCode || undefined}
                        onValueChange={setCountryCode}
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
                </div>

                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Secteur (ex. alimentation, santé, finance)
                    </Label>
                    <input
                        value={sector}
                        onChange={(event) => setSector(event.target.value)}
                        className={inputClass}
                    />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Classe
                    </Label>
                    <Select
                        value={sectorClass || undefined}
                        onValueChange={setSectorClass}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choisir une classe" />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(SECTOR_CLASS_LABELS).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Âge minimal (facultatif)
                    </Label>
                    <input
                        type="number"
                        min={0}
                        value={minimumAge}
                        onChange={(event) => setMinimumAge(event.target.value)}
                        className={inputClass}
                    />
                </div>
            </div>

            <div className="space-y-1.5">
                <span className="text-xs font-medium text-[var(--text-primary)]">
                    Formats autorisés
                </span>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(FORMAT_LABELS).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => toggleFormat(value)}
                            className={
                                allowedFormats.includes(value)
                                    ? 'rounded-full bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-medium text-white'
                                    : 'rounded-full border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)]'
                            }
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Justificatifs requis (un par ligne, facultatif)
                    </Label>
                    <textarea
                        value={requiredEvidence}
                        onChange={(event) =>
                            setRequiredEvidence(event.target.value)
                        }
                        rows={3}
                        className={inputClass}
                    />
                </div>

                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Avertissements (un par ligne, facultatif)
                    </Label>
                    <textarea
                        value={warnings}
                        onChange={(event) => setWarnings(event.target.value)}
                        rows={3}
                        className={inputClass}
                    />
                </div>
            </div>

            <div className="space-y-1.5">
                <Label className="text-xs font-medium text-[var(--text-primary)]">
                    Ciblages autorisés (un par ligne, facultatif — ex. country,
                    age_range)
                </Label>
                <textarea
                    value={allowedTargeting}
                    onChange={(event) =>
                        setAllowedTargeting(event.target.value)
                    }
                    rows={2}
                    className={inputClass}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Niveau de revue
                    </Label>
                    <Select
                        value={reviewLevel || undefined}
                        onValueChange={setReviewLevel}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choisir un niveau" />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(REVIEW_LEVEL_LABELS).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Nombre minimal d'approbateurs
                    </Label>
                    <input
                        type="number"
                        min={1}
                        value={minimumApprovals}
                        onChange={(event) =>
                            setMinimumApprovals(event.target.value)
                        }
                        className={inputClass}
                    />
                </div>
            </div>

            <Button type="submit" disabled={submitting}>
                {submitting ? 'Publication...' : 'Publier cette version'}
            </Button>
        </form>
    );
}

function ClassificationRow({
    classification,
    onRetired,
}: {
    classification: Classification;
    onRetired: (classification: Classification) => void;
}) {
    const [submitting, setSubmitting] = useState(false);

    async function retire() {
        setSubmitting(true);

        const result = await postJson<Classification>(
            `/admin/sector-classifications/${classification.id}/retire`,
            {},
        );

        setSubmitting(false);

        if (result.ok) {
            onRetired(result.data);
        }
    }

    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-default)] px-4 py-3">
            <div className="min-w-0">
                <p className="text-sm font-medium text-[var(--text-primary)]">
                    {classification.country_code} — {classification.sector}{' '}
                    <span className="text-[var(--text-secondary)]">
                        (v{classification.version})
                    </span>
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                    {SECTOR_CLASS_LABELS[classification.sector_class] ??
                        classification.sector_class}{' '}
                    · Revue{' '}
                    {REVIEW_LEVEL_LABELS[classification.review_level] ??
                        classification.review_level}
                    {classification.minimum_age
                        ? ` · Âge minimal ${classification.minimum_age} ans`
                        : ''}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-3">
                <Badge
                    variant={
                        classification.state === 'active'
                            ? 'default'
                            : 'outline'
                    }
                >
                    {classification.state === 'active' ? 'active' : 'retirée'}
                </Badge>
                {classification.state === 'active' && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => void retire()}
                        disabled={submitting}
                    >
                        Retirer
                    </Button>
                )}
            </div>
        </div>
    );
}

export default function AdminSectorClassifications({
    access,
    classifications,
}: {
    access: AdminAccess;
    classifications: Classification[];
}) {
    const [list, setList] = useState(classifications);

    function upsert(updated: Classification) {
        setList((current) => {
            const withoutUpdated = current.filter(
                (entry) => entry.id !== updated.id,
            );

            return [updated, ...withoutUpdated];
        });
    }

    return (
        <AdminLayout
            title="Secteurs publicitaires"
            description="Classification (pays, secteur) : formats autorisés, âge minimal, avertissements, niveau de revue (advertising.manage_sector_classifications)."
        >
            <Head title="Personnel — Secteurs publicitaires" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Publier une classification
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PublishForm onPublished={upsert} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Classifications existantes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {list.length === 0 ? (
                                    <p className="text-sm text-[var(--text-secondary)]">
                                        Aucune classification publiée pour le
                                        moment.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {list.map((classification) => (
                                            <ClassificationRow
                                                key={classification.id}
                                                classification={classification}
                                                onRetired={upsert}
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                </AdminAccessGate>
            </div>
        </AdminLayout>
    );
}

const inputClass =
    'w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none';
