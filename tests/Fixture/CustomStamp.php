<?php

namespace ARO\KafkaMessenger\Tests\Fixture;

use Symfony\Component\Messenger\Stamp\StampInterface;

class CustomStamp implements StampInterface
{
    public function __construct(public string $data = 'custom_data')
    {
    }
}
