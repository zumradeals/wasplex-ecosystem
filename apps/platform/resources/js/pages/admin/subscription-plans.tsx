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
import { postJson } from '@/lib/api';

type SubscriptionPlan = {
    id: string;
    stable_key: string;
    name: string;
    version: number;
    price_amount: number;
    currency: string;
    duration_days: number;
    economic_type_id: string;
    economic_type_name: string;
    state: 'active' | 'retired';
};

type EconomicTypeOption = { id: string; name: string };

const inputClass =
    'w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none';

function PublishForm({
    economicTypes,
    onPublished,
}: {
    economicTypes: EconomicTypeOption[];
    onPublished: (plan: SubscriptionPlan) => void;
}) {
    const [stableKey, setStableKey] = useState('');
    const [name, setName] = useState('');
    const [priceAmount, setPriceAmount] = useState('');
    const [currency, setCurrency] = useState('XOF');
    const [durationDays, setDurationDays] = useState('30');
    const [economicTypeId, setEconomicTypeId] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<SubscriptionPlan>(
            '/admin/subscription-plans',
            {
                stable_key: stableKey,
                name,
                price_amount: Number(priceAmount),
                currency,
                duration_days: Number(durationDays),
                economic_type_id: economicTypeId,
            },
        );

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La publication n'a pas abouti. Vérifiez les champs (clé technique en minuscules, prix > 0, un type économique choisi).",
            );

            return;
        }

        onPublished(result.data);
        setName('');
        setPriceAmount('');
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {error && (
                <p className="text-sm text-[var(--status-danger)]">{error}</p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Clé technique (ex. premium, minuscules)
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

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Prix
                    </Label>
                    <input
                        type="number"
                        min={1}
                        value={priceAmount}
                        onChange={(event) => setPriceAmount(event.target.value)}
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Devise
                    </Label>
                    <input
                        value={currency}
                        onChange={(event) =>
                            setCurrency(event.target.value.toUpperCase())
                        }
                        maxLength={3}
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Durée (jours)
                    </Label>
                    <input
                        type="number"
                        min={1}
                        value={durationDays}
                        onChange={(event) =>
                            setDurationDays(event.target.value)
                        }
                        className={inputClass}
                    />
                </div>
            </div>

            <div className="space-y-1.5">
                <Label className="text-xs font-medium text-[var(--text-primary)]">
                    Type économique rattaché
                </Label>
                <Select
                    value={economicTypeId || undefined}
                    onValueChange={setEconomicTypeId}
                >
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Choisir un type" />
                    </SelectTrigger>
                    <SelectContent>
                        {economicTypes.map((type) => (
                            <SelectItem key={type.id} value={type.id}>
                                {type.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <Button type="submit" disabled={submitting}>
                {submitting ? 'Publication...' : 'Publier cette version'}
            </Button>
        </form>
    );
}

function PlanRow({ plan }: { plan: SubscriptionPlan }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-default)] px-4 py-3">
            <div className="min-w-0">
                <p className="text-sm font-medium text-[var(--text-primary)]">
                    {plan.name}{' '}
                    <span className="text-[var(--text-secondary)]">
                        ({plan.stable_key}, v{plan.version})
                    </span>
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                    {plan.price_amount} {plan.currency} · {plan.duration_days}{' '}
                    jours · type {plan.economic_type_name}
                </p>
            </div>
            <Badge variant={plan.state === 'active' ? 'default' : 'outline'}>
                {plan.state === 'active' ? 'active' : 'retiré'}
            </Badge>
        </div>
    );
}

export default function AdminSubscriptionPlans({
    access,
    plans,
    economicTypes,
}: {
    access: AdminAccess;
    plans: SubscriptionPlan[];
    economicTypes: EconomicTypeOption[];
}) {
    const [list, setList] = useState(plans);

    function upsert(updated: SubscriptionPlan) {
        setList((current) => [
            updated,
            ...current.filter((entry) => entry.id !== updated.id),
        ]);
    }

    return (
        <AdminLayout
            title="Plans d'abonnement"
            description="Prix, durée et type économique rattaché de chaque plan (advertising.manage_subscription_plans)."
        >
            <Head title="Personnel — Plans d'abonnement" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <>
                        {economicTypes.length === 0 ? (
                            <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 text-sm text-[var(--text-secondary)]">
                                Aucun type économique actif : publiez-en un
                                d'abord depuis « Types économiques ».
                            </div>
                        ) : (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Publier un plan</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PublishForm
                                        economicTypes={economicTypes}
                                        onPublished={upsert}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Plans existants</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {list.length === 0 ? (
                                    <p className="text-sm text-[var(--text-secondary)]">
                                        Aucun plan publié pour le moment.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {list.map((plan) => (
                                            <PlanRow
                                                key={plan.id}
                                                plan={plan}
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
