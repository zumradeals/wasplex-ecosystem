import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import wallet from '@/routes/wallet';
import type { BreadcrumbItem } from '@/types';

type Balance = {
    currency: string;
    available: number;
    provisional: number;
    reserved: number;
};

type Access = {
    allowed: boolean;
    reason: string | null;
};

const amountFormatter = new Intl.NumberFormat('fr-FR');

// Wasplex ne présente jamais un motif de refus interne (grant, politique,
// définition de capacité — ADR-0004 §"décision explicable") : seul un
// texte destiné à la personne, jamais le code technique brut, est affiché.
const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant:
        "L'accès à votre Wallet n'est pas encore activé sur ce compte. Cet écran fonctionne, mais aucun droit de consultation ne vous a encore été accordé (P006, TD-0005-D).",
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

function AmountCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'available' | 'provisional' | 'reserved';
}) {
    const toneClassName =
        tone === 'available'
            ? 'text-wallet-gold'
            : tone === 'provisional'
              ? 'text-wallet-pending'
              : 'text-wallet-unknown';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-normal text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className={`text-2xl font-semibold ${toneClassName}`}>
                    {amountFormatter.format(value)}{' '}
                    <span className="text-base font-normal">WP</span>
                </p>
                <p className="text-sm text-muted-foreground">
                    ≈ {amountFormatter.format(value)} FCFA
                </p>
            </CardContent>
        </Card>
    );
}

export default function WalletOverview({
    access,
    balances,
}: {
    access: Access;
    balances: Balance[];
}) {
    return (
        <>
            <Head title="Wallet" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Votre Wallet"
                    description="1 WP = 1 FCFA. Le solde affiché est reconstruit depuis le registre comptable, jamais une valeur modifiable."
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

                {access.allowed && balances.length === 0 && (
                    <Alert>
                        <AlertTitle>Aucune opération enregistrée</AlertTitle>
                        <AlertDescription>
                            Vous n'avez encore reçu aucun droit WP. Cette page
                            se mettra à jour dès la première rémunération
                            créditée.
                        </AlertDescription>
                    </Alert>
                )}

                {access.allowed &&
                    balances.map((balance) => (
                        <section
                            key={balance.currency}
                            className="space-y-3"
                            aria-label={`Solde en ${balance.currency}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                {balance.currency}
                            </p>
                            <div className="grid gap-4 md:grid-cols-3">
                                <AmountCard
                                    label="Disponibles"
                                    value={balance.available}
                                    tone="available"
                                />
                                <AmountCard
                                    label="Provisoires"
                                    value={balance.provisional}
                                    tone="provisional"
                                />
                                <AmountCard
                                    label="Réservés"
                                    value={balance.reserved}
                                    tone="reserved"
                                />
                            </div>
                        </section>
                    ))}
            </div>
        </>
    );
}

WalletOverview.layout = {
    breadcrumbs: [
        {
            title: 'Wallet',
            href: wallet.show(),
        },
    ] satisfies BreadcrumbItem[],
};
