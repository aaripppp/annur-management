<?php

namespace App\Enums;

enum BillFrequency: string
{
    case Monthly = 'monthly';

    case Yearly = 'yearly';

    case OneTime = 'one_time';
}
