<?php
namespace DFWR;

class Verifier
{
    public function verify_by_resource_path(string $resource_path): array
    {
        $cfg = Environment::checkout_config();
        $client = new Http_Client();
        $url = rtrim($cfg['base_url'], '/') . $resource_path . (str_contains($resource_path, '?') ? '&' : '?') . 'entityId=' . rawurlencode($cfg['entity_id']);
        return $client->get($url, $cfg['bearer'], $cfg['mode'] !== 'prod');
    }

    public function verify_by_merchant_transaction_id(string $merchant_tx): array
    {
        $cfg = Environment::checkout_config();
        $client = new Http_Client();
        $url = rtrim($cfg['base_url'], '/') . '/v1/query?entityId=' . rawurlencode($cfg['entity_id']) . '&merchantTransactionId=' . rawurlencode($merchant_tx);
        return $client->get($url, $cfg['bearer'], $cfg['mode'] !== 'prod');
    }

    public static function classify(array $body): string
    {
        $code = $body['result']['code'] ?? '';
        if (preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $code)) {
            return 'approved';
        }
        if ($code === '') {
            return 'error';
        }
        if (preg_match('/^(000\.200|800\.400\.5)/', $code)) {
            return 'pending';
        }
        return 'declined';
    }
}
