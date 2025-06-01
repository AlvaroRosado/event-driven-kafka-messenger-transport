<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Hook;

use RdKafka\Message;
use Symfony\Component\Messenger\Envelope;

interface KafkaTransportHookInterface
{
    public function beforeProduce(Envelope $envelope): Envelope;
    public function afterProduce(Envelope $envelope): void;
    public function beforeConsume(Message $message): Message;
    public function afterConsume(Envelope $envelope): void;
}
