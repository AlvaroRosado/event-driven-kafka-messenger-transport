<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\Transport\Configuration\Configuration;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageStamp;
use RdKafka\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class KafkaTransportReceiver implements ReceiverInterface
{
    private ?SerializerInterface $serializer;
    private Configuration $configuration;
    private ?KafkaTransportHookInterface $hook;

    public function __construct(
        private KafkaConnection              $connection,
        Configuration                $configuration,
        ?KafkaTransportHookInterface $hook = null,
        ?SerializerInterface $serializer = null,
    ) {
        $this->configuration = $configuration;
        $this->hook = $hook;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(array $queues = []): iterable
    {
        /** @var ?Message $message */
        foreach ($this->connection->get($queues) as $message) {
            if (!$message) {
                return [];
            }
            yield from $this->getEnvelope($message);
        }
    }

    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($envelope->last(KafkaMessageStamp::class)->message());
    }

    public function reject(Envelope $envelope): void
    {
        if ($this->configuration->getConsumer()->commitOnError) {
            $this->ack($envelope);
        }
    }

    private function getEnvelope(Message $message): iterable
    {
        $messageToConvertToEnvelope = [
            'body' => $message->payload,
            'headers' => $message->headers,
        ];

        $envelope = $this->serializer->decode($messageToConvertToEnvelope);

        $this->hook?->afterConsume($envelope);

        yield $envelope->with(new KafkaMessageStamp($message));
    }
}
