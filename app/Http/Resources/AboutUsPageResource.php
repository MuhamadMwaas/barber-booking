<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AboutUsPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'hero'       => $this->buildHero(),
            'contact'    => $this->buildContact(),
            'social'     => $this->buildSocial(),
            'legal'      => $this->buildLegal(),
            'features'   => $this->buildFeatures(),
            'newsletter' => $this->buildNewsletter(),
            'team'       => $this->buildTeam(),
            'meta'       => [
                'is_active'  => $this->is_active,
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
        ];
    }

    // ── Hero ──────────────────────────────────────────────────────────────────

    private function buildHero(): array
    {
        return [
            'title'       => $this->hero_title,
            'subtitle'    => $this->hero_subtitle,
            'description' => $this->hero_description,
            'images'      => $this->whenLoaded('heroImages', fn() =>
                $this->heroImages->map(fn($img) => [
                    'url'        => Storage::disk($img->disk ?? 'public')->url($img->path),
                    'sort_order' => $img->sort_order,
                ])->values()->all()
            , []),
        ];
    }

    // ── Contact ───────────────────────────────────────────────────────────────

    private function buildContact(): array
    {
        $phone   = $this->contact_phone   ?? [];
        $address = $this->contact_address ?? [];

        return [
            'phone' => [
                'value' => $phone['value'] ?? null,
                'label' => $phone['label'] ?? null,
                'icon'  => $phone['icon']  ?? null,
            ],
            'address' => [
                'value' => $address['value'] ?? null,
                'label' => $address['label'] ?? null,
                'icon'  => $address['icon']  ?? null,
            ],
            'email'         => $this->contact_email,
            'opening_hours' => $this->opening_hours,
        ];
    }

    // ── Social ────────────────────────────────────────────────────────────────

    private function buildSocial(): array
    {
        $links = collect($this->social_links ?? [])
            ->map(fn($link) => [
                'platform' => $link['platform'] ?? null,
                'url'      => $link['url']      ?? null,
                'icon'     => $link['icon']     ?? null,
            ])
            ->values()
            ->all();

        return [
            'title' => $this->social_title,
            'links' => $links,
        ];
    }

    // ── Legal ─────────────────────────────────────────────────────────────────

    private function buildLegal(): array
    {
        $links = collect($this->legal_links ?? [])
            ->map(fn($link) => [
                'key'   => $link['key']   ?? null,
                'label' => $link['label'] ?? null,
                'url'   => $link['url']   ?? null,
            ])
            ->values()
            ->all();

        return ['links' => $links];
    }

    // ── Features ──────────────────────────────────────────────────────────────

    private function buildFeatures(): array
    {
        return collect($this->features ?? [])
            ->map(fn($f) => [
                'icon'        => $f['icon']        ?? null,
                'title'       => $f['title']       ?? null,
                'description' => $f['description'] ?? null,
            ])
            ->values()
            ->all();
    }

    // ── Newsletter ────────────────────────────────────────────────────────────

    private function buildNewsletter(): array
    {
        return [
            'enabled'     => $this->newsletter_enabled,
            'title'       => $this->newsletter_title,
            'description' => $this->newsletter_description,
        ];
    }

    // ── Team ──────────────────────────────────────────────────────────────────

    private function buildTeam(): array
    {
        return $this->whenLoaded('activeTeamMembers', fn() =>
            $this->activeTeamMembers->map(fn($m) => [
                'name'        => $m->name,
                'position'    => $m->position,
                'description' => $m->description,
                'image_url'   => $m->image
                    ? Storage::disk('public')->url($m->image)
                    : null,
                'sort_order'  => $m->sort_order,
            ])->values()->all()
        , []);
    }
}
