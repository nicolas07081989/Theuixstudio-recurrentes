<?php
namespace DFWR;

class Logger
{
    public static function log(string $message, array $context = []): void
    {
        if (Settings::get('debug', 'no') !== 'yes') {
            return;
        }
        $logger = wc_get_logger();
        $logger->info($message . ' ' . wp_json_encode($context), ['source' => 'datafast-woo-recurring']);
    }
}
