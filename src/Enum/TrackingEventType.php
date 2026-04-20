<?php

namespace App\Enum;

enum TrackingEventType: string
{
    case Open = 'open';
    case Click = 'click';
    case Bounce = 'bounce';
    case Unsubscribe = 'unsubscribe';
}
