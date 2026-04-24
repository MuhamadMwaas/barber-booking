<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\Pages\ViewUser;

class ViewCustomer extends ViewUser
{
    protected static string $resource = CustomerResource::class;
}
