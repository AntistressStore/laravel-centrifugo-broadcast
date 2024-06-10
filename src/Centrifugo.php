<?php

declare(strict_types=1);

namespace denis660\Centrifugo;

use denis660\Centrifugo\Contracts\CentrifugoInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;

class Centrifugo implements CentrifugoInterface
{
    public const API_PATH = '/api';

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $config;

    /**
     * Create a new Centrifugo instance.
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->config = $this->initConfiguration($config);
    }

    /**
     * Init centrifugo configuration.
     */
    protected function initConfiguration(array $config): array
    {
        $defaults = [
            'url' => 'http://localhost:8000',
            'token_hmac_secret_key' => null,
            'api_key' => null,
            'ssl_key' => null,
            'verify' => true,
        ];

        foreach ($config as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * Publish data into channel.
     */
    public function publish(string $channel, array $data, bool $skipHistory = false): array
    {
        return $this->send('publish', [
            'channel' => $channel,
            'data' => $data,
            'skip_history' => $skipHistory,
        ]);
    }

    /**
     * Broadcast the same data into multiple channels.
     */
    public function broadcast(array $channels, array $data, bool $skipHistory = false): array
    {
        $params = [
            'channels' => $channels,
            'data' => $data,
            'skip_history' => $skipHistory,
        ];

        return $this->send('broadcast', $params);
    }

    /**
     * Get channel presence information (all clients currently subscribed on this channel).
     */
    public function presence(string $channel): array
    {
        return $this->send('presence', ['channel' => $channel]);
    }

    /**
     * Get channel presence information in short form.
     */
    public function presenceStats(string $channel): array
    {
        return $this->send('presence_stats', ['channel' => $channel]);
    }

    /**
     * Get channel history.
     */
    public function history(string $channel, int $limit = 0, array $since = [], bool $reverse = false): array
    {
        $params = [
            'channel' => $channel,
            'limit' => $limit,
            'reverse' => $reverse,
        ];
        if ( ! empty($since)) {
            $params['since'] = $since;
        }

        return $this->send('history', $params);
    }

    /**
     * Remove channel history information.
     */
    public function historyRemove(string $channel): array
    {
        return $this->send('history_remove', [
            'channel' => $channel,
        ]);
    }

    /**
     * Subscribe user to channel.
     */
    public function subscribe(string $channel, string $user, string $client = ''): array
    {
        return $this->send('subscribe', [
            'channel' => $channel,
            'user' => $user,
            'client' => $client,
        ]);
    }

    /**
     * Unsubscribe user from channel.
     */
    public function unsubscribe(string $channel, string $user, string $client = ''): array
    {
        return $this->send('unsubscribe', [
            'channel' => $channel,
            'user' => $user,
            'client' => $client,
        ]);
    }

    /**
     * Disconnect user by its ID.
     */
    public function disconnect(string $user_id, string $client = ''): array
    {
        return $this->send('disconnect', [
            'user' => $user_id,
            'client' => $client,
        ]);
    }

    /**
     * Get all active channels.
     */
    public function channels(string $pattern = ''): array
    {
        return $this->send('channels', ['pattern' => $pattern]);
    }

    /**
     * Get stats information about running server nodes.
     */
    public function info(): array
    {
        return $this->send('info');
    }

    /**
     * Generate connection JWT.
     */
    public function generateConnectionToken(string $userId = '', int $exp = 0, array $info = [], array $channels = [], array $meta = []): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = ['sub' => $userId];

        foreach (['exp', 'info', 'channels', 'meta'] as $value) {
            if ( ! empty(${$value})) {
                $payload[$value] = ${$value};
            }
        }

        $segments = [];
        $segments[] = $this->urlsafeB64Encode(json_encode($header));
        $segments[] = $this->urlsafeB64Encode(json_encode($payload));
        $signing_input = implode('.', $segments);
        $signature = $this->sign($signing_input, $this->getSecret());
        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Generate private channel token.
     */
    public function generatePrivateChannelToken(string $client, string $channel, int $exp = 0, array $info = []): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = ['channel' => $channel, 'client' => $client];
        if ( ! empty($info)) {
            $payload['info'] = $info;
        }
        if ($exp) {
            $payload['exp'] = $exp;
        }
        $segments = [];
        $segments[] = $this->urlsafeB64Encode(json_encode($header));
        $segments[] = $this->urlsafeB64Encode(json_encode($payload));
        $signing_input = implode('.', $segments);
        $signature = $this->sign($signing_input, $this->getSecret());
        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Get token hmac secret key.
     */
    protected function getSecret(): string
    {
        return $this->config['token_hmac_secret_key'];
    }

    /**
     * Get Api Key.
     */
    protected function getApiKey(): string
    {
        return $this->config['api_key'];
    }

    /**
     * Send message to centrifugo server.
     */
    protected function send(string $method, array $params = []): array
    {
        $headers = [
            'Content-type' => 'application/json',
            'X-API-Key' => $this->getApiKey(),
        ];

        $url = $this->prepareUrl();

        $request_config = [
            'base_uri' => $url,
            'headers' => $headers,
            'json' => $params,
            'http_errors' => true,
            'verify' => $this->config['verify'],
            'ssl_key' => $this->config['ssl_key'],
        ];

        try {
            $response = $this->httpClient->post($method, $request_config);

            $result = json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            $result = [
                'method' => $method,
                'error' => $e->getMessage(),
                'body' => $params,
            ];
        }

        return $result;
    }

    /**
     * Prepare URL to send the http request.
     */
    protected function prepareUrl(): string
    {
        $address = rtrim($this->config['url'], '/');

        if (substr_compare($address, static::API_PATH, -strlen(static::API_PATH)) !== 0) {
            $address .= static::API_PATH;
        }

        return $address.'/';
    }

    /**
     * Safely encode string in base64.
     *
     * @return string
     */
    private function urlsafeB64Encode(string $input): string|array
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Sign message with secret key.
     */
    private function sign(string $msg, string $key): string
    {
        return hash_hmac('sha256', $msg, $key, true);
    }
}
