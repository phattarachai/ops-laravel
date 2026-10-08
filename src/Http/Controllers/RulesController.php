<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Models\Rule;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class RulesController
{
    public function index(): Response
    {
        return UiPage::render('rules');
    }

    public function store(): RedirectResponse
    {
        return back();
    }

    public function update(Rule $rule): RedirectResponse
    {
        return back();
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        $rule->delete();

        return back();
    }

    public function seedDefaults(): RedirectResponse
    {
        DefaultRules::seed();

        return back();
    }
}
