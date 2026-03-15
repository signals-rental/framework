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
                'products' => true,
                'services' => false,
                'stock' => true,
                'serialisation' => false,
                'opportunities' => true,
                'invoicing' => true,
                'credit_notes' => false,
                'purchase_orders' => false,
                'projects' => false,
                'crm' => false,
                'inspections' => false,
                'vehicles' => false,
                'quarantines' => false,
                'discussions' => false,
                'webhooks' => false,
                'crew' => false,
            ],
            self::FullService => [
                'products' => true,
                'services' => true,
                'stock' => true,
                'serialisation' => true,
                'opportunities' => true,
                'invoicing' => true,
                'credit_notes' => true,
                'purchase_orders' => true,
                'projects' => true,
                'crm' => true,
                'inspections' => true,
                'vehicles' => true,
                'quarantines' => true,
                'discussions' => true,
                'webhooks' => true,
                'crew' => true,
            ],
            self::Crew => [
                'products' => false,
                'services' => true,
                'stock' => false,
                'serialisation' => false,
                'opportunities' => true,
                'invoicing' => true,
                'credit_notes' => false,
                'purchase_orders' => false,
                'projects' => true,
                'crm' => false,
                'inspections' => false,
                'vehicles' => false,
                'quarantines' => false,
                'discussions' => true,
                'webhooks' => false,
                'crew' => true,
            ],
            self::General => [
                'products' => true,
                'services' => false,
                'stock' => true,
                'serialisation' => false,
                'opportunities' => true,
                'invoicing' => true,
                'credit_notes' => false,
                'purchase_orders' => false,
                'projects' => false,
                'crm' => true,
                'inspections' => false,
                'vehicles' => false,
                'quarantines' => false,
                'discussions' => true,
                'webhooks' => false,
                'crew' => true,
            ],
            self::Minimal => [
                'products' => true,
                'services' => false,
                'stock' => false,
                'serialisation' => false,
                'opportunities' => true,
                'invoicing' => false,
                'credit_notes' => false,
                'purchase_orders' => false,
                'projects' => false,
                'crm' => false,
                'inspections' => false,
                'vehicles' => false,
                'quarantines' => false,
                'discussions' => false,
                'webhooks' => false,
                'crew' => false,
            ],
        };
    }
}
