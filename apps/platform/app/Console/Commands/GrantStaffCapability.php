<?php

namespace App\Console\Commands;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\Exceptions\MultipleSystemAdministratorsRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SelfAuthorizationRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SeparationOfDutiesViolationException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bootstrap manuel des habilitations personnel Wasplex (P0, demande Koné
 * 2026-07-26 ; étendu 2026-07-30 après constat qu'aucun compte réel de
 * production — y compris celui du fondateur — n'avait jamais reçu la
 * moindre capacité admin, laissant chaque destination du portail
 * personnel afficher « Section indisponible ») : aucune des capacités
 * listées ci-dessous ne figure dans un rôle modèle auto-octroyé
 * (TD-0004-E) — par construction, aucune ne doit jamais être en
 * libre-service.
 *
 * `access.view`/`configuration.view`/`alert_case.review`/
 * `alert_case.publish`/`alert_match.validate`/`alert_return.verify` ont
 * été déclarées par des lots ultérieurs (P008-A, portail Configurations/
 * Accès) sans jamais être ajoutées à cet outil de bootstrap — écart
 * comblé ici, sans toucher au mécanisme lui-même. `alert_return.verify`
 * n'a encore aucun contrôleur d'action réel (seule la liste des
 * restitutions contestées existe, voir `AdminAlertsController`) : la
 * capacité peut être octroyée dès maintenant, l'action correspondante
 * reste à construire.
 *
 * N'invente aucun mécanisme d'autorisation : compose {@see GrantManager}
 * exactement comme le font déjà les tests de route (ex.
 * `CampaignFundingRouteTest::grantFund()`) — propose() puis activate(),
 * avec une portée `resource_type` seule (aucun `resource_ids` : une
 * habilitation personnel n'est jamais limitée à une liste de ressources
 * qui devrait être réélargie à chaque nouvelle campagne). Les portées
 * `alerts.case_category`/`governance.grant`/`governance.configuration_definition`
 * reprennent exactement celles déjà documentées par les migrations de
 * déclaration de chaque capacité (aucune valeur devinée ici). `activate()`
 * refuse toujours seul(e) auteur = approbateur, ou l'un des deux = sujet
 * (TD-0001-A) — ce garde-fou n'est jamais contourné ici, seulement
 * redemandé plus tôt avec un message clair, **sauf** pour l'attribution de
 * `governance.system_administrator` lui-même et pour tout octroi dont
 * l'auteur détient déjà ce rôle (addendum ADR-0004 2026-07-30, décision du
 * fondateur : un seul compte peut s'auto-amorcer puis s'accorder seul
 * n'importe quelle autre capacité — voir `GrantManager::activate()`).
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
        'access.view' => 'governance.grant',
        'configuration.view' => 'governance.configuration_definition',
        'alert_case.review' => 'alerts.case_category',
        'alert_case.publish' => 'alerts.case_category',
        'alert_match.validate' => 'alerts.case_category',
        'alert_return.verify' => 'alerts.case_category',
        // Amendement ADR-0004 2026-07-30 (« Rôle Administrateur Système ») :
        // resource_type nominal, jamais évalué par ScopeMatcher — voir la
        // migration de déclaration pour le raisonnement complet.
        'governance.system_administrator' => 'governance.system',
        'wallet_deposit.review' => 'wallet.deposit',
        // Véto du dirigeant 2026-07-30 (TD-0008-A) : resource_type nominal,
        // jamais évalué par ScopeMatcher — voir la migration de déclaration
        // pour le raisonnement complet.
        'wallet_deposit.manage_credentials' => 'wallet.deposit_provider_credentials',
    ];

    protected $signature = 'governance:grant-staff-capability
        {capability : Une des capacités personnel Wasplex (campaign.approve, campaign.fund, campaign.moderate, event.accept, event.reject, access.view, configuration.view, alert_case.review, alert_case.publish, alert_match.validate, alert_return.verify, governance.system_administrator, wallet_deposit.review, wallet_deposit.manage_credentials)}
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

        // Addendum ADR-0004 2026-07-30 (auto-amorçage de l'Administrateur
        // Système, décision du fondateur) : la garde de distinction
        // sujet/auteur/approbateur ne s'applique plus (1) à l'attribution de
        // `governance.system_administrator` lui-même — `GrantManager`
        // refuse de toute façon un second grant actif via
        // `MultipleSystemAdministratorsRefusedException` — ni (2) lorsque
        // l'auteur détient déjà ce rôle (il peut alors s'accorder n'importe
        // quelle autre capacité seul, cf. `GrantManager::activate()`).
        // Aucune règle dupliquée ici : seule source de vérité,
        // `GrantManager::isSystemAdministrator()`.
        $bootstrapExempt = $capabilityKey === 'governance.system_administrator'
            || $grantManager->isSystemAdministrator($author);

        if (! $bootstrapExempt && ($authorEmail === $approverEmail || $authorEmail === $subjectEmail || $approverEmail === $subjectEmail)) {
            $this->components->error(
                'Sujet, auteur et approbateur doivent être trois comptes distincts (séparation des tâches, TD-0001-A) — activate() les refuserait de toute façon.',
            );

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

        // `governance.grants_author_not_approver_check` (contrainte SQL,
        // 2026_07_23_100008) refuse tout approbateur identique à l'auteur,
        // sans exception — même dans le chemin d'auto-amorçage. Un
        // approbateur qui ne fait qu'y dupliquer l'auteur n'apporte de
        // toute façon aucune garantie réelle : on n'enregistre alors aucun
        // approbateur, exactement comme le fait déjà l'auto-octroi
        // (`GrantManager::activate()`, approbateur `null`).
        $approverForActivation = $bootstrapExempt && $approverEmail === $authorEmail ? null : $approver;

        try {
            $grantManager->activate($grant, $author, $approverForActivation, $correlationId);
        } catch (SelfAuthorizationRefusedException|SeparationOfDutiesViolationException|MultipleSystemAdministratorsRefusedException $exception) {
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
