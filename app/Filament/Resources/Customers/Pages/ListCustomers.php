<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\Pages\ListUsers;

class ListCustomers extends ListUsers
{
    protected static string $resource = CustomerResource::class;
}
