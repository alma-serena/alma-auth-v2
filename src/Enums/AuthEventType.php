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
    case StepUpSucceeded = 'step_up.succeeded';
    case StepUpFailed = 'step_up.failed';
    case EmailChangeRequested = 'email.change_requested';
    case EmailChanged = 'email.changed';
    case EmailChangeFailed = 'email.change_failed';
    case PasskeyRegistered = 'passkey.registered';
    case PasskeyAuthenticated = 'passkey.authenticated';
    case PasskeyAuthFailed = 'passkey.auth_failed';
    case PasskeyRevoked = 'passkey.revoked';
    case TrustedDeviceMarked = 'trusted_device.marked';
    case TrustedDeviceUsed = 'trusted_device.used';
    case TrustedDeviceRevoked = 'trusted_device.revoked';
    case LegalConsentRecorded = 'legal.consent_recorded';
}
