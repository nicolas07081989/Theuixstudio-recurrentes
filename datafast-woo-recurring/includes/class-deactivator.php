<?php
namespace DFWR;

if (! defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(Cron::HOOK);
    }
}
