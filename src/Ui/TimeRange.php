<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui;

use Carbon\CarbonImmutable;
use Phattarachai\OpsCore\Rollup\Grain;
use Phattarachai\OpsCore\Time\WindowPlan;

/**
 * A resolved reporting window: presets and custom ranges share one span → grain/step/bars
 * derivation (ops-core WindowPlan); `key` is a stable cache-key fragment.
 */
final readonly class TimeRange
{
    /** @var array<string, int> */
    public const array PRESET_MINUTES = ['1h' => 60, '6h' => 360, '24h' => 1440, '7d' => 10080, '30d' => 43200];

    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $grain,
        public int $step,
        public int $bars,
        public string $label,
        public string $key,
    ) {}

    public static function fromPreset(string $preset): self
    {
        $preset = array_key_exists($preset, self::PRESET_MINUTES) ? $preset : '24h';
        $end = CarbonImmutable::now();

        return self::build($end->subMinutes(self::PRESET_MINUTES[$preset]), $end, $preset, "p:{$preset}");
    }

    public static function custom(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $start = $from->startOfDay();
        $end = $to->endOfDay()->greaterThan($start) ? $to->endOfDay() : $start->endOfDay();

        return self::build($start, $end, Format::date($start, 'j M').' – '.Format::date($end, 'j M'), "c:{$start->format('Ymd')}:{$end->format('Ymd')}");
    }

    public function firstBarStart(): CarbonImmutable
    {
        $minutesOfDay = $this->end->hour * 60 + $this->end->minute;

        return $this->end->startOfDay()
            ->addMinutes(intdiv($minutesOfDay, $this->step) * $this->step)
            ->subMinutes(($this->bars - 1) * $this->step);
    }

    public function barStart(int $index): CarbonImmutable
    {
        return $this->firstBarStart()->addMinutes($index * $this->step);
    }

    public function barLabel(int $index): string
    {
        return $this->usesDateLabels() ? (string) Format::date($this->barStart($index), 'j M') : $this->barStart($index)->format('H:i');
    }

    public function usesDateLabels(): bool
    {
        return $this->step >= 360;
    }

    public function isMinuteGrain(): bool
    {
        return $this->grain === Grain::Minute->value;
    }

    private static function build(CarbonImmutable $start, CarbonImmutable $end, string $label, string $key): self
    {
        $plan = WindowPlan::forSpan(max(1, (int) round(abs($start->diffInMinutes($end)))));

        return new self($start, $end, $plan->grain->value, $plan->stepMinutes, $plan->bars, $label, $key);
    }
}
