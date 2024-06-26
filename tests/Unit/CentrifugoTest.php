<?php

namespace denis660\Centrifugo\Test\Unit;

use denis660\Centrifugo\Centrifugo;
use denis660\Centrifugo\Test\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

/**
 * @internal
 *
 * @coversNothing
 */
class CentrifugoTest extends TestCase
{
    public function testGenerateToken(): void
    {
        $timestamp = 1491650279;
        $info = [
            'first_name' => 'Luck',
            'last_name' => 'Skywalker',
        ];
        $clientId = '0c951315-be0e-4516-b99e-05e60b0cc317';

        $clientToken1 = $this->centrifuge->generateConnectionToken($clientId, $timestamp);
        $this->assertEquals(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIwYzk1MTMxNS1iZTBlLTQ1MTYtYjk5ZS0wNWU2MGIwY2MzMTciLCJleHAiOjE0OTE2NTAyNzl9.5JzL2nG7KmVw0DLpwq57VsTt-QK3S9LeO4xF-D7e0ro',
            $clientToken1
        );

        $clientToken2 = $this->centrifuge->generatePrivateChannelToken($clientId, 'test', $timestamp, $info);
        $this->assertEquals(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJjaGFubmVsIjoidGVzdCIsImNsaWVudCI6IjBjOTUxMzE1LWJlMGUtNDUxNi1iOTllLTA1ZTYwYjBjYzMxNyIsImluZm8iOnsiZmlyc3RfbmFtZSI6Ikx1Y2siLCJsYXN0X25hbWUiOiJTa3l3YWxrZXIifSwiZXhwIjoxNDkxNjUwMjc5fQ.CFbSCKtEA95iIa47S_30zysYANyLdOxxekfmcje3nZI',
            $clientToken2
        );
    }

    public function testCentrifugoApiPublish(): void
    {
        $publish = $this->centrifuge->publish('test-test', ['event' => 'test-event']);
        $this->assertArrayHasKey('result', $publish);
        $this->assertIsArray($publish['result']);
    }

    public function testCentrifugoApiBroadcast(): void
    {
        $broadcast = $this->centrifuge->broadcast(['test-channel-1', 'test-channel-2'], ['event' => 'test-event']);
        $this->assertCount(2, $broadcast['result']['responses']);
        $this->assertArrayHasKey('result', $broadcast['result']['responses'][0]);
        $this->assertArrayHasKey('result', $broadcast['result']['responses'][1]);
    }

    // 108 error if centrifugo config "history" and "history_ttl" dont set
    // for correct response you need set centrifugo config "presence": true
    public function testCentrifugoApiPresence(): void
    {
        $presence = $this->centrifuge->presence('test-channel');
        $this->assertArrayHasKey('presence', $presence['result']);
        $this->assertEmpty($presence['result']['presence']);
        // $this->assertEquals('not available', $presence['error']['message']);
        // $this->assertEquals(108, $presence['error']['code']);
    }

    // 108 error if centrifugo config "history" and "history_ttl" dont set
    // for correct response you need set centrifugo config param >0,
    // "history":20,"history_ttl":"2m"
    public function testCentrifugoApiHistory(): void
    {
        $history = $this->centrifuge->history('test-channel');
        $this->assertArrayHasKey('publications', $history['result']);
        $this->assertEmpty($history['result']['publications']);
        // $this->assertIsArray($history['error']);
        // $this->assertEquals('not available', $history['error']['message']);
        // $this->assertEquals(108, $history['error']['code']);
    }

    public function testCentrifugoApiChannels(): void
    {
        $channels = $this->centrifuge->channels();
        $this->assertArrayHasKey('channels', $channels['result']);
    }

    public function testCentrifugoApiUnsubscribe(): void
    {
        $unsubscribe = $this->centrifuge->unsubscribe('test-channel', '1');
        $this->assertEquals([], $unsubscribe['result']);
    }

    public function testCentrifugoApiSubscribe(): void
    {
        $subscribe = $this->centrifuge->unsubscribe('test-channel', '1');
        $this->assertEquals([], $subscribe['result']);
    }

    // 108 error if centrifugo config "presence" dont set
    // for correct response you need set centrifugo config "presence": true
    public function testCentrifugoApiStats(): void
    {
        $stats = $this->centrifuge->presenceStats('test-channel');

        $this->assertEquals([
            'result' => [
                'num_clients' => 0,
                'num_users' => 0,
            ],
        ], $stats);

        // $this->assertEquals('not available', $stats['error']['message']);
        // $this->assertEquals(108, $stats['error']['code']);
    }

    public function testTimeoutFunction(): void
    {
        $timeout = 3;
        $delta = 0.5;

        $badCentrifugo = new Centrifugo(
            [
                'driver' => 'centrifugo',
                'token_hmac_secret_key' => '',
                'api_key' => '',
                'api_path' => '',
                'url' => 'http://localhost:3999',
                'timeout' => $timeout,
                'tries' => 1,
            ],
            new Client()
        );

        $start = microtime(true);
        $this->expectException(ConnectException::class);

        try {
            $badCentrifugo->publish('test-channel', ['event' => 'test-event']);
        } catch (\Exception $e) {
            $end = microtime(true);
            $eval = $end - $start;
            $this->assertTrue($eval < $timeout + $delta);

            throw $e;
        }
    }

    public function testTriesFunction(): void
    {
        $timeout = 1;
        $tries = 3;
        $delta = 0.5;

        $badCentrifugo = new Centrifugo(
            [
                'driver' => 'centrifugo',
                'token_hmac_secret_key' => '',
                'api_key' => '',
                'api_path' => '',
                'url' => 'http://localhost:3999',
                'timeout' => $timeout,
                'tries' => $tries,
            ],
            new Client()
        );

        $start = microtime(true);

        $this->expectException(ConnectException::class);

        try {
            $badCentrifugo->publish('test-channel', ['event' => 'test-event']);
        } catch (\Exception $e) {
            $end = microtime(true);
            $eval = $end - $start;
            $this->assertTrue($eval < ($timeout + $delta) * $tries);

            throw $e;
        }
    }
}
