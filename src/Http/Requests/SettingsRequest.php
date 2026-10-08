<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * The settings page form. Field names are the OpsSettings keys; the Slack webhook travels
 * separately and an empty value keeps the stored one.
 */
final class SettingsRequest extends FormRequest
{
    /** @var array<string, 'bool'|'int'|'string'|'list'|'emails'|'statuses'> */
    public const array FIELDS = [
        'channels.mail.enabled' => 'bool',
        'channels.mail.recipients' => 'emails',
        'channels.mail.mailer' => 'string',
        'channels.mail.from_address' => 'string',
        'channels.mail.from_name' => 'string',
        'channels.slack.enabled' => 'bool',
        'recorders.requests.enabled' => 'bool',
        'recorders.requests.paths' => 'list',
        'recorders.requests.ignore' => 'list',
        'recorders.outgoing.enabled' => 'bool',
        'recorders.outgoing.ignore_hosts' => 'list',
        'recorders.jobs.enabled' => 'bool',
        'recorders.jobs.ignore' => 'list',
        'recorders.schedule.enabled' => 'bool',
        'recorders.security.enabled' => 'bool',
        'recorders.security.statuses' => 'statuses',
        'signals.user_route_floor' => 'int',
        'retention.minute_days' => 'int',
        'retention.hour_days' => 'int',
        'retention.user_days' => 'int',
        'retention.runs_days' => 'int',
        'retention.signal_days' => 'int',
    ];

    public const string WEBHOOK = 'channels.slack.webhook_url';

    public const string CLEAR_WEBHOOK = 'channels.slack.clear_webhook';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [];

        foreach (self::FIELDS as $key => $type) {
            $rules = [...$rules, ...$this->rulesFor($key, $type)];
        }

        return [
            ...$rules,
            'channels.mail.recipients' => ['required_if_accepted:channels.mail.enabled', 'present', 'array', 'max:50'],
            'channels.mail.mailer' => ['nullable', 'string', Rule::in(array_keys((array) config('mail.mailers', [])))],
            'channels.mail.from_address' => ['nullable', 'email', 'max:255'],
            'recorders.requests.paths' => ['present', 'array', 'min:1', 'max:100'],
            'signals.user_route_floor' => ['required', 'integer', 'min:1', 'max:100000'],
            self::WEBHOOK => ['nullable', 'string', 'url', 'starts_with:https://', 'max:1000'],
            self::CLEAR_WEBHOOK => ['nullable', 'boolean'],
        ];
    }

    /** @return array<int, Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('channels.slack.enabled') && ! $this->hasWebhookAfterSave()) {
                $validator->errors()->add(self::WEBHOOK, 'Slack needs a webhook URL before it can be enabled.');
            }
        }];
    }

    /** @return array<string, mixed> setting key => value */
    public function values(): array
    {
        $values = [];

        foreach (self::FIELDS as $key => $type) {
            $values[$key] = match ($type) {
                'bool' => $this->boolean($key),
                'int' => (int) $this->validated($key),
                'string' => $this->nullableString($key),
                'list', 'emails' => $this->strings($key),
                'statuses' => $this->statuses(),
            };
        }

        return $values;
    }

    public function webhook(): ?string
    {
        return $this->nullableString(self::WEBHOOK);
    }

    public function clearsWebhook(): bool
    {
        return $this->boolean(self::CLEAR_WEBHOOK) && $this->webhook() === null;
    }

    /** @return array<string, list<mixed>> */
    private function rulesFor(string $key, string $type): array
    {
        return match ($type) {
            'bool' => [$key => ['required', 'boolean']],
            'int' => [$key => ['required', 'integer', 'min:1', 'max:3650']],
            'string' => [$key => ['nullable', 'string', 'max:255']],
            'list' => [$key => ['present', 'array', 'max:100'], "{$key}.*" => ['nullable', 'string', 'max:255']],
            'emails' => [$key => ['present', 'array', 'max:50'], "{$key}.*" => ['nullable', 'email', 'max:255']],
            'statuses' => [$key => ['present', 'array', 'max:50'], "{$key}.*" => ['integer', 'between:100,599']],
            default => [],
        };
    }

    private function hasWebhookAfterSave(): bool
    {
        $stored = app(OpsSettings::class)->get(self::WEBHOOK);

        return trim((string) $this->input(self::WEBHOOK)) !== '' || (! $this->boolean(self::CLEAR_WEBHOOK) && is_string($stored) && $stored !== '');
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->validated($key));

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private function strings(string $key): array
    {
        $values = $this->validated($key);

        return array_values(array_unique(array_filter(array_map(fn (mixed $value): string => trim((string) $value), is_array($values) ? $values : []), fn (string $value): bool => $value !== '')));
    }

    /** @return list<int> */
    private function statuses(): array
    {
        $values = $this->validated('recorders.security.statuses');
        $statuses = array_values(array_unique(array_map(intval(...), is_array($values) ? $values : [])));
        sort($statuses);

        return $statuses;
    }
}
