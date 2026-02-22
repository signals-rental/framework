<?php

namespace App\Enums;

enum FeatureProfile: string
{
    case DryHire = 'dry_hire';
    case FullService = 'full_service';
    case Crew = 'crew';
    case General = 'general';
    case Minimal = 'minimal';

    public function label(): string
    {
        return match ($this) {
            self::DryHire => 'Dry Hire',
            self::FullService => 'Full Service',
            self::Crew => 'Crew & Services',
            self::General => 'General',
            self::Minimal => 'Minimal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DryHire => 'Equipment rental without crew or services. Ideal for AV, lighting, and sound hire.',
            self::FullService => 'Equipment rental with crew, transport, and services. For full event production.',
            self::Crew => 'Focus on crew and service management with minimal stock tracking.',
            self::General => 'All modules enabled. Best for companies covering multiple rental types.',
            self::Minimal => 'Core quoting and ordering only. Add modules later as you grow.',
        };
    }

    /**
     * Get the module toggle map for this profile.
     *
     * @return array<string, bool>
     */
    public function modules(): array
    {
        return match ($this) {
            self::DryHire => [
                'opportunities' => true,
                'products' => true,
                'stock' => true,
                'invoicing' => true,
                'crm' => true,
                'crew' => false,
                'services' => false,
                'projects' => false,
                'inspections' => true,
            ],
            self::FullService => [
                'opportunities' => true,
                'products' => true,
                'stock' => true,
                'invoicing' => true,
                'crm' => true,
                'crew' => true,
                'services' => true,
                'projects' => true,
                'inspections' => true,
            ],
            self::Crew => [
                'opportunities' => true,
                'products' => false,
                'stock' => false,
                'invoicing' => true,
                'crm' => true,
                'crew' => true,
                'services' => true,
                'projects' => true,
                'inspections' => false,
            ],
            self::General => [
                'opportunities' => true,
                'products' => true,
                'stock' => true,
                'invoicing' => true,
                'crm' => true,
                'crew' => true,
                'services' => true,
                'projects' => true,
                'inspections' => true,
            ],
            self::Minimal => [
                'opportunities' => true,
                'products' => true,
                'stock' => false,
                'invoicing' => true,
                'crm' => true,
                'crew' => false,
                'services' => false,
                'projects' => false,
                'inspections' => false,
            ],
        };
    }
}
