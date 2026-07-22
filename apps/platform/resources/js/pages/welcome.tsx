import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

const engagements = [
    {
        title: 'Attention volontaire',
        description:
            "Aucune sollicitation forcée. L'utilisateur choisit ce qu'il regarde et ce qu'il partage.",
    },
    {
        title: 'Données protégées',
        description:
            "Les données personnelles ne sont jamais vendues ni exposées comme une base de contacts.",
    },
    {
        title: 'Valeur traçable',
        description:
            'Chaque valeur générée est enregistrée, rapprochable et auditable, sans promesse de gain.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Wasplex" />
            <div className="min-h-screen bg-[#F5F7FA] text-[#10233F] dark:bg-[#07182D] dark:text-[#F5F8FC]">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-5">
                    <span className="text-xl font-semibold tracking-tight text-[#10233F] dark:text-[#F5F8FC]">
                        Wasplex
                    </span>
                    <nav className="flex items-center gap-3 text-sm">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md border border-[#CBD5E1] px-4 py-2 font-medium text-[#10233F] transition-colors hover:border-[#075CCF] hover:text-[#075CCF] dark:border-[#35506D] dark:text-[#F5F8FC] dark:hover:border-[#4FA3FF] dark:hover:text-[#4FA3FF]"
                            >
                                Tableau de bord
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-md px-4 py-2 font-medium text-[#10233F] transition-colors hover:text-[#075CCF] dark:text-[#F5F8FC] dark:hover:text-[#4FA3FF]"
                                >
                                    Se connecter
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-md bg-[#075CCF] px-4 py-2 font-medium text-white transition-colors hover:bg-[#0A4BA8] dark:bg-[#4FA3FF] dark:text-[#07182D] dark:hover:bg-[#70B7FF]"
                                >
                                    Créer un compte
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-5xl px-6 pt-10 pb-16 sm:pt-16">
                    <section className="max-w-2xl">
                        <p className="text-sm font-semibold tracking-wide text-[#007F9F] uppercase dark:text-[#2BC4DE]">
                            Wasplex
                        </p>
                        <h1 className="mt-3 text-3xl leading-tight font-bold text-balance text-[#10233F] sm:text-4xl dark:text-[#F5F8FC]">
                            Transformer l'attention publicitaire en valeur
                            économique traçable et redistribuée aux
                            populations.
                        </h1>
                    </section>

                    <section
                        aria-label="Nos engagements"
                        className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-3"
                    >
                        {engagements.map((engagement) => (
                            <div
                                key={engagement.title}
                                className="rounded-lg border border-[#CBD5E1] bg-[#FFFFFF] p-5 dark:border-[#35506D] dark:bg-[#0E2542]"
                            >
                                <h2 className="text-base font-semibold text-[#10233F] dark:text-[#F5F8FC]">
                                    {engagement.title}
                                </h2>
                                <p className="mt-2 text-sm text-[#53657D] dark:text-[#A9B7C8]">
                                    {engagement.description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="mx-auto w-full max-w-5xl px-6 pb-8 text-xs text-[#53657D] dark:text-[#A9B7C8]">
                    Wasplex
                </footer>
            </div>
        </>
    );
}
