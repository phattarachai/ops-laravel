<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Buffer;

final readonly class MinuteData
{
    public const string SEPARATOR = '|';

    public const string METRIC = '#';

    /**
     * @param  array<string, int>  $fields
     * @param  array<string, float>  $maxima
     * @param  list<string>  $members
     */
    public function __construct(
        public array $fields = [],
        public array $maxima = [],
        public array $members = [],
    ) {}

    /** @param  list<string|int|bool|null>  $values */
    public static function dims(array $values): string
    {
        return implode(self::SEPARATOR, array_map(
            static fn (string|int|bool|null $value): string => str_replace([self::SEPARATOR, self::METRIC], '/', (string) (is_bool($value) ? (int) $value : $value)),
            $values,
        ));
    }

    public static function field(string $dims, string $metric): string
    {
        return $dims.self::METRIC.$metric;
    }

    /** @return list<string> */
    public static function split(string $dims): array
    {
        return explode(self::SEPARATOR, $dims);
    }

    public function isEmpty(): bool
    {
        return $this->fields === [] && $this->members === [];
    }

    /** @return array<string, array<string, int>> dims => metric => value */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->fields as $field => $value) {
            [$dims, $metric] = array_pad(explode(self::METRIC, $field, 2), 2, '');
            $groups[$dims][$metric] = $value;
        }

        return $groups;
    }

    public function max(string $dims): float
    {
        return $this->maxima[$dims] ?? 0.0;
    }
}
