<?php

namespace App\Enums;

enum PaidAdUsageKind: string
{
    case Voice = 'voice';
    case Video = 'video';
}
