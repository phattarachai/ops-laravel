<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Http\Requests\RuleRequest;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\Reports\Incidents\RuleRows;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class RulesController
{
    public function index(Request $request): Response
    {
        return UiPage::render('rules', [
            'rules' => RuleRows::all(),
            'options' => RuleRows::options(),
            'focus_rule_id' => $request->integer('rule') ?: null,
        ]);
    }

    public function store(RuleRequest $request): RedirectResponse
    {
        $rule = Rule::query()->create([...$request->attributesForRule(), 'muted_until' => $request->mutedUntil(null)]);

        $this->toast('Rule ":name" created', ['name' => $rule->name]);

        return back();
    }

    public function update(RuleRequest $request, Rule $rule): RedirectResponse
    {
        $rule->update([...$request->attributesForRule(), 'muted_until' => $request->mutedUntil($rule->muted_until)]);

        match ($request->mute()) {
            null => $this->toast('Rule ":name" saved', ['name' => $rule->name]),
            'clear' => $this->toast('Rule ":name" unmuted', ['name' => $rule->name]),
            default => $this->toast('Rule ":name" muted until :time', ['name' => $rule->name, 'time' => (string) Format::date($rule->muted_until)]),
        };

        return back();
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        $rule->delete();

        $this->toast('Rule ":name" deleted', ['name' => $rule->name]);

        return back();
    }

    public function seedDefaults(): RedirectResponse
    {
        $created = DefaultRules::seed();

        $created > 0
            ? $this->toast(':count default rules added', ['count' => $created])
            : $this->toast('The default rules are already in place', tone: 'info');

        return back();
    }

    /** @param  array<string, string|int>  $vars */
    private function toast(string $text, array $vars = [], string $tone = 'success'): void
    {
        session()->flash('ops.flash', [
            'message' => strtr($text, collect($vars)->mapWithKeys(fn (string|int $value, string $name): array => [":{$name}" => (string) $value])->all()),
            'tone' => $tone,
            'text' => $text,
            'vars' => $vars,
        ]);
    }
}
