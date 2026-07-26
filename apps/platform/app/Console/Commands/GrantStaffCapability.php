<?php

namespace App\Console\Commands;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\Exceptions\SelfAuthorizationRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SeparationOfDutiesViolationException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bootstrap manuel des toutes premières habilitations personnel Wasplex
 * (P0, demande Koné 2026-07-26) : `campaign.approve`, `campaign.fund`,
 * `event.accept`, `event.reject`, `campaign.moderate` ne figurent dans
 * aucun rôle modèle auto-octroyé (TD-0004-E) — par construction, aucune
 * de ces capacités ne doit jamais être en libre-service.
 *
 * N'invente aucun mécanisme d'autorisation : compose {@see GrantManager}
 * exactement comme le font déjà les tests de route (ex.
 * `CampaignFundingRouteTest::grantFund()`) — propose() puis activate(),
 * avec une portée `resource_type` seule (aucun `resource_ids` : une
 * habilitation personnel n'est jamais limitée à une liste de ressources
 * qui devrait être réélargie à chaque nouvelle campagne). `activate()`
 * refuse toujours seul(e) auteur = approbateur, ou l'un des deux = sujet
 * (TD-0001-A) — ce garde-fou n'est jamais contourné ici, seulement
 * redemandé plus tôt avec un message clair.
 */
class GrantStaffCapability extends Command
{
    /**
     * @var array<string, string>
     */
    private const CAPABILITY_RESOURCE_TYPES = [
        'campaign.approve' => 'advertising.advertiser_profile',
        'campaign.fund' => 'advertising.campaign',
        'campaign.moderate' => 'advertising.moderation_case',
        'event.accept' => 'advertising.qualified_event',
        'event.reject' => 'advertising.qualified_event',
    ];

    protected $signature = 'governance:grant-staff-capability
        {capability : Une des capacités personnel Wasplex (campaign.approve, campaign.fund, campaign.moderate, event.accept, event.reject)}
        {subject-email : E-mail du compte qui recevra le droit}
        {author-email : E-mail du compte qui propose ce grant}
        {approver-email : E-mail du compte qui approuve ce grant (distinct du sujet et de l\'auteur)}';

    protected $description = 'Octroie manuellement une capacité personnel Wasplex (jamais en libre-service) à un compte, via le cycle propose()+activate() existant de GrantManager.';

    public function handle(GrantManager $grantManager): int
    {
        $capabilityKey = $this->argument('capability');
        $resourceType = self::CAPABILITY_RESOURCE_TYPES[$capabilityKey] ?? null;

        if ($resourceType === null) {
            $this->components->error(sprintf(
                'Capacité inconnue de cette commande : %s. Attendu : %s.',
                $capabilityKey,
                implode(', ', array_keys(self::CAPABILITY_RESOURCE_TYPES)),
            ));

            return self::FAILURE;
        }

        $subjectEmail = $this->argument('subject-email');
        $authorEmail = $this->argument('author-email');
        $approverEmail = $this->argument('approver-email');

        if ($authorEmail === $approverEmail || $authorEmail === $subjectEmail || $approverEmail === $subjectEmail) {
            $this->components->error(
                'Sujet, auteur et approbateur doivent être trois comptes distincts (séparation des tâches, TD-0001-A) — activate() les refuserait de toute façon.',
            );

            return self::FAILURE;
        }

        $capability = CapabilityDefinition::query()
            ->where('stable_key', $capabilityKey)
            ->where('state', 'active')
            ->first();

        if ($capability === null) {
            $this->components->error("Capacité «{$capabilityKey}» introuvable ou non active.");

            return self::FAILURE;
        }

        $subject = $this->resolveLink($subjectEmail);
        $author = $this->resolveLink($authorEmail);
        $approver = $this->resolveLink($approverEmail);

        if ($subject === null || $author === null || $approver === null) {
            return self::FAILURE;
        }

        $policy = PolicyVersion::create([
            'stable_key' => "staff_grant_{$capabilityKey}_".Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', $capabilityKey.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $correlationId = (string) Str::uuid();

        $grant = $grantManager->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['resource_type' => $resourceType]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: $correlationId,
        );

        try {
            $grantManager->activate($grant, $author, $approver, $correlationId);
        } catch (SelfAuthorizationRefusedException|SeparationOfDutiesViolationException $exception) {
            $this->components->error("Activation refusée : {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(
            "Grant «{$capabilityKey}» actif pour {$subjectEmail} (portée resource_type={$resourceType}, aucun resource_ids — couvre toute ressource de ce type).",
        );

        return self::SUCCESS;
    }

    private function resolveLink(string $email): ?PersonAccountLink
    {
        $link = PersonAccountLink::query()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->first();

        if ($link === null) {
            $this->components->error("Aucun compte actif trouvé pour {$email}.");
        }

        return $link;
    }
}
