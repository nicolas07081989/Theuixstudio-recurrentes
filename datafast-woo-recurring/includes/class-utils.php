<?php
namespace DFWR;

class Utils
{
    public static function detect_client_ip(): string
    {
        $candidates = [];
        if (! empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($parts as $part) {
                $candidates[] = trim($part);
            }
        }
        if (! empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function normalize_identification(string $id): string
    {
        $digits = preg_replace('/\D+/', '', $id);
        $digits = (string) $digits;
        if (strlen($digits) > 10) {
            $digits = substr($digits, 0, 10);
        }
        return str_pad($digits, 10, '0', STR_PAD_LEFT);
    }

    public static function merchant_transaction_id(int $order_id, string $prefix = 'DFWC'): string
    {
        $id = $prefix . '-' . $order_id . '-' . gmdate('YmdHis') . '-' . wp_generate_password(12, false, false);
        if (strlen($id) < 8) {
            $id .= '-TRX';
        }
        return substr($id, 0, 255);
    }

    public static function merchant_customer_id(int $user_id): string
    {
        $meta = get_user_meta($user_id, '_dfwr_merchant_customer_id', true);
        if ($meta) {
            return substr((string) $meta, 0, 16);
        }
        $new = substr('DFCUST' . $user_id, 0, 16);
        update_user_meta($user_id, '_dfwr_merchant_customer_id', $new);
        return $new;
    }
}
