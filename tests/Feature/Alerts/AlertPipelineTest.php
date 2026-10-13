<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Phattarachai\OpsCore\Alerts\IncidentStatus;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsCore\Alerts\RuleType;
use Phattarachai\OpsCore\Alerts\Severity;
use Phattarachai\OpsLaravel\Alerts\Channels\IncidentMail;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Enums\ScheduleRunStatus;
use Phattarachai\OpsLaravel\Models\Incident;
use Phattarachai\OpsLaravel\Models\NotificationSent;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Models\ScheduleRun;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Tests\Fixtures\RecordedJob;
use Phattarachai\OpsLaravel\Ui\OpsCache;

beforeEach(function (): void {
    Mail::fake();
    Route::get('api/secret', fn () => abort(403));
    app(OpsSettings::class)->set('channels.mail.recipients', ['ops@example.test']);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:15:10'));
});

function burstRule(array $overrides = []): Rule
{
    return Rule::query()->create([
        'name' => '403 burst',
        'type' => RuleType::Threshold,
        'signal' => 'http.403',
        'threshold' => 3,
        'window_seconds' => 300,
        ...$overrides,
    ]);
}

it('turns a 403 burst into one incident and one mail', function (): void {
    burstRule();

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret', ['REMOTE_ADDR' => '203.0.113.9'])->assertForbidden();
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts')->assertSuccessful();
    $this->artisan('ops:alerts')->assertSuccessful();

    $incident = Incident::query()->sole();

    expect($incident)
        ->key->toBe('ip:203.0.113.9')
        ->status->toBe(IncidentStatus::Open)
        ->last_value->toBe(3.0)
        ->notifications->toBe(1);

    Mail::assertSent(IncidentMail::class, fn (IncidentMail $mail): bool => $mail->hasTo('ops@example.test') && str_contains($mail->envelope()->subject, 'ip:203.0.113.9'));
    Mail::assertSentCount(1);
    expect(NotificationSent::query()->sole())->channel->toBe('mail')->delivered->toBeTrue();
});

