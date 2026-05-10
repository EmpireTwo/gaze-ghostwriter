<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class GhostwriterSmtpTransportFactory
{
    public static function make(): TransportInterface
    {
        $driver = strtolower(trim((string) config('gaze-ghostwriter.smtp.driver', 'smtp')));

        if ($driver === 'null' || $driver === '') {
            return Transport::fromDsn('null://null');
        }

        $host = (string) config('gaze-ghostwriter.smtp.host');
        $port = (int) config('gaze-ghostwriter.smtp.port', 587);
        $encryption = strtolower((string) config('gaze-ghostwriter.smtp.encryption', 'tls'));
        $username = (string) config('gaze-ghostwriter.smtp.username', '');
        $password = (string) config('gaze-ghostwriter.smtp.password', '');

        $useTls = match ($encryption) {
            'none', 'false', 'off' => false,
            default => true,
        };

        $transport = new EsmtpTransport($host, $port, $useTls);

        if ($username !== '') {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        $timeout = (float) config('gaze-ghostwriter.smtp.timeout', 30);
        $stream = $transport->getStream();
        if ($timeout > 0 && $stream instanceof SocketStream) {
            $stream->setTimeout($timeout);
        }

        return $transport;
    }
}
