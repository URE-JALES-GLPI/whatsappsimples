<?php

namespace GlpiPlugin\Whatsappsimples;

use CommonGLPI;
use Session;

class Menu extends CommonGLPI
{
    public static $rightname = 'tools';

    public static function getMenuName(int $nb = 0): string
    {
        return 'WhatsApp';
    }

    public static function getIcon(): string
    {
        return 'fab fa-whatsapp';
    }

    public static function canView(): bool
    {
        return Session::getLoginUserID() > 0;
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) return [];
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/whatsappsimples/Chat',
            'icon'  => self::getIcon(),
        ];
    }
}
