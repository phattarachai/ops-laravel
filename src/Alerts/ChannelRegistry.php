<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Alerts;

use Closure;
use Illuminate\Contracts\Container\Container;
use Phattarachai\OpsCore\Alerts\Contracts\IncidentChannel;
use Phattarachai\OpsLaravel\Alerts\Channels\MailChannel;
use Phattarachai\OpsLaravel\Alerts\Channels\SlackChannel;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * Mail and Slack ship built in; `Ops::channel('telegram', fn () => new TelegramChannel)` adds
 * more. A channel only sends when its `channels.{name}.enabled` setting is on.
 */
final class ChannelRegistry
{
    /** @var array<string, Closure(): IncidentChannel> */
    private array $factories = [];

    public function __construct(private readonly Container $container, private readonly OpsSettings $settings)
    {
        $this->factories[MailChannel::NAME] = fn (): IncidentChannel => $this->container->make(MailChannel::class);
        $this->factories[SlackChannel::NAME] = fn (): IncidentChannel => $this->container->make(SlackChannel::class);
    }

    /** @param  Closure(): IncidentChannel  $factory */
    public function register(string $name, Closure $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->factories);
    }

    public function get(string $name): ?IncidentChannel
    {
        return isset($this->factories[$name]) ? ($this->factories[$name])() : null;
    }

    public function isEnabled(string $name): bool
    {
        return $this->settings->bool("channels.{$name}.enabled");
    }

    /**
     * @param  list<string>  $requested  empty = every enabled channel
     * @return list<string>
     */
    public function enabledFor(array $requested): array
    {
        $names = $requested === [] ? $this->names() : array_values(array_intersect($requested, $this->names()));

        return array_values(array_filter($names, $this->isEnabled(...)));
    }
}
