<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Enums;

use Throwable;

enum OutgoingOutcome: string
{
    case Ok = 'ok';
    case Http4xx = 'http_4xx';
    case Http5xx = 'http_5xx';
    case Timeout = 'timeout';
    case ConnectError = 'connect_error';

    public static function forStatus(int $status): self
    {
        return match (true) {
            $status >= 500 => self::Http5xx,
            $status >= 400 => self::Http4xx,
            default => self::Ok,
        };
    }

    public static function forException(?Throwable $exception): self
    {
        $message = strtolower((string) $exception?->getMessage());

        return str_contains($message, 'curl error 28') || str_contains($message, 'timed out') ? self::Timeout : self::ConnectError;
    }

    public function isFailure(): bool
    {
        return $this !== self::Ok;
    }
}
