<?php

namespace DeskCaptcha;

class Config
{
    private static array $cache = [];

    public static function api(): array
    {
        return self::load('api');
    }

    public static function cors(): array
    {
        return self::load('cors');
    }

    public static function limits(): array
    {
        return self::load('limits');
    }

    private static function load(string $name): array
    {
        if (!isset(self::$cache[$name])) {
            self::$cache[$name] = require __DIR__ . '/../config/' . $name . '.php';
        }
        return self::$cache[$name];
    }
}
