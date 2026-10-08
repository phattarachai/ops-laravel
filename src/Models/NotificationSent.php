<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Override;

/**
 * @property int|null $incident_id
 * @property int|null $rule_id
 * @property string $channel
 * @property string $kind
 * @property bool $delivered
 * @property string|null $error
 * @property CarbonImmutable $sent_at
 */
final class NotificationSent extends OpsModel
{
    use MassPrunable;

    public $timestamps = false;

    protected $table = 'ops_notifications_sent';

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('sent_at', '<', now()->subDays(90));
    }

    #[Override]
    protected function casts(): array
    {
        return ['delivered' => 'boolean', 'sent_at' => 'immutable_datetime'];
    }
}
