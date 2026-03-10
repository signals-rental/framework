<?php

use App\Actions\Admin\SendTestEmail;
use App\Mail\TestEmail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('sends a test email to the given address', function () {
    Mail::fake();

    (new SendTestEmail)('recipient@example.com');

    Mail::assertSent(TestEmail::class, function ($mail) {
        return $mail->hasTo('recipient@example.com');
    });
});

it('throws an exception if mail delivery fails', function () {
    Mail::shouldReceive('to->send')
        ->andThrow(new \Exception('Connection refused'));

    (new SendTestEmail)('test@example.com');
})->throws(\Exception::class, 'Connection refused');

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    Mail::fake();

    (new SendTestEmail)('test@example.com');
})->throws(AuthorizationException::class);
