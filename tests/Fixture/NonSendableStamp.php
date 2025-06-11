<?php

namespace ARO\KafkaMessenger\Tests\Fixture;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class NonSendableStamp implements NonSendableStampInterface
{
    public function __construct(public string $value = 'test')
    {
    }
}
