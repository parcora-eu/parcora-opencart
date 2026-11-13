<?php

namespace Parcora\OpenCart;

/**
 * A minimal cURL client for the Parcora API.
 *
 * The official parcora/parcora-php SDK needs PHP 8.2 and a PSR-18 client via
 * Composer, neither of which an OpenCart extension can count on, so the few
 * calls the module makes are done here directly.
 */
class Client
{
    const DEFAULT_BASE_URL = 'https://api.parcora.eu';

    const VERSION = '0.1.0';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeout;

    public function __construct(string $apiKey, string $baseUrl = '', int $timeout = 10)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');
        $this->timeout = $timeout;
    }

    public function carriers(): array
    {
        return $this->request('GET', '/v1/carriers')['data'];
    }

    public function rates(array $payload): array
    {
        return $this->request('POST', '/v1/rates', $payload)['data'];
    }

    public function pickupPoints(array $query): array
    {
        return $this->request('GET', '/v1/pickup_points?' . http_build_query($query))['data'];
    }

    public function createShipment(array $payload, string $idempotencyKey): array
    {
        return $this->request('POST', '/v1/shipments', $payload, $idempotencyKey);
    }

    public function shipment(string $id): array
    {
        return $this->request('GET', '/v1/shipments/' . rawurlencode($id));
    }

    /** Raw label bytes plus their content type. */
    public function label(string $id, string $format = ''): array
    {
        $query = $format !== '' ? '?' . http_build_query(['format' => $format]) : '';

        return $this->send('GET', '/v1/shipments/' . rawurlencode($id) . '/label' . $query);
    }

    public function createWebhookEndpoint(string $url, array $events): array
    {
        return $this->request('POST', '/v1/webhook_endpoints', ['url' => $url, 'events' => $events]);
    }

    public function deleteWebhookEndpoint(string $id): void
    {
        $this->request('DELETE', '/v1/webhook_endpoints/' . rawurlencode($id));
    }

    public function ping(): array
    {
        return $this->request('GET', '/v1/ping');
    }

    private function request(string $method, string $path, ?array $body = null, string $idempotencyKey = ''): array
    {
        $response = $this->send($method, $path, $body, $idempotencyKey);
        $decoded = json_decode($response['body'], true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{body: string, content_type: string} */
    private function send(string $method, string $path, ?array $body = null, string $idempotencyKey = ''): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'User-Agent: parcora-opencart/' . self::VERSION . ' php/' . PHP_VERSION,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $curl = curl_init($this->baseUrl . $path);

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($raw === false) {
            throw new ApiException('Could not reach Parcora: ' . $error);
        }

        if ($status < 200 || $status >= 300) {
            $decoded = json_decode((string) $raw, true);
            $message = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Parcora returned HTTP ' . $status . '.';

            throw new ApiException($message, $status);
        }

        return ['body' => (string) $raw, 'content_type' => $contentType];
    }
}
