export const amountFormatter = new Intl.NumberFormat('fr-FR');

export const CAMPAIGN_STATE_LABELS: Record<string, string> = {
    active: 'Active',
    suspended: 'Suspendue',
    closed: 'Terminée',
};

export const VERSION_STATE_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    in_review: 'En revue',
    approved: 'Approuvée',
    suspended: 'Suspendue',
    retired: 'Retirée',
};

export const BILLING_STATUS_LABELS: Record<string, string> = {
    pending: 'En attente de validation',
    accepted: 'Créditée',
    rejected: 'Refusée',
};

export const FORMAT_LABELS: Record<string, string> = {
    video: 'Vidéo',
    display: 'Affichage',
    banner: 'Bannière',
    audio: 'Audio',
};

/**
 * Wasplex n'expose jamais un motif de refus interne (grant, politique,
 * définition de capacité — ADR-0004 §"décision explicable") : seul un
 * texte destiné à la personne est affiché.
 */
export const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant:
        "L'accès à votre espace annonceur n'est pas encore activé sur ce compte.",
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

export function displayCampaignStatus(
    state: string,
    latestVersionState: string | null,
): string {
    if (latestVersionState === 'draft' || latestVersionState === 'in_review') {
        return VERSION_STATE_LABELS[latestVersionState];
    }

    return CAMPAIGN_STATE_LABELS[state] ?? state;
}
