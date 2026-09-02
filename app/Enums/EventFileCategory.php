<?php

namespace App\Enums;

enum EventFileCategory: string
{
    case Photos = 'photos';
    case Reports = 'reports';
    case Communications = 'communications';
    case Designs = 'designs';
    case Other = 'other';
}
