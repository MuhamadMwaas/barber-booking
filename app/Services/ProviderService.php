<?php

namespace App\Services;

use App\Models\User;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProviderService
{
    /**

     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getProvidersWithServices(array $filters = [], int $perPage = 15)
    {
        // Bookable links only, same rule as Service::bookableProviders(): a
        // provider whose every link is switched off is not listed at all, and one
        // switched off for `service_id` is not listed for that service.
        $query = User::with(['bookableServices.translations', 'branch'])
            ->whereHas('bookableServices')
            ->whereHas('roles', fn ($q) => $q->where('roles.name', 'provider'))
            ->where('is_active', true);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['service_id'])) {
            $query->whereHas('bookableServices', function ($q) use ($filters) {
                $q->where('services.id', $filters['service_id']);
            });
        }

        $sortBy = $filters['sort_by'] ?? 'first_name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * @param int $providerId
     * @param string|null $locale
     * @return array
     */
    public function getProviderDetailsWithServices(int $providerId, ?string $locale = null): array
    {
        $language = $locale ?
            Language::where('code', $locale)->first() :
            Language::where('is_default', true)->first();

        if (!$language) {
            $language = Language::first();
        }

        $provider = User::with([
                'bookableServices.translations',
                'bookableServices.category.translations',
                'bookableServices.reviews',
                'branch'
            ])
            ->where('is_active', true)
            ->findOrFail($providerId);

        $enhancedServices = $provider->bookableServices->map(function ($service) use ($providerId, $language) {
            $bookingCount = Appointment::where('provider_id', $providerId)
                ->whereHas('services', function ($query) use ($service) {
                    $query->where('services.id', $service->id);
                })
                ->count();

            $averageRating = $service->reviews->avg('rating') ?? 0;

            $service->booking_count = $bookingCount;
            $service->average_rating = round($averageRating, 1);
            $service->review_count = $service->reviews->count();

            $service->translated_name = $this->getTranslatedValue(
                $service->translations,
                $language->id,
                'name',
                $service->name
            );

            $service->translated_description = $this->getTranslatedValue(
                $service->translations,
                $language->id,
                'description',
                $service->description
            );

            // Handle category translations
            if ($service->category) {
                $service->category->translated_name = $this->getTranslatedValue(
                    $service->category->translations,
                    $language->id,
                    'name',
                    $service->category->name
                );

                $service->category->translated_description = $this->getTranslatedValue(
                    $service->category->translations,
                    $language->id,
                    'description',
                    $service->category->description
                );
            }

            return $service;
        });

        return [
            'provider' => $provider,
            'services' => $enhancedServices,
            'total_booking_count' => $this->getProviderTotalBookings($providerId),
        ];
    }

    /**
     * @param mixed $translations
     * @param int $languageId
     * @param string $field
     * @param string|null $defaultValue  nullable: a category's description is optional
     * @return string|null
     */
    private function getTranslatedValue($translations, int $languageId, string $field, ?string $defaultValue): ?string
    {
        if (!$translations) {
            return $defaultValue;
        }

        $translation = $translations->firstWhere('language_id', $languageId);
        return $translation ? $translation->$field : $defaultValue;
    }

    /**
     * @param int $providerId
     * @return int
     */
    private function getProviderTotalBookings(int $providerId): int
    {
        return Appointment::where('provider_id', $providerId)->count();
    }
}
