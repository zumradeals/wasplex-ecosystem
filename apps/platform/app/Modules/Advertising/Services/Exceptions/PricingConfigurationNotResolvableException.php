<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * La configuration de prix épinglée par une `CampaignVersion`
 * (`pricing_configuration_key`/`version`) n'existe pas, n'a jamais été
 * mise en effet, ou n'est pas un montant entier (ADR-0002 §6, §7.3 :
 * échec fermé plutôt qu'un montant par défaut silencieux).
 */
class PricingConfigurationNotResolvableException extends RuntimeException {}
