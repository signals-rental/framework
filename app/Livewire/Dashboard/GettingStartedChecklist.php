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
