import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';

type EconomicType = {
    id: string;
    stable_key: string;
    name: string;
    version: number;
    user_share_percentage: number;
    monthly_quota: number | null;
    is_default: boolean;
    state: 'active' | 'retired';
};

const inputClass =
    'w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none';

function PublishForm({
    onPublished,
}: {
    onPublished: (type: EconomicType) => void;
}) {
    const [stableKey, setStableKey] = useState('');
    const [name, setName] = useState('');
    const [userSharePercentage, setUserSharePercentage] = useState('100');
    const [monthlyQuota, setMonthlyQuota] = useState('');
    const [isDefault, setIsDefault] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<EconomicType>('/admin/economic-types', {
            stable_key: stableKey,
            name,
            user_share_percentage: Number(userSharePercentage),
            monthly_quota: monthlyQuota.trim() ? Number(monthlyQuota) : null,
            is_default: isDefault,
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La publication n'a pas abouti. Vérifiez les champs (clé technique en minuscules, pourcentage entre 0 et 100).",
            );

            return;
        }

        onPublished(result.data);
        setName('');
        setMonthlyQuota('');
        setIsDefault(false);
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {error && (
                <p className="text-sm text-[var(--status-danger)]">{error}</p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Clé technique (ex. gold, minuscules)
                    </Label>
                    <input
                        value={stableKey}
                        onChange={(event) => setStableKey(event.target.value)}
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Nom public
                    </Label>
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        className={inputClass}
                    />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        % de la part utilisateur (0-100)
                    </Label>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        value={userSharePercentage}
                        onChange={(event) =>
                            setUserSharePercentage(event.target.value)
                        }
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Quota mensuel (facultatif — vide = illimité)
                    </Label>
                    <input
                        type="number"
                        min={1}
                        value={monthlyQuota}
                        onChange={(event) =>
                            setMonthlyQuota(event.target.value)
                        }
                        className={inputClass}
                    />
                </div>
            </div>

            <label className="flex items-center gap-2 text-xs font-medium text-[var(--text-primary)]">
                <input
                    type="checkbox"
                    checked={isDefault}
                    onChange={(event) => setIsDefault(event.target.checked)}
                />
                Type par défaut (appliqué à toute personne sans abonnement)
            </label>

            <Button type="submit" disabled={submitting}>
                {submitting ? 'Publication...' : 'Publier cette version'}
            </Button>
        </form>
    );
}

function TypeRow({ type }: { type: EconomicType }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-default)] px-4 py-3">
            <div className="min-w-0">
                <p className="text-sm font-medium text-[var(--text-primary)]">
                    {type.name}{' '}
                    <span className="text-[var(--text-secondary)]">
                        ({type.stable_key}, v{type.version})
                    </span>
                    {type.is_default && (
                        <span className="ml-2 text-xs text-[var(--brand-blue)]">
                            Par défaut
                        </span>
                    )}
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                    {type.user_share_percentage}% de la part utilisateur · Quota{' '}
                    {type.monthly_quota !== null
                        ? `${type.monthly_quota}/mois`
                        : 'illimité'}
                </p>
            </div>
            <Badge variant={type.state === 'active' ? 'default' : 'outline'}>
                {type.state === 'active' ? 'active' : 'retiré'}
            </Badge>
        </div>
    );
}

export default function AdminEconomicTypes({
    access,
    economicTypes,
}: {
    access: AdminAccess;
    economicTypes: EconomicType[];
}) {
    const [list, setList] = useState(economicTypes);

    function upsert(updated: EconomicType) {
        setList((current) => {
            const withoutUpdated = current.filter(
                (entry) => entry.id !== updated.id,
            );
            const cleared = updated.is_default
                ? withoutUpdated.map((entry) => ({
                      ...entry,
                      is_default: false,
                  }))
                : withoutUpdated;

            return [updated, ...cleared];
        });
    }

    return (
        <AdminLayout
            title="Types économiques"
            description="Les trois types économiques qui gouvernent la part utilisateur réelle d'un événement publicitaire (advertising.manage_economic_types)."
        >
            <Head title="Personnel — Types économiques" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Publier un type</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PublishForm onPublished={upsert} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Types existants</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {list.length === 0 ? (
                                    <p className="text-sm text-[var(--text-secondary)]">
                                        Aucun type publié pour le moment.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {list.map((type) => (
                                            <TypeRow
                                                key={type.id}
                                                type={type}
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
