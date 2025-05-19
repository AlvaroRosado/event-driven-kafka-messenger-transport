<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Stamp;

use RdKafka\Message as RdKafkaMessage;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class KafkaMessageStamp implements NonSendableStampInterface
{
    public function __construct(private readonly RdKafkaMessage $message)
    {
    }

    public function message(): RdKafkaMessage
    {
        return $this->message;
    }
}
