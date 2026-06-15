<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Email;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Phone;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->createDemoStores();
        $this->createDemoMembers();
        $this->createDemoProducts();
        $this->createDemoOpportunities();
        $this->createDemoInvoices();
        $this->createDemoCustomFields();
        $this->createDemoActivities();
    }

    private function createDemoStores(): void
    {
        $demoStores = [
            [
                'name' => 'London Warehouse',
                'street' => '123 Industrial Way',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'E1 6AN',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
                'tag_list' => ['demo-data'],
            ],
            [
                'name' => 'Manchester Depot',
                'street' => '45 Trade Park',
                'city' => 'Manchester',
                'county' => 'Greater Manchester',
                'postcode' => 'M1 1AA',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
                'tag_list' => ['demo-data'],
            ],
            [
                'name' => 'Edinburgh Office',
                'street' => '78 Festival Square',
                'city' => 'Edinburgh',
                'county' => 'Midlothian',
                'postcode' => 'EH1 1BB',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
                'tag_list' => ['demo-data'],
            ],
        ];

        foreach ($demoStores as $storeData) {
            Store::create($storeData);
        }
    }

    private function createDemoMembers(): void
    {
        $this->command->info('Seeding 2,000 organisations...');
        $organisations = Member::factory()
            ->organisation()
            ->count(2000)
            ->create(['tag_list' => ['demo-data']]);

        $this->createContactDetailsForMembers($organisations);

        $this->command->info('Seeding 500 venues...');
        $venues = Member::factory()
            ->venue()
            ->count(500)
            ->create(['tag_list' => ['demo-data']]);

        $this->createContactDetailsForMembers($venues);

        $this->command->info('Seeding 3,000 contacts...');
        $contacts = Member::factory()
            ->contact()
            ->count(3000)
            ->create(['tag_list' => ['demo-data']]);

        $this->createContactDetailsForMembers($contacts);

        $this->command->info('Creating relationships between contacts and organisations/venues...');
        $this->createRelationships($contacts, $organisations, $venues);
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    private function createContactDetailsForMembers($members): void
    {
        $emailInserts = [];
        $phoneInserts = [];
        $now = now();

        foreach ($members as $member) {
            $emailInserts[] = [
                'emailable_type' => Member::class,
                'emailable_id' => $member->id,
                'address' => fake()->unique()->safeEmail(),
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $phoneInserts[] = [
                'phoneable_type' => Member::class,
                'phoneable_id' => $member->id,
                'number' => fake()->phoneNumber(),
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($emailInserts, 500) as $chunk) {
            Email::insert($chunk);
        }

        foreach (array_chunk($phoneInserts, 500) as $chunk) {
            Phone::insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Member>  $contacts
     * @param  Collection<int, Member>  $organisations
     * @param  Collection<int, Member>  $venues
     */
    private function createRelationships($contacts, $organisations, $venues): void
    {
        $relationshipTypes = ['Employee', 'Director', 'Contractor', 'Consultant', 'Manager'];
        $venueRelationshipTypes = ['Event Manager', 'Site Contact', 'Venue Manager', 'Technical Contact'];
        $inserts = [];
        $now = now();

        foreach ($contacts as $index => $contact) {
            // Every contact belongs to an organisation
            $org = $organisations[$index % $organisations->count()];
            $inserts[] = [
                'member_id' => $contact->id,
                'related_member_id' => $org->id,
                'relationship_type' => $relationshipTypes[array_rand($relationshipTypes)],
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // ~30% of contacts also linked to a venue
            if ($index % 3 === 0) {
                $venue = $venues[$index % $venues->count()];
                $inserts[] = [
                    'member_id' => $contact->id,
                    'related_member_id' => $venue->id,
                    'relationship_type' => $venueRelationshipTypes[array_rand($venueRelationshipTypes)],
                    'is_primary' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            MemberRelationship::insert($chunk);
        }
    }

    /**
     * Seed a realistic spread of demo products across the seeded catalogue
     * groups, mixing rental/sale/service types and serialised/bulk stock
     * methods. Each record is tagged 'demo-data' so signals:clear-demo can
     * remove them. Keyed on name via firstOrCreate so re-runs are idempotent.
     */
    private function createDemoProducts(): void
    {
        $this->command->info('Seeding demo products...');

        $groupIds = ProductGroup::query()->pluck('id', 'name');

        $products = [
            [
                'name' => 'Demo - Sennheiser EW100 G4 Radio Mic',
                'description' => 'Wireless handheld radio microphone system.',
                'group' => 'Audio',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 49900,
                'sku' => 'DEMO-AUD-EW100',
            ],
            [
                'name' => 'Demo - XLR Cable 5m',
                'description' => 'Balanced microphone cable, bulk stock.',
                'group' => 'Audio',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 1200,
                'sku' => 'DEMO-AUD-XLR5',
            ],
            [
                'name' => 'Demo - LED PAR Can RGBW',
                'description' => 'Compact LED uplighter, RGBW colour mixing.',
                'group' => 'Lighting - Generic',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 19900,
                'sku' => 'DEMO-LGN-PARRGBW',
            ],
            [
                'name' => 'Demo - Moving Head Beam 230W',
                'description' => 'Sharp-beam moving head fixture.',
                'group' => 'Lighting - Moving Heads',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 129900,
                'weight' => 16.5000,
                'sku' => 'DEMO-LMH-BEAM230',
            ],
            [
                'name' => 'Demo - 55" LED Display Screen',
                'description' => 'Full HD display panel with floor stand.',
                'group' => 'Video',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 89900,
                'sku' => 'DEMO-VID-LED55',
            ],
            [
                'name' => 'Demo - Stage Deck 2m x 1m',
                'description' => 'Modular staging deck, anti-slip surface.',
                'group' => 'Staging',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 24900,
                'weight' => 22.0000,
                'sku' => 'DEMO-STG-DECK2X1',
            ],
            [
                'name' => 'Demo - 16A Power Distro',
                'description' => '16A power distribution unit with RCD protection.',
                'group' => 'Power',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 34900,
                'sku' => 'DEMO-PWR-DISTRO16',
            ],
            [
                'name' => 'Demo - Gaffer Tape 50mm Black',
                'description' => 'Matt black cloth tape, consumable sale item.',
                'group' => 'Consumables',
                'product_type' => ProductType::Sale,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 900,
                'purchase_price' => 650,
                'sku' => 'DEMO-CON-GAF50',
            ],
            [
                'name' => 'Demo - Folding Banquet Chair',
                'description' => 'Stackable padded event chair.',
                'group' => 'Furniture',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 4900,
                'sku' => 'DEMO-FUR-CHAIR',
            ],
            [
                'name' => 'Demo - On-Site Technician Day Rate',
                'description' => 'Crew labour service line.',
                'group' => null,
                'product_type' => ProductType::Service,
                'stock_method' => StockMethod::Bulk,
                'sku' => 'DEMO-SVC-TECH',
            ],
        ];

        foreach ($products as $data) {
            $group = $data['group'];
            unset($data['group']);

            Product::query()->firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'product_group_id' => $group !== null ? $groupIds->get($group) : null,
                    'is_active' => true,
                    'tag_list' => ['demo-data'],
                ]),
            );
        }
    }

    /** @codeCoverageIgnore */
    private function createDemoOpportunities(): void
    {
        // TODO: Implement when Opportunity model exists
        // Spec: ~20 opportunities in various states (draft, quotation, order)
    }

    /** @codeCoverageIgnore */
    private function createDemoInvoices(): void
    {
        // TODO: Implement when Invoice model exists
        // Spec: ~10 invoices (open, issued, paid)
    }

    /** @codeCoverageIgnore */
    private function createDemoCustomFields(): void
    {
        // TODO: Implement with custom field example values
    }

    /** @codeCoverageIgnore */
    private function createDemoActivities(): void
    {
        // TODO: Implement when Activity model exists
        // Spec: activities and discussions on demo records
    }
}
