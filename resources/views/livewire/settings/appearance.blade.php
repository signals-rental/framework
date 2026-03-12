<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Appearance')] class extends Component {
    //
}; ?>

<section class="w-full">
    <x-settings.layout heading="Appearance" subheading="Update your account's appearance settings">
        <x-signals.form-section title="Appearance">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">Light</flux:radio>
                <flux:radio value="dark" icon="moon">Dark</flux:radio>
                <flux:radio value="system" icon="computer-desktop">System</flux:radio>
            </flux:radio.group>
        </x-signals.form-section>
    </x-settings.layout>
</section>
