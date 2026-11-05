<?php

namespace App\Enums;

enum MeetingTranscriptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
