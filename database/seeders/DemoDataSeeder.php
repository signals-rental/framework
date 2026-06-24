<?php

namespace Database\Seeders;

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\MembershipType;
use App\Enums\OpportunityItemType;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Accessory;
use App\Models\Activity;
use App\Models\Email;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Phone;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

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

        $this->seedDemoProductAccessories();
    }

    /**
     * Link included catalogue accessories so product adds can auto-materialise
     * nested accessory rows in the unified line-item tree.
     */
    private function seedDemoProductAccessories(): void
    {
        $mic = Product::query()->where('name', 'Demo - Sennheiser EW100 G4 Radio Mic')->first();
        $cable = Product::query()->where('name', 'Demo - XLR Cable 5m')->first();
        $movingHead = Product::query()->where('name', 'Demo - Moving Head Beam 230W')->first();
        $distro = Product::query()->where('name', 'Demo - 16A Power Distro')->first();

        if ($mic !== null && $cable !== null) {
            Accessory::query()->firstOrCreate(
                [
                    'product_id' => $mic->id,
                    'accessory_product_id' => $cable->id,
                ],
                [
                    'quantity' => '1',
                    'included' => true,
                    'sort_order' => 1,
                ],
            );
        }

        if ($movingHead !== null && $distro !== null) {
            Accessory::query()->firstOrCreate(
                [
                    'product_id' => $movingHead->id,
                    'accessory_product_id' => $distro->id,
                ],
                [
                    'quantity' => '1',
                    'included' => true,
                    'sort_order' => 1,
                ],
            );
        }
    }

    /**
     * Seed a realistic spread of demo opportunities across the lifecycle: a few
     * drafts, quotations and confirmed orders, each with manually-priced line
     * items drawn from the demo catalogue.
     *
     * Opportunities are event-sourced (Verbs): every record is created through
     * the REAL action path (CreateOpportunity → AddOpportunityGroup/Item →
     * ConvertToQuotation → ConvertToOrder) so the opportunities projection,
     * demands and availability snapshots all stay consistent — never a raw
     * Opportunity::create(). The 'demo-data' tag is written onto the projection
     * afterwards (it is metadata, not lifecycle state, so it is not an event
     * field) so signals:clear-demo can identify and remove these rows.
     *
     * Re-runs are idempotent: each opportunity is keyed on a unique demo
     * `reference` and skipped when a 'demo-data'-tagged row with that reference
     * already exists, so re-seeding never duplicates or accumulates.
     */
    private function createDemoOpportunities(): void
    {
        $this->command->info('Seeding demo opportunities...');

        $owner = User::query()->first();
        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        $organisations = Member::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->where('membership_type', MembershipType::Organisation)
            ->limit(20)
            ->get();

        $products = Product::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->whereIn('product_type', [ProductType::Rental, ProductType::Sale])
            ->get();

        if ($owner === null || $store === null || $organisations->isEmpty() || $products->isEmpty()) {
            return;
        }

        // Authorise the action-class Gate checks as the seeded owner so the
        // event-sourced creation path runs exactly as it would for a real user.
        Auth::setUser($owner);

        $window = [
            'starts_at' => now()->addWeek()->setTime(9, 0)->toIso8601String(),
            'ends_at' => now()->addWeek()->addDays(3)->setTime(17, 0)->toIso8601String(),
        ];

        // [reference, subject, target lifecycle stage]. 'draft' stops after items
        // are added; 'quotation' converts to a quote; 'order' converts through to
        // a confirmed order. A spread across all three exercises the search block,
        // demand projections and the index counts. Headline refs 0001 + 0013 carry
        // rich three-level group → product → accessory trees.
        $blueprints = [
            ['DEMO-OPP-0001', 'Summer Festival Main Stage', 'order'],
            ['DEMO-OPP-0002', 'Corporate AGM AV Package', 'order'],
            ['DEMO-OPP-0003', 'Product Launch Lighting Rig', 'quotation'],
            ['DEMO-OPP-0004', 'Awards Dinner Sound System', 'quotation'],
            ['DEMO-OPP-0005', 'Conference Breakout Rooms', 'quotation'],
            ['DEMO-OPP-0006', 'Wedding Marquee Hire', 'draft'],
            ['DEMO-OPP-0007', 'Trade Show Booth Fit-Out', 'draft'],
            ['DEMO-OPP-0008', 'Charity Gala Staging', 'order'],
            ['DEMO-OPP-0013', 'Arena Tour Production Package', 'order'],
        ];

        foreach ($blueprints as $index => [$reference, $subject, $stage]) {
            $exists = Opportunity::query()
                ->whereJsonContains('tag_list', 'demo-data')
                ->where('reference', $reference)
                ->exists();

            if ($exists) {
                continue;
            }

            $member = $organisations[$index % $organisations->count()];

            $data = OpportunityData::from($this->buildDemoOpportunity(
                $subject,
                $reference,
                $member->id,
                $store->id,
                $owner->id,
                $window,
            ));

            $opportunity = Opportunity::query()->whereKey($data->id)->firstOrFail();

            if (in_array($reference, ['DEMO-OPP-0001', 'DEMO-OPP-0013'], true)) {
                $this->seedRichDemoLineTree($opportunity, $window);
            } else {
                $this->seedStandardDemoLines($opportunity, $window, $index, $products);
            }

            $opportunity->refresh();

            if ($stage === 'quotation' || $stage === 'order') {
                (new ConvertToQuotation)($opportunity);
                $opportunity->refresh();
            }

            if ($stage === 'order') {
                (new ConvertToOrder)($opportunity);
                $opportunity->refresh();
            }

            // Tag the projection so signals:clear-demo can find and remove it.
            // tag_list is presentation metadata, not lifecycle state, so it is
            // written directly rather than through an event.
            $opportunity->forceFill(['tag_list' => ['demo-data']])->save();
        }

        Auth::forgetUser();
    }

    /**
     * Headline demo opportunities: group → nested products → auto-materialised
     * included accessories, plus a top-level crew service line.
     *
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function seedRichDemoLineTree(Opportunity $opportunity, array $window): void
    {
        $audioGroup = $this->addDemoGroup($opportunity, 'Main Stage Audio');
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Demo - Sennheiser EW100 G4 Radio Mic'),
            $window,
            parentPath: $audioGroup->path,
            quantity: '2',
            unitPrice: 3500,
        );
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Demo - Moving Head Beam 230W'),
            $window,
            parentPath: $audioGroup->path,
            quantity: '4',
            unitPrice: 8500,
        );

        $lightingGroup = $this->addDemoGroup($opportunity, 'Lighting Package');
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Demo - LED PAR Can RGBW'),
            $window,
            parentPath: $lightingGroup->path,
            quantity: '8',
            unitPrice: 1800,
        );
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Demo - 55" LED Display Screen'),
            $window,
            parentPath: $lightingGroup->path,
            quantity: '2',
            unitPrice: 12000,
        );

        $stagingGroup = $this->addDemoGroup($opportunity, 'Staging & Decks');
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Demo - Stage Deck 2m x 1m'),
            $window,
            parentPath: $stagingGroup->path,
            quantity: '12',
            unitPrice: 4500,
        );

        $this->addDemoServiceLine(
            $opportunity,
            $this->demoProduct('Demo - On-Site Technician Day Rate'),
            $window,
            quantity: '3',
            unitPrice: 45000,
        );
    }

    /**
     * Standard demo opportunities: a mix of grouped, flat, and service lines.
     *
     * @param  array{starts_at: string, ends_at: string}  $window
     * @param  Collection<int, Product>  $products
     */
    private function seedStandardDemoLines(
        Opportunity $opportunity,
        array $window,
        int $index,
        Collection $products,
    ): void {
        $lineProducts = $products->values();

        if ($index % 3 === 0) {
            $group = $this->addDemoGroup($opportunity, 'Equipment Package');
            $first = $lineProducts[$index % $lineProducts->count()];
            $second = $lineProducts[($index + 1) % $lineProducts->count()];

            $this->addDemoProductLine($opportunity, $first, $window, parentPath: $group->path, quantity: '2', unitPrice: 2500);
            $this->addDemoProductLine($opportunity, $second, $window, parentPath: $group->path, quantity: '1', unitPrice: 5000);

            return;
        }

        if ($index % 3 === 1) {
            foreach ($lineProducts->shuffle()->take(2) as $position => $product) {
                $this->addDemoProductLine(
                    $opportunity,
                    $product,
                    $window,
                    quantity: (string) (($position % 3) + 1),
                    unitPrice: (($position + 1) * 2500),
                );
            }

            return;
        }

        $product = $lineProducts[$index % $lineProducts->count()];
        $this->addDemoProductLine($opportunity, $product, $window, quantity: '1', unitPrice: 3000);
        $this->addDemoServiceLine(
            $opportunity,
            $this->demoProduct('Demo - On-Site Technician Day Rate'),
            $window,
            quantity: '1',
            unitPrice: 45000,
        );
    }

    private function demoProduct(string $name): Product
    {
        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function addDemoGroup(Opportunity $opportunity, string $name, ?string $parentPath = null): OpportunityItem
    {
        (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
            'name' => $name,
            'parent_path' => $parentPath,
        ]));

        $opportunity->refresh();

        return $opportunity->items()
            ->where('name', $name)
            ->where('item_type', OpportunityItemType::Group)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function addDemoProductLine(
        Opportunity $opportunity,
        Product $product,
        array $window,
        ?string $parentPath = null,
        string $quantity = '1',
        int $unitPrice = 2500,
    ): void {
        $productType = ProductType::from((string) $product->getRawOriginal('product_type'));

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'parent_path' => $parentPath,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'transaction_type' => $productType === ProductType::Sale
                ? LineItemTransactionType::Sale->value
                : LineItemTransactionType::Rental->value,
            'starts_at' => $window['starts_at'],
            'ends_at' => $window['ends_at'],
        ]));
    }

    /**
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function addDemoServiceLine(
        Opportunity $opportunity,
        Product $product,
        array $window,
        string $quantity = '1',
        int $unitPrice = 45000,
    ): void {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Service->value,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'transaction_type' => LineItemTransactionType::Service->value,
            'starts_at' => $window['starts_at'],
            'ends_at' => $window['ends_at'],
        ]));
    }

    /**
     * Build the CreateOpportunity action result for one demo opportunity.
     *
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function buildDemoOpportunity(
        string $subject,
        string $reference,
        int $memberId,
        int $storeId,
        int $ownerId,
        array $window,
    ): OpportunityData {
        return (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => $subject,
            'reference' => $reference,
            'member_id' => $memberId,
            'store_id' => $storeId,
            'owned_by' => $ownerId,
            'starts_at' => $window['starts_at'],
            'ends_at' => $window['ends_at'],
        ]));
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

    /**
     * Seed a realistic spread of demo activities (tasks, calls, meetings,
     * emails, notes) across the demo members. Each record is tagged
     * 'demo-data' so signals:clear-demo can remove them. Re-runs are idempotent:
     * each activity is skipped when a demo-tagged row with the same subject
     * already exists. The existence check is scoped to the 'demo-data' tag (the
     * same marker signals:clear-demo uses) so re-seeding never collides with a
     * real user's activity that happens to share a subject.
     */
    private function createDemoActivities(): void
    {
        $this->command->info('Seeding demo activities...');

        $user = User::query()->first();

        if ($user === null) {
            return;
        }

        // Anchor a few activities on demo members so the "Regarding" links work.
        $demoMembers = Member::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->limit(3)
            ->get();

        $activities = [
            [
                'subject' => 'Follow up on rental quote',
                'state' => 'task',
                'starts_at' => now()->addDay(),
                'regarding' => $demoMembers->first(),
            ],
            [
                'subject' => 'Confirm delivery schedule',
                'state' => 'call',
                'starts_at' => now()->addHours(3),
            ],
            [
                'subject' => 'Site visit for upcoming event',
                'state' => 'meeting',
                'location' => 'Client Office',
            ],
            [
                'subject' => 'Send updated price list',
                'state' => 'email',
            ],
            [
                'subject' => 'Customer prefers Friday deliveries',
                'state' => 'note',
                'description' => 'Noted during last phone call that Friday mornings work best for the customer.',
                'regarding' => $demoMembers->get(1),
            ],
            [
                'subject' => 'Initial consultation completed',
                'state' => 'completed',
            ],
        ];

        foreach ($activities as $data) {
            $existing = Activity::query()
                ->whereJsonContains('tag_list', 'demo-data')
                ->where('subject', $data['subject'])
                ->exists();

            if ($existing) {
                continue;
            }

            /** @var Member|null $regarding */
            $regarding = $data['regarding'] ?? null;

            Activity::factory()
                ->{$data['state']}()
                ->create([
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'owned_by' => $user->id,
                    'starts_at' => $data['starts_at'] ?? null,
                    'regarding_type' => $regarding !== null ? Member::class : null,
                    'regarding_id' => $regarding?->id,
                    'tag_list' => ['demo-data'],
                ]);
        }
    }
}
