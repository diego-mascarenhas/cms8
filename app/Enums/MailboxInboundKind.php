<?php

namespace App\Enums;

enum MailboxInboundKind: string
{
    case HardBounce = 'hard_bounce';
    case SoftBounce = 'soft_bounce';
    case PolicyBounce = 'policy_bounce';
    case Reply = 'reply';
    case Ignore = 'ignore';
}
