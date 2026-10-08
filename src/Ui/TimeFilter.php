<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Throwable;

final readonly class TimeFilter
{
    public function __construct(
        public string $window,
        public ?string $from,
        public ?string $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $from = $request->query('from');
        $to = $request->query('to');

        return new self((string) $request->query('window', '24h'), is_string($from) ? $from : null, is_string($to) ? $to : null);
    }

    public function range(): TimeRange
    {
        if ($this->window !== 'custom' || $this->from === null || $this->to === null) {
            return TimeRange::fromPreset($this->window);
        }

        try {
            return TimeRange::custom(CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to));
        } catch (Throwable) {
            return TimeRange::fromPreset('24h');
        }
    }

    /** @return array<string, string> */
    public function params(): array
    {
        return array_filter([
            'window' => $this->window === '24h' ? null : $this->window,
            'from' => $this->window === 'custom' ? $this->from : null,
            'to' => $this->window === 'custom' ? $this->to : null,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array{window: string, from: ?string, to: ?string, label: string, presets: list<array{value: string, label: string}>, as_of: ?string, as_of_human: ?string, refresh_url: string}
     */
    public function toProps(?CarbonInterface $asOf): array
    {
        $presets = array_map(fn (string $preset): array => ['value' => $preset, 'label' => $preset], array_keys(TimeRange::PRESET_MINUTES));

        return [
            'window' => $this->window,
            'from' => $this->from,
            'to' => $this->to,
            'label' => $this->range()->label,
            'presets' => [...$presets, ['value' => 'custom', 'label' => 'Custom range']],
            'as_of' => Format::date($asOf, 'j M H:i:s'),
            'as_of_human' => $asOf?->copy()->locale(Format::locale())->diffForHumans(),
            'refresh_url' => route('ops.refresh'),
        ];
    }
}
