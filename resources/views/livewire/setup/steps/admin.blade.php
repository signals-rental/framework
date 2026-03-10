<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="s-auth-heading">Admin Account</h1>
        <p class="s-auth-description">Create your owner account. This will be the primary administrator.</p>
    </div>

    <flux:input wire:model="adminName" label="Full Name" type="text" placeholder="Jane Smith" required autofocus />

    <flux:input wire:model="adminEmail" label="Email Address" type="email" placeholder="jane@example.com" required />

    <flux:input wire:model="adminPassword" label="Password" type="password" placeholder="Minimum 8 characters" required />

    <flux:input wire:model="adminPassword_confirmation" label="Confirm Password" type="password" placeholder="Repeat your password" required />
</div>
