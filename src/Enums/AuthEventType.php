<?php

declare(strict_types=1);

namespace Alma\Auth\Enums;

enum AuthEventType: string
{
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case TwoFactorVerified = 'two_factor.verified';
    case TwoFactorFailed = 'two_factor.failed';
    case TwoFactorEnabled = 'two_factor.enabled';
    case RefreshTokenIssued = 'refresh.issued';
    case RefreshTokenRotated = 'refresh.rotated';
    case RefreshReuseDetected = 'refresh.reuse_detected';
}