it('resolves the incident once the rule stays clear and says so', function (): void {
    burstRule(['resolve_after_seconds' => 300]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:30:02'));
    $this->artisan('ops:alerts');

    expect(Incident::query()->sole()->status)->toBe(IncidentStatus::Resolved);
    Mail::assertSentCount(2);
});

it('fires on built-in job failures and on host probes', function (): void {
    Rule::query()->create(['name' => 'jobs', 'type' => RuleType::Threshold, 'signal' => 'job.failed', 'threshold' => 1, 'window_seconds' => 900]);
    Rule::query()->create(['name' => 'vpn', 'type' => RuleType::State, 'signal' => 'network']);

    expect(fn () => RecordedJob::dispatchSync(fail: true))->toThrow(RuntimeException::class);
    Ops::probe('network', 'vpn', ProbeLevel::Critical, message: 'down');

    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:16:02'));
    $this->artisan('ops:alerts');

    expect(Incident::query()->orderBy('id')->pluck('key')->all())->toBe([RecordedJob::class, 'vpn']);
});

it('skips rules outside their environments and sends nothing when muted', function (): void {
    burstRule(['environments' => ['production']]);
    burstRule(['name' => 'muted', 'muted_until' => now()->addHour()]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    expect(Incident::query()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('posts to Slack when the channel is enabled and logs a failed delivery', function (): void {
    Http::fake(['hooks.slack.test/*' => Http::response('no_service', 404)]);
    app(OpsSettings::class)->set('channels.slack.enabled', true);
    app(OpsSettings::class)->set('channels.slack.webhook_url', 'https://hooks.slack.test/services/T/B/x');
    burstRule(['channels' => ['slack']]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://hooks.slack.test/') && str_contains((string) $request['text'], '403 burst'));
    Mail::assertNothingSent();
    expect(NotificationSent::query()->sole())->channel->toBe('slack')->delivered->toBeFalse();
});

it('seeds the default rules once without overwriting edits', function (): void {
    expect(DefaultRules::seed())->toBe(count(DefaultRules::definitions()));

    Rule::query()->where('slug', 'http-403-burst')->update(['threshold' => 50]);

    expect(DefaultRules::seed())->toBe(0)
        ->and(Rule::query()->where('slug', 'http-403-burst')->value('threshold'))->toBe(50.0);
});

it('never stores or reports the Slack webhook when a delivery fails', function (): void {
    Http::fake(['hooks.slack.test/*' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host for https://hooks.slack.test/services/T/B/secret-token')]);
    app(OpsSettings::class)->set('channels.slack.enabled', true);
    app(OpsSettings::class)->set('channels.slack.webhook_url', 'https://hooks.slack.test/services/T/B/secret-token');
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $exception) use (&$reported): bool {
        $reported[] = $exception->getMessage();

        return false;
    });
    burstRule(['channels' => ['slack']]);

    foreach (range(1, 3) as $ignored) {
        $this->getJson('api/secret');
    }

    rollupAt('2026-10-08 10:16:02');
    $this->artisan('ops:alerts');

    expect(NotificationSent::query()->sole()->error)->not->toContain('secret-token')->toContain('••••')
        ->and(implode(' ', $reported))->not->toContain('secret-token');
});

it('turns a failing scheduled task into a schedule.health incident', function (): void {
    app(Schedule::class)->command('inspire')->hourly();
    ScheduleRun::query()->create(['task' => 'inspire', 'expression' => '0 * * * *', 'status' => ScheduleRunStatus::Failed, 'started_at' => now()->subMinutes(10)]);
    Rule::query()->create(['name' => 'schedules', 'type' => RuleType::State, 'signal' => 'schedule.health']);

    $this->artisan('ops:alerts');

    expect(Incident::query()->sole())->key->toBe('inspire')->severity->toBe(Severity::Critical);
});

function scheduleHealthRule(): Rule
{
    DefaultRules::seed();
    Rule::query()->where('slug', '<>', 'schedule-health')->delete();
    Rule::query()->where('slug', 'schedule-health')->update(['environments' => null]);

    return Rule::query()->sole();
}

function alertsAt(string $at): void
{
    test()->travelTo(CarbonImmutable::parse($at));
    OpsCache::bust();
    test()->artisan('ops:alerts')->assertSuccessful();
}

it('mails a missed-run schedule incident on open and resolve only', function (): void {
    expect(scheduleHealthRule()->remind_warnings)->toBeFalse();
    app(Schedule::class)->command('inspire')->hourly();

    foreach (['08:00:02', '09:00:02'] as $at) {
        ScheduleRun::query()->create(['task' => 'inspire', 'expression' => '0 * * * *', 'status' => ScheduleRunStatus::Success, 'started_at' => "2026-10-08 {$at}"]);
    }

    alertsAt('2026-10-08 10:15:10');

    expect(Incident::query()->sole())->key->toBe('inspire')->severity->toBe(Severity::Warning);

    foreach (['10:31:10', '10:47:10', '11:20:10'] as $at) {
        alertsAt("2026-10-08 {$at}");
    }

    ScheduleRun::query()->create(['task' => 'inspire', 'expression' => '0 * * * *', 'status' => ScheduleRunStatus::Skipped, 'started_at' => '2026-10-08 10:00:30']);
    ScheduleRun::query()->create(['task' => 'inspire', 'expression' => '0 * * * *', 'status' => ScheduleRunStatus::Success, 'started_at' => '2026-10-08 11:00:02']);
    alertsAt('2026-10-08 11:21:10');
    alertsAt('2026-10-08 11:27:10');

    expect(Incident::query()->sole()->status)->toBe(IncidentStatus::Resolved)
        ->and(NotificationSent::query()->orderBy('id')->pluck('kind')->all())->toBe(['opened', 'resolved']);
});

it('keeps reminding about a failing scheduled task', function (): void {
    scheduleHealthRule();
    app(Schedule::class)->command('inspire')->hourly();
    ScheduleRun::query()->create(['task' => 'inspire', 'expression' => '0 * * * *', 'status' => ScheduleRunStatus::Failed, 'started_at' => '2026-10-08 10:00:02']);

    alertsAt('2026-10-08 10:15:10');
    alertsAt('2026-10-08 10:31:10');

    expect(Incident::query()->sole()->severity)->toBe(Severity::Critical)
        ->and(NotificationSent::query()->orderBy('id')->pluck('kind')->all())->toBe(['opened', 'reminder']);
});

it('turns warning reminders off for an existing schedule-health rule when migrating', function (): void {
    $schema = Schema::connection(config('ops.connection'));
    $schema->table('ops_rules', fn (Blueprint $table) => $table->dropColumn('remind_warnings'));
    Rule::query()->insert([
        ['slug' => 'schedule-health', 'name' => 'schedules', 'type' => 'state', 'signal' => 'schedule.health'],
        ['slug' => 'job-failures', 'name' => 'jobs', 'type' => 'threshold', 'signal' => 'job.failed'],
    ]);

    (require __DIR__.'/../../../database/migrations/2026_10_13_000001_add_remind_warnings_to_ops_rules.php')->up();

    expect(Rule::query()->orderBy('id')->pluck('remind_warnings', 'slug')->all())->toBe(['schedule-health' => false, 'job-failures' => true]);
});
