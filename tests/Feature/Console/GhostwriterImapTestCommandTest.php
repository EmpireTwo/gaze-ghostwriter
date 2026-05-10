<?php

use Illuminate\Console\Command;

it('fails when imap host or username is missing', function () {
    config([
        'ghostwriter.imap.host' => '',
        'ghostwriter.imap.username' => '',
    ]);

    $this->artisan('ghostwriter:imap-test')->assertExitCode(Command::FAILURE);
});

it('fails when host is set but username missing', function () {
    config([
        'ghostwriter.imap.host' => 'imap.example.com',
        'ghostwriter.imap.username' => '',
    ]);

    $this->artisan('ghostwriter:imap-test')->assertExitCode(Command::FAILURE);
});
