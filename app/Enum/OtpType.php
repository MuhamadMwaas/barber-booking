<?php

namespace App\Enum;

enum OtpType: int
{
    case EMAIL_OTP=1;
    case SMS_OTP= 2;


}