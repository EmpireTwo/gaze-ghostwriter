<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Support\SupportMailBareGreetingDetector;

dataset('bare_greetings', [
    ['Hi'],
    ['Hi Krishan'],
    ['Hallo!'],
    ['Hey du'],
    ['Moin'],
    ['Guten Tag'],
    ['Danke dir'],
    ['Thanks'],
    ['LG'],
]);

dataset('not_bare', [
    ['Kann ich mein Release noch verschieben?'],
    ['Ich brauche Hilfe beim Login'],
    ['Hallo, ich habe eine Frage zu meiner Rechnung'],
    ['Problem mit dem Account seit gestern'],
    ['Nur ein kurzer Test ob der Versand klappt und ob die Mail ankommt'],
    ['Hallo zusammen wie geht es mit dem Release'],
]);

test('detects bare greeting or ping', function (string $body) {
    expect(SupportMailBareGreetingDetector::isBareGreetingOrPing($body))->toBeTrue();
})->with('bare_greetings');

test('does not flag substantive support text', function (string $body) {
    expect(SupportMailBareGreetingDetector::isBareGreetingOrPing($body))->toBeFalse();
})->with('not_bare');

test('empty body is not bare greeting', function () {
    expect(SupportMailBareGreetingDetector::isBareGreetingOrPing(''))->toBeFalse()
        ->and(SupportMailBareGreetingDetector::isBareGreetingOrPing('   '))->toBeFalse();
});
