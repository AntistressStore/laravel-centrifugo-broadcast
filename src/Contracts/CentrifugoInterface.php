<?php

declare(strict_types=1);

namespace denis660\Centrifugo\Contracts;

interface CentrifugoInterface
{
    /**
     * Send message into channel.
     */
    public function publish(string $channel, array $data, bool $skipHistory = false): mixed;

    /**
     * Send message into multiple channel.
     */
    public function broadcast(array $channels, array $data, bool $skipHistory = false): mixed;

    /**
     * Get channel presence information (all clients currently subscribed to this channel).
     */
    public function presence(string $channel): mixed;

    /**
     * Get channel presence information in short form (number of clients currently subscribed to this channel).
     */
    public function presenceStats(string $channel): mixed;

    /**
     * Get channel history information (list of last messages sent into channel).
     */
    public function history(string $channel, $limit = 0, $since = [], $reverse = false): mixed;

    /**
     * Remove channel history information .
     */
    public function historyRemove(string $channel): mixed;

    /**
     * Subscribe user to channel.
     *
     * @param string $client (optional)
     */
    public function subscribe(string $channel, string $user, string $client = ''): mixed;

    /**
     * Unsubscribe user from channel.
     *
     * @param string $client (optional)
     */
    public function unsubscribe(string $channel, string $user, string $client = ''): mixed;

    /**
     * Disconnect user by its ID.
     */
    public function disconnect(string $user_id, string $client = ''): mixed;

    /**
     * Get channels information (list of currently active channels).
     *
     * @param string $pattern (optional)
     */
    public function channels(string $pattern = ''): mixed;

    /**
     * Get stats information about running server nodes.
     */
    public function info(): mixed;

    /**
     * Generate connection token.
     */
    public function generateConnectionToken(string $userId = '', int $exp = 0, array $info = [], array $channels = [], array $meta = []): string;

    /**
     * Generate private channel token.
     */
    public function generatePrivateChannelToken(string $client, string $channel, int $exp = 0, array $info = []): string;
}
