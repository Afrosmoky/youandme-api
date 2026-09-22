<?php

test('the account deletion page explains the in-app path and the e-mail alternative', function (): void {
    $this->get('/delete-account')
        ->assertOk()
        ->assertSee('Profil → Usuń konto')
        ->assertSee('mailto:kontakt@jaity.app', false);
});

test('the privacy policy links to the account deletion page', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('href="/delete-account"', false);
});
