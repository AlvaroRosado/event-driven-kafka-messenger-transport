<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class KafkaMessageVersionStamp implements StampInterface
{
    public function __construct(public int $identifier)
    {
    }
}
