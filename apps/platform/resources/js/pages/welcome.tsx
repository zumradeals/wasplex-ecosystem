import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

// Chaque ligne paraphrase fidèlement un texte déjà adopté — rien
// d'inventé : Constitution art. 3 §1/§4/§6 (attention volontaire,
// condition de complétion), AMD-0003 (parité 1 WP = 1 FCFA), AMD-0001
// (donnée personnelle jamais un produit).
const explainer = [
    'Vous choisissez de regarder une publicité — jamais imposée, jamais automatique.',
    'Une fois la condition annoncée remplie, votre Wallet est crédité en WasPoints : 1 WP = 1 FCFA.',
    'Vos données ne sont jamais vendues ni exposées comme une base de contacts.',
];

export default function Welcome() {
    return (
        <>
            <Head title="Wasplex" />
            <div className="flex min-h-svh flex-col bg-[#F5F7FA] px-6 py-8 text-[#10233F] dark:bg-[#07182D] dark:text-[#F5F8FC]">
                <header className="flex justify-center">
                    <span className="text-lg font-semibold tracking-tight">
                        Wasplex
                    </span>
                </header>

                <main className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-8 py-8">
                    <h1 className="text-center text-2xl leading-tight font-bold text-balance sm:text-3xl">
                        Transformer l'attention publicitaire en valeur
                        économique traçable et redistribuée aux populations.
                    </h1>

                    <ul className="space-y-3">
                        {explainer.map((line) => (
                            <li
                                key={line}
                                className="rounded-lg border border-[#CBD5E1] bg-white p-4 text-sm dark:border-[#35506D] dark:bg-[#0E2542]"
                            >
                                {line}
                            </li>
                        ))}
                    </ul>
                </main>

                <footer className="mx-auto w-full max-w-sm space-y-3 pb-4">
                    <Button asChild size="lg" className="w-full">
                        <Link href={login()}>Se connecter</Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        size="lg"
                        className="w-full"
                    >
                        <Link href={register()}>Créer un compte</Link>
                    </Button>
                </footer>
            </div>
        </>
    );
}
