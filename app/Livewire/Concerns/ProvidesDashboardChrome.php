<?php

namespace App\Livewire\Concerns;

use App\Models\Language;

trait ProvidesDashboardChrome
{
    protected function getActiveLanguages(): array
    {
        return cache()->remember('dashboard_active_languages', 60, function () {
            return Language::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get(['name', 'native_name', 'code'])
                ->map(fn (Language $language) => [
                    'name'        => $language->name,
                    'native_name' => $language->native_name,
                    'code'        => $language->code,
                ])
                ->toArray();
        });
    }
}
