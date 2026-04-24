<?php

namespace DeskCaptcha\Captcha;

// Thin wrapper — exposes a single clean() entry point
class Cleaner
{
    public static function run(): void
    {
        $pool = new Pool();
        $pool->expireOld();
        $pool->enforce();
    }
}
