<?php

namespace Modules\Cms\app\Enums;

enum CmsContentEventType: string
{
    case View = 'view';
    case Download = 'download';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
