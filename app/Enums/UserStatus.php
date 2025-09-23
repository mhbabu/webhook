<?php

namespace App\Enums;

enum UserStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case OCCUPIED = 'OCCUPIED';
    case BREAK_REQUEST = 'BREAK_REQUEST';
    case BREAK = 'BREAK';
    case OFFLINE = 'OFFLINE';
}
