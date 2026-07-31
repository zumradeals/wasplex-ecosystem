import { Head, router } from '@inertiajs/react';
import { ArrowRightLeft, Wallet as WalletIcon } from 'lucide-react';
import { useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
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
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { amountFormatter } from '@/lib/advertising-labels';
import { postJson } from '@/lib/api';
import { CURRENCIES } from '@/lib/currencies';

type WalletBalance = { currency: string; available: number };

type WalletCampaign = { id: string; code: string; currency: string };

type WalletDeposit = {
    id: string;
    state: string;
    currency: string;
    amount: number;
    created_at: string;
};

const DEPOSIT_STATE_LABELS: Record<string, string> = {
    draft: 'Non transmis',
    awaiting_provider: 'En cours',
    pending: 'En cours de confirmation',
    completed: 'Crédité',
    failed: 'Échoué',
    unknown_reconciliation: 'En vérification',
};

const inputClass =
    'w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none';

function DepositForm() {
    const [amount, setAmount] = useState('');
    const [currency, setCurrency] = useState('XOF');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        const parsed = Number(amount);

        if (!Number.isInteger(parsed) || parsed < 200) {
            setError('Le montant minimum est de 200.');

            return;
        }

        setSubmitting(true);
        setError(null);

        const result = await postJson<{ checkout_url: string }>(
            '/advertising/wallet/deposits',
            {
                amount: parsed,
                currency,
                idempotency_key: crypto.randomUUID(),
            },
        );

        if (result.ok) {
            window.location.href = result.data.checkout_url;

            return;
        }

        setSubmitting(false);
        setError(
            result.status === 503
                ? "Le service de paiement n'est pas disponible pour le moment."
                : "Le dépôt n'a pas pu être initié.",
        );
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Montant
                    </Label>
                    <input
                        type="number"
                        min={200}
                        step={1}
                        value={amount}
                        onChange={(event) => setAmount(event.target.value)}
                        placeholder="Ex. 5000"
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Devise
                    </Label>
                    <Select value={currency} onValueChange={setCurrency}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Devise" />
                        </SelectTrigger>
                        <SelectContent>
                            {[...CURRENCIES]
                                .sort((a, b) =>
                                    a.name.localeCompare(b.name, 'fr'),
                                )
                                .map((option) => (
                                    <SelectItem
                                        key={option.code}
                                        value={option.code}
                                    >
                                        {option.name} ({option.code})
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {error && (
                <p className="text-xs text-[var(--status-danger)]">{error}</p>
            )}

            <Button type="submit" disabled={submitting}>
                {submitting ? 'Redirection...' : 'Ajouter des fonds'}
            </Button>

            <p className="text-xs text-[var(--text-secondary)]">
                Redirige vers le paiement sécurisé GeniusPay. Le solde n'est
                crédité qu'après confirmation du paiement — jamais avant.
            </p>
        </form>
    );
}

function AllocationForm({ campaigns }: { campaigns: WalletCampaign[] }) {
    const [campaignId, setCampaignId] = useState('');
    const [amount, setAmount] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        const parsed = Number(amount);

        if (!campaignId) {
            setError('Choisissez une campagne.');

            return;
        }

        if (!Number.isInteger(parsed) || parsed < 1) {
            setError('Montant invalide.');

            return;
        }

        setSubmitting(true);
        setError(null);

        const result = await postJson('/advertising/wallet/allocations', {
            campaign_id: campaignId,
            amount: parsed,
            idempotency_key: crypto.randomUUID(),
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                result.status === 422
                    ? 'Solde Wallet insuffisant dans la devise de cette campagne.'
                    : "L'allocation n'a pas abouti.",
            );

            return;
        }

        setAmount('');
        router.reload({ only: ['balances', 'campaigns'] });
    }

    if (campaigns.length === 0) {
        return (
            <p className="text-sm text-[var(--text-secondary)]">
                Aucune campagne à financer pour le moment.
            </p>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Campagne
                    </Label>
                    <Select
                        value={campaignId || undefined}
                        onValueChange={setCampaignId}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choisir une campagne" />
                        </SelectTrigger>
                        <SelectContent>
                            {campaigns.map((campaign) => (
                                <SelectItem
                                    key={campaign.id}
                                    value={campaign.id}
                                >
                                    {campaign.code} ({campaign.currency})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Montant à allouer
                    </Label>
                    <input
                        type="number"
                        min={1}
                        step={1}
                        value={amount}
                        onChange={(event) => setAmount(event.target.value)}
                        placeholder="Ex. 2000"
                        className={inputClass}
                    />
                </div>
            </div>

            {error && (
                <p className="text-xs text-[var(--status-danger)]">{error}</p>
            )}

            <Button type="submit" disabled={submitting} variant="outline">
                {submitting ? 'Allocation...' : 'Allouer à cette campagne'}
            </Button>

            <p className="text-xs text-[var(--text-secondary)]">
                Déplace immédiatement ce montant du solde Wallet vers le budget
                disponible de la campagne choisie — un transfert interne, pas un
                nouveau paiement.
            </p>
        </form>
    );
}

export default function AdvertisingWallet({
    access,
    advertiserProfile,
    balances,
    campaigns,
    recentDeposits,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    balances: WalletBalance[];
    campaigns: WalletCampaign[];
    recentDeposits: WalletDeposit[];
}) {
    return (
        <AdvertiserLayout
            title="Wallet"
            description="Solde commun de votre espace annonceur : ajoutez des fonds une fois, puis allouez-les à la campagne de votre choix."
        >
            <Head title="Espace annonceur — Wallet" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                <div className="space-y-6">
                    {balances.length === 0 ? (
                        <AdvertiserEmptyState
                            icon={WalletIcon}
                            title="Wallet vide"
                            description="Ajoutez des fonds ci-dessous : ils deviendront disponibles dès la confirmation du paiement."
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {balances.map((balance) => (
                                <Card key={balance.currency}>
                                    <CardContent className="pt-6">
                                        <p className="text-xs text-[var(--text-secondary)]">
                                            Solde disponible ({balance.currency}
                                            )
                                        </p>
                                        <p className="mt-1 text-2xl font-bold text-[var(--text-primary)] tabular-nums">
                                            {amountFormatter.format(
                                                balance.available,
                                            )}{' '}
                                            <span className="text-sm font-normal text-[var(--text-secondary)]">
                                                {balance.currency}
                                            </span>
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Ajouter des fonds</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DepositForm />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ArrowRightLeft size={16} />
                                Allouer à une campagne
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AllocationForm campaigns={campaigns} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Dépôts récents</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentDeposits.length === 0 ? (
                                <p className="text-sm text-[var(--text-secondary)]">
                                    Aucun dépôt pour le moment.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                                <th className="px-4 py-2 font-medium">
                                                    Montant
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Statut
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Date
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[var(--border-default)]">
                                            {recentDeposits.map((deposit) => (
                                                <tr key={deposit.id}>
                                                    <td className="px-4 py-2.5 text-[var(--text-primary)] tabular-nums">
                                                        {amountFormatter.format(
                                                            deposit.amount,
                                                        )}{' '}
                                                        {deposit.currency}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-[var(--text-secondary)]">
                                                        {DEPOSIT_STATE_LABELS[
                                                            deposit.state
                                                        ] ?? deposit.state}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-[var(--text-secondary)]">
                                                        {new Date(
                                                            deposit.created_at,
                                                        ).toLocaleString(
                                                            'fr-FR',
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}
