<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Incidents;

use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * A delivery error as the dashboard shows it. Transport errors can quote the URL they called
 * (a Slack webhook is a secret), so every URL path and every stored secret is masked.
 */
final class DeliveryError
{
    private const string MASK = '••••';

    public static function redact(?string $error): ?string
    {
        if ($error === null || $error === '') {
            return $error;
        }

        $settings = app(OpsSettings::class);

        foreach ((array) config('ops.secret_settings', []) as $key) {
            $secret = $settings->get((string) $key);

            if (is_string($secret) && $secret !== '') {
                $error = str_replace($secret, self::MASK, $error);
            }
        }

        return (string) preg_replace('~(https?://[^/\s]+)/\S*~i', '$1/'.self::MASK, $error);
    }
}
