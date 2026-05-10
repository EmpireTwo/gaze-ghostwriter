<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Enums;

enum MailChunkRole: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
    case REFERENCE = 'reference';
}
