<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Tests;

use PHPUnit\Framework\TestCase;
use Tjseabury\WoprIslands\Snapshot\SnapshotException;
use Tjseabury\WoprIslands\Snapshot\SnapshotSigner;

final class SnapshotSignerTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $signer = new SnapshotSigner('test-secret-key');

        $payload = [
            'v' => 1,
            'slug' => 'counter',
            'instanceId' => 'abc-123',
            'class' => 'App\\Counter',
            'state' => ['count' => 41],
        ];

        $token = $signer->createToken($payload);
        $this->assertNotSame('', $token);
        $this->assertStringContainsString('.', $token);

        $decoded = $signer->parseToken($token);
        $this->assertSame($payload, $decoded);
    }

    public function testRejectsTamperedPayload(): void
    {
        $signer = new SnapshotSigner('test-secret-key');
        $token = $signer->createToken(['v' => 1, 'slug' => 'x', 'instanceId' => 'i', 'class' => 'C', 'state' => []]);

        $parts = explode('.', $token, 2);
        $this->assertCount(2, $parts);
        $tampered = $parts[0] . '!!!.' . $parts[1];

        $this->expectException(SnapshotException::class);
        $signer->parseToken($tampered);
    }

    public function testRejectsWrongSecret(): void
    {
        $a = new SnapshotSigner('secret-a');
        $token = $a->createToken(['v' => 1, 'slug' => 'x', 'instanceId' => 'i', 'class' => 'C', 'state' => []]);

        $b = new SnapshotSigner('secret-b');
        $this->expectException(SnapshotException::class);
        $b->parseToken($token);
    }
}
