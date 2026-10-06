<?php

declare(strict_types=1);

namespace App\Enums;

enum DownloadLogStatus: string
{
    case Ok = 'ok';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case LimitReached = 'limit';
    case NotFound = 'not_found';
    case Throttled = 'throttled';
}
