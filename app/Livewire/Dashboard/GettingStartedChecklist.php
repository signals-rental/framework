<?php

namespace App\Livewire\Dashboard;

use App\Models\Store;
use App\Models\User;
use Livewire\Component;

class GettingStartedChecklist extends Component
{
    public bool $dismissed = false;

    public function mount(): void
    {
        $this->dismissed = (bool) settings('dashboard.checklist_dismissed');
    }

    public function dismiss(): void
    {
        settings()->set('dashboard.checklist_dismissed', true, 'boolean');
        $this->dismissed = true;
    }

    /**
     * @return array<int, array{label: string, completed: bool, description: string}>
     */
    public function items(): array
    {
        return [
            [
                'label' => 'Company configured',
                'completed' => ! empty(settings('company.name')),
                'description' => 'Set your company name, country, and tax details.',
            ],
            [
                'label' => 'Store created',
                'completed' => Store::query()->exists(),
                'description' => 'Add at least one physical location or warehouse.',
            ],
            [
                'label' => 'Admin account set up',
                'completed' => User::query()->where('is_owner', true)->exists(),
                'description' => 'Create an owner account for your organisation.',
            ],
            [
                'label' => 'Upload your logo',
                'completed' => ! empty(settings('branding.logo_path')),
                'description' => 'Add your company logo for documents and the navigation bar.',
            ],
            [
                'label' => 'Configure email settings',
                'completed' => ! empty(settings('email.smtp_host')),
                'description' => 'Set up SMTP so Signals can send invoices, notifications, and invitations.',
            ],
            [
                'label' => 'Invite a team member',
                'completed' => User::query()->count() > 1,
                'description' => 'Add colleagues so they can log in and start working.',
            ],
            [
                'label' => 'Create your first product',
                'completed' => false, // TODO: Enable when Product model exists
                'description' => 'Add equipment, services, or consumables to your catalogue.',
            ],
            [
                'label' => 'Create your first opportunity',
                'completed' => false, // TODO: Enable when Opportunity model exists
                'description' => 'Start a quote or order for a customer.',
            ],
        ];
    }

    public function progress(): int
    {
        $items = $this->items();
        $completed = collect($items)->filter(fn (array $item) => $item['completed'])->count();

        return $items ? (int) round(($completed / count($items)) * 100) : 0;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.dashboard.getting-started-checklist', [
            'items' => $this->items(),
            'progress' => $this->progress(),
        ]);
    }
}
