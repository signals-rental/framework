<?php

namespace Database\Seeders;

use App\Models\ListName;
use App\Models\ListValue;
use Illuminate\Database\Seeder;

class ListOfValuesSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'Address Type' => ['Billing', 'Shipping', 'Primary', 'Registered'],
            'Email Type' => ['Work', 'Personal', 'Billing', 'Support'],
            'Phone Type' => ['Work', 'Mobile', 'Home', 'Fax'],
            'Link Type' => ['Website', 'LinkedIn', 'Facebook', 'Instagram', 'X (Twitter)', 'YouTube'],
            'Relationship Type' => ['Employee', 'Director', 'Contractor', 'Agent'],
            'Lawful Basis Type' => [
                'Legitimate interest - prospect/lead',
                'Legitimate interest - customer',
                'Legitimate interest - supplier',
                'Consent',
                'Contract',
                'Legal obligation',
                'Vital interests',
                'Public task',
                'Not applicable',
            ],
            'Location Type' => ['Internal', 'External'],
            'Rating' => ['None', '1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
            'Invoice Term' => ['Due on Receipt', 'Net 7', 'Net 14', 'Net 30', 'Net 45', 'Net 60', 'Net 90', 'End of Month'],
            'Locale' => ['en-GB', 'en-US', 'en-AU', 'en-NZ', 'en-CA', 'en-IE', 'en-ZA', 'fr-FR', 'de-DE', 'es-ES', 'nl-NL', 'pt-PT', 'it-IT', 'da-DK', 'sv-SE', 'nb-NO', 'fi-FI'],
            'File Category' => ['Contract', 'Invoice', 'Quote', 'Purchase Order', 'Certificate', 'Insurance', 'Photo', 'Floor Plan', 'Technical Spec', 'Health & Safety', 'Risk Assessment', 'Correspondence', 'Other'],
        ];

        foreach ($lists as $listName => $values) {
            $list = ListName::query()->updateOrCreate(
                ['name' => $listName],
                [
                    'description' => "{$listName} options",
                    'is_system' => true,
                ],
            );

            foreach ($values as $index => $value) {
                ListValue::query()->updateOrCreate(
                    ['list_name_id' => $list->id, 'name' => $value],
                    [
                        'sort_order' => $index,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
