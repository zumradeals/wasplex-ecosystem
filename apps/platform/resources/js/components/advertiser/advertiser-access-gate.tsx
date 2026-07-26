import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ACCESS_DENIED_MESSAGES } from '@/lib/advertising-labels';
import advertising from '@/routes/advertising';

export type AdvertiserAccess = {
    allowed: boolean;
    reason: string | null;
};

export type AdvertiserProfileSummary = {
    id: string;
    legal_name: string;
    status: string;
    country_code: string;
    territories: string[];
};

/**
 * Écran partagé par les neuf destinations du portail annonceur
 * (UX-0002 §5 « contrat d'écran obligatoire », états accès refusé / vide) :
 * un refus d'autorisation ou l'absence de dossier annonceur restitue un
 * état explicatif, jamais un écran vide silencieux ni une redirection
 * inattendue.
 */
export function AdvertiserAccessGate({
    access,
    advertiserProfile,
    onMissingProfile,
    children,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    onMissingProfile?: ReactNode;
    children: ReactNode;
}) {
    if (!access.allowed) {
        return (
            <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4">
                <p className="text-sm font-semibold text-[var(--status-warning)]">
                    Consultation indisponible pour le moment
                </p>
                <p className="mt-1.5 text-sm text-[var(--text-secondary)]">
                    {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                        "Cet écran n'est pas encore disponible pour votre compte."}
                </p>
            </div>
        );
    }

    if (!advertiserProfile) {
        if (onMissingProfile) {
            return <>{onMissingProfile}</>;
        }

        return (
            <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4">
                <p className="text-sm font-semibold text-[var(--text-primary)]">
                    Aucun dossier annonceur déclaré
                </p>
                <p className="mt-1.5 text-sm text-[var(--text-secondary)]">
                    Déclarez votre dossier annonceur pour accéder à cet écran.
                </p>
                <Link
                    href={advertising.organization().url}
                    className="mt-3 inline-flex rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                >
                    Déclarer mon dossier annonceur
                </Link>
            </div>
        );
    }

    return <>{children}</>;
}
