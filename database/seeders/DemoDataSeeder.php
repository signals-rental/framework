<?php

namespace Database\Seeders;

use App\Models\Email;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Phone;
use App\Models\Store;
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
            ],
            [
                'name' => 'Manchester Depot',
                'street' => '45 Trade Park',
                'city' => 'Manchester',
                'county' => 'Greater Manchester',
                'postcode' => 'M1 1AA',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
            ],
            [
                'name' => 'Edinburgh Office',
                'street' => '78 Festival Square',
                'city' => 'Edinburgh',
                'county' => 'Midlothian',
                'postcode' => 'EH1 1BB',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, Member>  $members
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, Member>  $contacts
     * @param  \Illuminate\Database\Eloquent\Collection<int, Member>  $organisations
     * @param  \Illuminate\Database\Eloquent\Collection<int, Member>  $venues
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

    /** @codeCoverageIgnore */
    private function createDemoProducts(): void
    {
        // TODO: Implement when Product model exists
        // Spec: ~50 products across 5 groups (Lighting, Sound, Video, Staging, Power)
        // ~20 serialised assets with serial numbers
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
