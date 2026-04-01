<?php

declare(strict_types=1);

namespace BridgeKit\Contracts\Messaging;

use BridgeKit\DTOs\EmailMessage;
use BridgeKit\Enums\MailFolder;

interface EmailSenderInterface
{
    /**
     * Send an email and return the message ID.
     */
    public function send(EmailMessage $message): string;

    /**
     * @return array<int, EmailMessage>
     */
    public function listMessages(MailFolder|string $folder = MailFolder::Inbox, int $limit = 50): array;

    public function getMessage(string $messageId): EmailMessage;

    public function deleteMessage(string $messageId): bool;
}
