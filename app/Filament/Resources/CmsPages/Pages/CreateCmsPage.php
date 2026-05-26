<?php

namespace App\Filament\Resources\CmsPages\Pages;

use App\Filament\Resources\CmsPages\CmsPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;

    /**
     * Before saving a new page, the model's `saving` hook already runs
     * CmsBlockNormalizer which handles Filament Builder's { type, data:{…} }
     * format and stores a clean flat structure. No extra mutation needed here.
     */
}
