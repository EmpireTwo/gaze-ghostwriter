<?php

declare(strict_types=1);

use Illuminate\Console\Command;

it('fails when imap host or username is missing', function () {
    config([
        'gaze-ghostwriter.imap.host' => '',
        'gaze-ghostwriter.imap.username' => '',
    ]);

    $this->artisan('ghostwriter:imap-test')->assertExitCode(Command::FAILURE);
});

it('fails when host is set but username missing', function () {
    config([
        'gaze-ghostwriter.imap.host' => 'imap.example.com',
        'gaze-ghostwriter.imap.username' => '',
    ]);

    $this->artisan('ghostwriter:imap-test')->assertExitCode(Command::FAILURE);
});
