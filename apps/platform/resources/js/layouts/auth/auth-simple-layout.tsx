import { Link } from '@inertiajs/react';
import WasplexMascot from '@/components/wasplex-mascot';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-[#07182D] px-4 py-10">
            <div className="w-full max-w-sm space-y-8">
                {/* Logo + title */}
                <div className="flex flex-col items-center gap-3 text-center">
                    <Link
                        href={home()}
                        aria-label="Accueil Wasplex"
                        className="flex items-center gap-2"
                    >
                        <WasplexMascot className="h-10 w-10" />
                        <span className="text-xl font-bold tracking-tight text-white">
                            Wasplex
                        </span>
                    </Link>
                    {title && (
                        <h1 className="text-lg font-semibold text-[#F5F8FC]">
                            {title}
                        </h1>
                    )}
                    {description && (
                        <p className="text-sm text-[#A9B7C8]">{description}</p>
                    )}
                </div>

                {/* Form card */}
                <div className="rounded-2xl border border-[#35506D] bg-[#0E2542] p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}
