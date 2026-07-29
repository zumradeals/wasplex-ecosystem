<?php

namespace App\Modules\Alerts\Enums;

/**
 * Catégorie d'un dossier (mission P008-A §8). Chaque valeur appartient à
 * une seule `CaseNature` — voir la contrainte `cases_nature_category_check`
 * (défense en profondeur, migration `..._create_alerts_cases_table`).
 */
enum CaseCategory: string
{
    // community
    case LostItem = 'lost_item';
    case FoundItem = 'found_item';
    case LostDocument = 'lost_document';
    case FoundDocument = 'found_document';
    case StolenVehicle = 'stolen_vehicle';
    case FoundVehicle = 'found_vehicle';
    case MissingPerson = 'missing_person';
    case FoundPerson = 'found_person';

    // sos
    case Fire = 'fire';
    case Accident = 'accident';
    case MedicalEmergency = 'medical_emergency';
    case RobberyInProgress = 'robbery_in_progress';

    public function nature(): CaseNature
    {
        return match ($this) {
            self::LostItem, self::FoundItem, self::LostDocument, self::FoundDocument,
            self::StolenVehicle, self::FoundVehicle, self::MissingPerson, self::FoundPerson => CaseNature::Community,
            self::Fire, self::Accident, self::MedicalEmergency, self::RobberyInProgress => CaseNature::Sos,
        };
    }

    /**
     * Catégories exigeant une revue renforcée avant toute publication
     * (AMD-0007 §8 ; ecosystem/alertes/02 §6 ; ecosystem/alertes/03 §2.3) —
     * jamais publiées automatiquement, même après vérification technique
     * minimale.
     */
    public function requiresReinforcedReview(): bool
    {
        return match ($this) {
            self::MissingPerson, self::FoundPerson, self::StolenVehicle,
            self::LostDocument, self::FoundDocument => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
