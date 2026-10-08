<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule as ValidationRule;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Alerts\ChannelRegistry;

/**
 * One alert rule from the Rules dialog. Which fields apply depends on the type: threshold
 * (signal, count, window, distinct field), rate (signal, denominator, %, min samples, window),
 * absence (signal, seconds silent) and state (signal, min level).
 */
final class RuleRequest extends FormRequest
{
    public const array MUTES = ['1h' => 60, '24h' => 1440];

    public const int MAX_WINDOW_SECONDS = 7 * 86400;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', ValidationRule::enum(RuleType::class)],
            'signal' => ['required', 'string', 'max:100'],
            'severity' => ['required', ValidationRule::enum(Severity::class)],
            'threshold' => [ValidationRule::requiredIf($this->typeIs(RuleType::Threshold, RuleType::Rate, RuleType::Absence)), 'nullable', 'numeric', ...$this->thresholdBounds()],
            'window_seconds' => [ValidationRule::requiredIf($this->typeIs(RuleType::Threshold, RuleType::Rate)), 'nullable', 'integer', 'min:60', 'max:'.self::MAX_WINDOW_SECONDS],
            'distinct_field' => ['nullable', 'string', 'max:64'],
            'denominator_signal' => [ValidationRule::requiredIf($this->typeIs(RuleType::Rate)), 'nullable', 'string', 'max:100'],
            'min_samples' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'min_level' => [ValidationRule::requiredIf($this->typeIs(RuleType::State)), 'nullable', ValidationRule::in([ProbeLevel::Warning->value, ProbeLevel::Critical->value])],
            'keys' => ['nullable', 'array', 'max:100'],
            'keys.*' => ['nullable', 'string', 'max:255'],
            'environments' => ['nullable', 'array', 'max:20'],
            'environments.*' => ['nullable', 'string', 'max:64'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', ValidationRule::in(app(ChannelRegistry::class)->names())],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:'.self::MAX_WINDOW_SECONDS],
            'resolve_after_seconds' => ['required', 'integer', 'min:0', 'max:'.self::MAX_WINDOW_SECONDS],
            'active' => ['required', 'boolean'],
            'notify_on_resolve' => ['required', 'boolean'],
            'mute' => ['nullable', ValidationRule::in([...array_keys(self::MUTES), 'clear'])],
        ];
    }

    /**
     * @return array{name: string, type: RuleType, signal: string, severity: Severity, threshold: float, window_seconds: int, distinct_field: ?string, denominator_signal: ?string, min_samples: int, min_level: ProbeLevel, keys: list<string>, environments: list<string>, channels: list<string>, cooldown_seconds: int, resolve_after_seconds: int, active: bool, notify_on_resolve: bool}
     */
    public function attributesForRule(): array
    {
        $type = RuleType::from((string) $this->validated('type'));

        return [
            'name' => (string) $this->validated('name'),
            'type' => $type,
            'signal' => (string) $this->validated('signal'),
            'severity' => Severity::from((string) $this->validated('severity')),
            'threshold' => $type === RuleType::State ? 0.0 : (float) $this->validated('threshold'),
            'window_seconds' => in_array($type, [RuleType::Threshold, RuleType::Rate], true) ? (int) $this->validated('window_seconds') : 300,
            'distinct_field' => $type === RuleType::Threshold ? $this->nullableString('distinct_field') : null,
            'denominator_signal' => $type === RuleType::Rate ? $this->nullableString('denominator_signal') : null,
            'min_samples' => $type === RuleType::Rate ? max(1, (int) ($this->validated('min_samples') ?? 1)) : 1,
            'min_level' => $type === RuleType::State ? ProbeLevel::from((string) $this->validated('min_level')) : ProbeLevel::Warning,
            'keys' => $this->strings('keys'),
            'environments' => $this->strings('environments'),
            'channels' => $this->strings('channels'),
            'cooldown_seconds' => (int) $this->validated('cooldown_seconds'),
            'resolve_after_seconds' => (int) $this->validated('resolve_after_seconds'),
            'active' => $this->boolean('active'),
            'notify_on_resolve' => $this->boolean('notify_on_resolve'),
        ];
    }

    public function mute(): ?string
    {
        $mute = $this->validated('mute');

        return is_string($mute) ? $mute : null;
    }

    public function mutedUntil(?CarbonImmutable $current): ?CarbonImmutable
    {
        $mute = $this->mute();

        return match (true) {
            $mute === null => $current,
            $mute === 'clear' => null,
            default => CarbonImmutable::now()->addMinutes(self::MUTES[$mute]),
        };
    }

    /** @return list<string> */
    private function thresholdBounds(): array
    {
        return match (true) {
            $this->typeIs(RuleType::Rate) => ['gt:0', 'max:100'],
            $this->typeIs(RuleType::Absence) => ['min:60', 'max:'.self::MAX_WINDOW_SECONDS],
            default => ['min:1', 'max:1000000000'],
        };
    }

    private function typeIs(RuleType ...$types): bool
    {
        return in_array(RuleType::tryFrom((string) $this->input('type')), $types, true);
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
}
