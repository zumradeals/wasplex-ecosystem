<?php

namespace App\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Identity\Enums\SessionAssurance;

/**
 * Transporte explicitement, depuis le point de déclenchement jusqu'à
 * l'exécution différée, l'identité initiatrice, la capacité, la finalité et
 * la corrélation d'une commande ou d'un worker initié par une personne
 * (P003-B2 §E).
 *
 * Ne constitue jamais une décision d'autorisation : c'est une donnée
 * sérialisable simple, portée par la tâche différée, qui doit toujours être
 * revérifiée via {@see AuthenticatedSubjectResolver} puis
 * {@see AuthorizationGate} juste avant l'effet métier — jamais réutilisée
 * telle quelle comme preuve d'autorisation encore valide. Un worker
 * n'obtient donc jamais une identité système universelle : il ne peut agir
 * que pour la personne explicitement désignée ici.
 */
final readonly class DeferredAuthorizationContext
{
    public function __construct(
        public int $initiatorAccountUserId,
        public string $capabilityKey,
        public Operation $operation,
        public ?string $claimedMembershipId,
        public ?string $purposeKey,
        public string $correlationId,
        /**
         * Force de session constatée au moment du déclenchement. Ne doit
         * jamais être réinjectée telle quelle dans la réévaluation : une
         * session ne vit pas au-delà de sa propre requête HTTP. Un worker
         * réévalue toujours avec {@see SessionAssurance::Weak} sauf preuve
         * fraîche distincte disponible au moment de l'exécution (P003-B2 §E).
         */
        public SessionAssurance $sessionAssuranceAtDispatch,
    ) {}
}
