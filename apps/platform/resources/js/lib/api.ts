/**
 * Les routes advertising.* de type action (POST) restituent volontairement
 * du JSON brut plutôt qu'une réponse Inertia (voir CampaignController et
 * consorts, routes/web.php) : un appel non authentifié doit recevoir un 401
 * JSON structuré, jamais une redirection de page. Le routeur Inertia
 * (`router.post`) attend une réponse Inertia ou une redirection — il ne
 * convient donc pas ici ; ces formulaires appellent directement l'API via
 * `fetch`.
 *
 * Laravel chiffre le cookie XSRF-TOKEN et ne le déchiffre que côté serveur
 * (VerifyCsrfToken) : il n'est jamais nécessaire — ni possible — de le
 * déchiffrer ici ; on renvoie simplement sa valeur telle quelle dans
 * l'en-tête X-XSRF-TOKEN (même mécanique que l'intégration XSRF d'axios).
 */
function xsrfTokenFromCookie(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

export type ApiResult<T> =
    | { ok: true; status: number; data: T }
    | { ok: false; status: number; data: unknown };

export async function postJson<T = unknown>(
    url: string,
    body: unknown,
): Promise<ApiResult<T>> {
    const xsrfToken = xsrfTokenFromCookie();

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: JSON.stringify(body),
    });

    const data = (await response.json().catch(() => null)) as T;

    return response.ok
        ? { ok: true, status: response.status, data }
        : { ok: false, status: response.status, data };
}

/**
 * Mirroir de `postJson` pour un envoi `multipart/form-data` (upload de
 * fichier, Lot 4) : jamais de `Content-Type` posé à la main — le
 * navigateur fixe lui-même la frontière multipart exacte.
 */
export async function postFormData<T = unknown>(
    url: string,
    formData: FormData,
): Promise<ApiResult<T>> {
    const xsrfToken = xsrfTokenFromCookie();

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: formData,
    });

    const data = (await response.json().catch(() => null)) as T;

    return response.ok
        ? { ok: true, status: response.status, data }
        : { ok: false, status: response.status, data };
}
