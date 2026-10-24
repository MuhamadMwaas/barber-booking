<?php

/**
 * The provider lists the app picks a barber from must hold the same providers
 * the booking call accepts. A provider_service link switched off in the
 * dashboard used to leave the provider selectable in the app — with no slots
 * behind it, because availability already skipped the link.
 */

use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture();
});

function listedIds(array $rows, string $key = 'id'): array
{
    return collect($rows)->pluck($key)->all();
}

it('lists only bookable providers on the single service endpoint', function () {
    $response = $this->getJson("/api/services/{$this->salon->service->id}")->assertOk();

    $ids = listedIds($response->json('data.providers'));

    expect($ids)->toContain($this->salon->available->id)
        ->and($ids)->not->toContain($this->salon->inactivePivot->id)
        ->and($ids)->not->toContain($this->salon->inactiveUser->id)
        ->and($ids)->not->toContain($this->salon->doesNotOffer->id)
        ->and($response->json('data.statistics.provider_count'))->toBe(count($ids));
});

it('lists only bookable providers on the service catalogue', function () {
    $response = $this->getJson('/api/services?per_page=50')->assertOk();

    $service = collect($response->json('data'))->firstWhere('id', $this->salon->service->id);
    $ids = listedIds($service['providers']);

    expect($ids)->toContain($this->salon->available->id)
        ->and($ids)->not->toContain($this->salon->inactivePivot->id)
        ->and($ids)->not->toContain($this->salon->inactiveUser->id);
});

it('does not list a provider for a service whose link is switched off', function () {
    $response = $this->getJson("/api/providers?service_id={$this->salon->service->id}&per_page=50")->assertOk();

    $ids = listedIds($response->json('data'));

    expect($ids)->toContain($this->salon->available->id)
        ->and($ids)->not->toContain($this->salon->inactivePivot->id)
        ->and($ids)->not->toContain($this->salon->doesNotOffer->id);
});

it('drops a provider from the list when every link is switched off', function () {
    $response = $this->getJson('/api/providers?per_page=50')->assertOk();

    // inactivePivot's only link is the switched-off one.
    expect(listedIds($response->json('data')))->not->toContain($this->salon->inactivePivot->id);
});

it('shows a provider only the services they can be booked for', function () {
    $this->salon->offerService($this->salon->doesNotOffer, $this->salon->service, isActive: false);

    $response = $this->getJson("/api/providers/{$this->salon->doesNotOffer->id}")->assertOk();

    expect(listedIds($response->json('data.services')))
        ->toBe([$this->salon->secondService->id]);
});

it('hides an inactive service from the provider page', function () {
    $response = $this->getJson("/api/providers/{$this->salon->available->id}")->assertOk();

    expect(listedIds($response->json('data.services')))
        ->not->toContain($this->salon->inactiveService->id)
        ->toContain($this->salon->service->id);
});
