<?php

namespace App\Enums;

enum EventReportType: string
{
    case PreEvent = 'PRE_EVENT';
    case PostEvent = 'POST_EVENT';
}
