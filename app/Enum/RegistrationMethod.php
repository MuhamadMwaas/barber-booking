<?php

namespace App\Enum;

enum RegistrationMethod: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
}