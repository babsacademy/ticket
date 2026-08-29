<?php

use App\Mail\TwoFactorCodeMail;

test('it has a clear subject', function () {
    $mail = new TwoFactorCodeMail('123456');

    $mail->assertHasSubject('Votre code de connexion');
});

test('it renders the code in large print with its validity window', function () {
    $mail = new TwoFactorCodeMail('123456');

    $mail->assertSeeInHtml('123456')
        ->assertSeeInHtml('valable 10 minutes');
});
