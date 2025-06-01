<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class KafkaIdentifierStamp implements StampInterface
{
    public function __construct(public string $identifier)
    {
    }

    public function __toString(): string
    {
        return $this->identifier;
    }

}
