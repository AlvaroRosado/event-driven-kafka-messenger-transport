<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class KafkaTransport implements TransportInterface, QueueReceiverInterface
{
    public function __construct(
        private KafkaTransportSender $sender,
        private KafkaTransportReceiver $receiver
    ) {
    }

    public function get(): iterable
    {
        return $this->receiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->receiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->receiver->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->sender->send($envelope);
    }

    public function getFromQueues(array $queueNames): iterable
    {
        return $this->receiver->get($queueNames);
    }
}
