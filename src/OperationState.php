<?php
declare(strict_types=1);
namespace Silesco\AcmeSh;

/** Sanitized lifecycle. Issued is not TLS activation or PWA readiness. */
enum OperationState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case WaitingDns = 'waiting_dns';
    case Issued = 'issued';
    case Failed = 'failed';
    case Expired = 'expired';
}
