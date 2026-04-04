<?php

declare(strict_types=1);

namespace BridgeKit\Tests\Unit\Webhooks;

use BridgeKit\DTOs\WebhookPayload;
use BridgeKit\Enums\Provider;
use BridgeKit\Enums\WebhookEvent;
use BridgeKit\Events\Calendar\CalendarEventCreated;
use BridgeKit\Events\Social\PostPublished;
use BridgeKit\Events\Storage\FileCreated;
use BridgeKit\Events\Storage\FileDeleted;
use BridgeKit\Events\Storage\FileMoved;
use BridgeKit\Events\Storage\FileUpdated;
use BridgeKit\Events\WebhookReceived;
use BridgeKit\Webhooks\WebhookProcessor;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;

final class WebhookProcessorTest extends TestCase
{
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new WebhookProcessor();
    }

    public function test_dispatches_webhook_received_for_every_payload(): void
    {
        Event::fake();

        $payload = new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::Unknown,
        );

        $this->processor->process($payload);

        Event::assertDispatched(WebhookReceived::class);
    }

    public function test_dispatches_file_created_event(): void
    {
        Event::fake();

        $payload = new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::FileCreated,
            resourceId: 'file-123',
        );

        $this->processor->process($payload);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(FileCreated::class, fn (FileCreated $e) => $e->payload->resourceId === 'file-123');
    }

    public function test_dispatches_file_updated_event(): void
    {
        Event::fake();

        $this->processor->process(new WebhookPayload(
            provider: Provider::Microsoft,
            event: WebhookEvent::FileUpdated,
        ));

        Event::assertDispatched(FileUpdated::class);
    }

    public function test_dispatches_file_deleted_event(): void
    {
        Event::fake();

        $this->processor->process(new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::FileDeleted,
        ));

        Event::assertDispatched(FileDeleted::class);
    }

    public function test_dispatches_file_moved_event(): void
    {
        Event::fake();

        $payload = new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::FileMoved,
            changes: ['parent_from' => 'folder-a', 'parent_to' => 'folder-b'],
        );

        $this->processor->process($payload);

        Event::assertDispatched(FileMoved::class, function (FileMoved $e) {
            return $e->fromFolder() === 'folder-a' && $e->toFolder() === 'folder-b';
        });
    }

    public function test_dispatches_post_published_event(): void
    {
        Event::fake();

        $this->processor->process(new WebhookPayload(
            provider: Provider::Meta,
            event: WebhookEvent::PostPublished,
        ));

        Event::assertDispatched(PostPublished::class);
    }

    public function test_dispatches_calendar_event_created(): void
    {
        Event::fake();

        $this->processor->process(new WebhookPayload(
            provider: Provider::Microsoft,
            event: WebhookEvent::EventCreated,
        ));

        Event::assertDispatched(CalendarEventCreated::class);
    }

    public function test_unknown_event_only_dispatches_generic(): void
    {
        Event::fake();

        $this->processor->process(new WebhookPayload(
            provider: Provider::Google,
            event: WebhookEvent::Unknown,
        ));

        Event::assertDispatched(WebhookReceived::class);
        Event::assertNotDispatched(FileCreated::class);
        Event::assertNotDispatched(PostPublished::class);
    }
}
