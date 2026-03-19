<?php
namespace DFWR;

class Cron
{
    public const HOOK = 'dfwr_recurring_run';

    public static function init(): void
    {
        add_action(self::HOOK, [__CLASS__, 'run']);
        add_action('init', [__CLASS__, 'register_cli']);
    }

    public static function register_cli(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('datafast recurring run', [__CLASS__, 'run']);
        }
    }

    public static function run(): void
    {
        $lock = get_transient('dfwr_recurring_lock');
        if ($lock) {
            return;
        }
        set_transient('dfwr_recurring_lock', '1', MINUTE_IN_SECONDS * 10);

        $repo = new Subscription_Repository();
        $due = $repo->due_subscriptions();
        $gateway = new \DFWR\Gateway_Datafast();
        foreach ($due as $sub) {
            $gateway->charge_subscription($sub);
        }

        delete_transient('dfwr_recurring_lock');
    }
}
