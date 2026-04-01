<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\DTOs;

use BridgeKit\DTOs\EmailMessage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailMessageTest extends TestCase
{
    public function test_constructor_assigns_properties(): void
    {
        $date = new DateTimeImmutable('2026-04-01T15:30:00+00:00');
        $attachments = [
            ['name' => 'f.txt', 'content' => 'hi', 'mime_type' => 'text/plain'],
        ];
        $msg = new EmailMessage(
            subject: 'S',
            body: 'B',
            to: ['a@x.com'],
            from: 'from@x.com',
            cc: ['cc@x.com'],
            bcc: ['bcc@x.com'],
            isHtml: false,
            attachments: $attachments,
            messageId: '<id@host>',
            date: $date,
            metadata: ['thread' => 1],
        );

        $this->assertSame('S', $msg->subject);
        $this->assertSame('B', $msg->body);
        $this->assertSame(['a@x.com'], $msg->to);
        $this->assertSame('from@x.com', $msg->from);
        $this->assertSame(['cc@x.com'], $msg->cc);
        $this->assertSame(['bcc@x.com'], $msg->bcc);
        $this->assertFalse($msg->isHtml);
        $this->assertSame($attachments, $msg->attachments);
        $this->assertSame('<id@host>', $msg->messageId);
        $this->assertSame($date, $msg->date);
        $this->assertSame(['thread' => 1], $msg->metadata);
    }

    public function test_from_array_maps_keys(): void
    {
        $msg = EmailMessage::fromArray([
            'subject' => 'Sub',
            'body' => 'Body',
            'to' => ['t@e.com'],
            'from' => 'f@e.com',
            'cc' => ['c@e.com'],
            'bcc' => ['b@e.com'],
            'is_html' => true,
            'attachments' => [],
            'message_id' => 'mid',
            'date' => '2026-05-01T10:00:00+00:00',
            'metadata' => ['x' => 'y'],
        ]);

        $this->assertSame('Sub', $msg->subject);
        $this->assertSame('Body', $msg->body);
        $this->assertSame(['t@e.com'], $msg->to);
        $this->assertSame('f@e.com', $msg->from);
        $this->assertSame(['c@e.com'], $msg->cc);
        $this->assertSame(['b@e.com'], $msg->bcc);
        $this->assertTrue($msg->isHtml);
        $this->assertSame('mid', $msg->messageId);
        $this->assertSame('2026-05-01T10:00:00+00:00', $msg->date?->format('c'));
        $this->assertSame(['x' => 'y'], $msg->metadata);
    }

    public function test_json_serialize(): void
    {
        $date = new DateTimeImmutable('2026-07-01T12:00:00+00:00');
        $msg = new EmailMessage(
            subject: 'S',
            body: 'B',
            to: ['to@x.com'],
            from: 'f@x.com',
            cc: [],
            bcc: [],
            isHtml: true,
            attachments: [],
            messageId: null,
            date: $date,
            metadata: [],
        );

        $this->assertSame([
            'subject' => 'S',
            'body' => 'B',
            'to' => ['to@x.com'],
            'from' => 'f@x.com',
            'cc' => [],
            'bcc' => [],
            'is_html' => true,
            'attachments' => [],
            'message_id' => null,
            'date' => $date->format('c'),
            'metadata' => [],
        ], $msg->jsonSerialize());
    }
}
