<?php

namespace Modules\Cms\app\Enums;

enum CmsMediaType: string
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Video = 'video';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
