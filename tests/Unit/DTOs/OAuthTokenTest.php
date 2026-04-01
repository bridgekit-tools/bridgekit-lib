<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\OAuthToken;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OAuthTokenTest extends TestCase
{
    public function test_constructor_assigns_properties(): void
    {
        $expiresAt = new DateTimeImmutable('+1 day');
        $token = new OAuthToken(
            accessToken: 'acc',
            refreshToken: 'ref',
            tokenType: 'Bearer',
            expiresIn: 3600,
            expiresAt: $expiresAt,
            scopes: ['a', 'b'],
        );

        $this->assertSame('acc', $token->accessToken);
        $this->assertSame('ref', $token->refreshToken);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame(3600, $token->expiresIn);
        $this->assertSame($expiresAt, $token->expiresAt);
        $this->assertSame(['a', 'b'], $token->scopes);
    }

    public function test_is_expired_returns_false_when_expires_at_is_null(): void
    {
        $token = new OAuthToken(accessToken: 'x');

        $this->assertFalse($token->isExpired());
    }

    public function test_is_expired_returns_true_when_expires_at_is_in_the_past(): void
    {
        $token = new OAuthToken(
            accessToken: 'x',
            expiresAt: (new DateTimeImmutable())->sub(new DateInterval('PT1H')),
        );

        $this->assertTrue($token->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_in_the_future(): void
    {
        $token = new OAuthToken(
            accessToken: 'x',
            expiresAt: (new DateTimeImmutable())->add(new DateInterval('PT1H')),
        );

        $this->assertFalse($token->isExpired());
    }

    public function test_has_scope(): void
    {
        $token = new OAuthToken(accessToken: 'x', scopes: ['email', 'profile']);

        $this->assertTrue($token->hasScope('email'));
        $this->assertFalse($token->hasScope('admin'));
    }

    public function test_from_array_sets_expires_at_from_expires_in(): void
    {
        $before = new DateTimeImmutable();
        $token = OAuthToken::fromArray([
            'access_token' => 'at',
            'expires_in' => 120,
        ]);
        $after = new DateTimeImmutable();

        $this->assertSame('at', $token->accessToken);
        $this->assertSame(120, $token->expiresIn);
        $this->assertNotNull($token->expiresAt);
        $this->assertGreaterThanOrEqual(
            $before->add(new DateInterval('PT119S')),
            $token->expiresAt,
        );
        $this->assertLessThanOrEqual(
            $after->add(new DateInterval('PT121S')),
            $token->expiresAt,
        );
    }

    public function test_from_array_parses_expires_at_string_when_expires_in_not_set(): void
    {
        $token = OAuthToken::fromArray([
            'access_token' => 'at',
            'expires_at' => '2026-01-15T12:00:00+00:00',
        ]);

        $this->assertSame('2026-01-15T12:00:00+00:00', $token->expiresAt?->format('c'));
    }

    public function test_from_array_prefers_expires_in_over_expires_at(): void
    {
        $token = OAuthToken::fromArray([
            'access_token' => 'at',
            'expires_in' => 60,
            'expires_at' => '2030-01-01T00:00:00+00:00',
        ]);

        $this->assertSame(60, $token->expiresIn);
        $future = new DateTimeImmutable();
        $this->assertGreaterThan($future, $token->expiresAt);
    }

    public function test_from_array_accepts_scope_key_as_array(): void
    {
        $token = OAuthToken::fromArray([
            'access_token' => 'at',
            'scope' => ['read', 'write'],
        ]);

        $this->assertSame(['read', 'write'], $token->scopes);
    }

    public function test_from_array_accepts_scopes_array(): void
    {
        $token = OAuthToken::fromArray([
            'access_token' => 'at',
            'scopes' => ['read', 'write'],
        ]);

        $this->assertSame(['read', 'write'], $token->scopes);
    }

    public function test_json_serialize_uses_snake_case_keys(): void
    {
        $expiresAt = new DateTimeImmutable('2026-06-01T10:00:00+00:00');
        $token = new OAuthToken(
            accessToken: 'a',
            refreshToken: 'r',
            tokenType: 'Bearer',
            expiresIn: 100,
            expiresAt: $expiresAt,
            scopes: ['s'],
        );

        $json = $token->jsonSerialize();

        $this->assertSame([
            'access_token' => 'a',
            'refresh_token' => 'r',
            'token_type' => 'Bearer',
            'expires_in' => 100,
            'expires_at' => $expiresAt->format('c'),
            'scopes' => ['s'],
        ], $json);
    }
}
