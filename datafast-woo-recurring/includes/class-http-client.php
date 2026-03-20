<?php
namespace DFWR;

class Http_Client
{
    public function post_form(string $url, array $payload, string $bearer, bool $sslverify = true): array
    {
        $response = wp_remote_post($url, [
            'timeout' => 45,
            'sslverify' => $sslverify,
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($payload),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return [
            'ok' => wp_remote_retrieve_response_code($response) < 400,
            'status' => wp_remote_retrieve_response_code($response),
            'body' => $decoded ?: ['raw' => $body],
        ];
    }

    public function get(string $url, string $bearer, bool $sslverify = true): array
    {
        $response = wp_remote_get($url, [
            'timeout' => 45,
            'sslverify' => $sslverify,
            'headers' => ['Authorization' => 'Bearer ' . $bearer],
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        return [
            'ok' => wp_remote_retrieve_response_code($response) < 400,
            'status' => wp_remote_retrieve_response_code($response),
            'body' => $decoded ?: ['raw' => $body],
        ];
    }
}
