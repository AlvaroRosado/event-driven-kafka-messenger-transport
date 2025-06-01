<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class KafkaKeyStamp implements NonSendableStampInterface
{
    public function __construct(public string $key)
    {
    }
}
