<?php

declare(strict_types=1);

namespace BridgeKit\Enums;

enum MailFolder: string
{
    case Inbox = 'INBOX';
    case Sent = 'SENT';
    case Drafts = 'DRAFTS';
    case Trash = 'TRASH';
    case Spam = 'SPAM';
}
