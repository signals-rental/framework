<?php

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('redirects the well-known change-password URL to the settings password page', function () {
    $this->get('/.well-known/change-password')
        ->assertRedirect(route('settings.password'));
});
