<?php

namespace App\Enums;

enum PlatformTypeWiseWeightage: string
{
    case Facebook = 'facebook';
    case FacebookMessenger = 'facebook_messenger';
    case WhatsApp = 'whatsapp';
    case Website = 'website';
    case CorporateApp = 'corporate_app';
    case Instagram = 'instagram';
    case Email = 'email';
    case UnknownSource = 'unknown_source';

    // Human-readable label
    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::FacebookMessenger => 'Facebook Messenger',
            self::WhatsApp => 'WhatsApp',
            self::Website => 'Website',
            self::CorporateApp => 'Corporate App',
            self::Instagram => 'Instagram',
            self::Email => 'Email',
            self::UnknownSource => 'Unknown Source',
        };
    }

    // Weight / capacity impact
    public function weight(): int
    {
        return match ($this) {
            self::Facebook => 1,
            self::FacebookMessenger => 2,
            self::WhatsApp => 2,
            self::Website => 1,
            self::CorporateApp => 1,
            self::Instagram => 2,
            self::Email => 1,
            self::UnknownSource => 1,
        };
    }
}
