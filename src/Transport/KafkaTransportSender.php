<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final class KafkaTransportSender implements SenderInterface
{
    public function __construct(
        private KafkaConnection              $connection,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $this->connection->produce($envelope);
        return $envelope;
    }

}
