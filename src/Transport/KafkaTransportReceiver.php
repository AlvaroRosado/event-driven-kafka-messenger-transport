<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\SchemaRegistry\SchemaRegistryManager;
use ARO\KafkaMessenger\Transport\Metadata\KafkaMetadataHookInterface;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageStamp;
use RdKafka\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class KafkaTransportReceiver implements ReceiverInterface
{
    public function __construct(
        private KafkaConnection      $connection,
        private ?KafkaMetadataHookInterface $metadata = null,
        private ?SerializerInterface $serializer = new PhpSerializer(),
        private ?SchemaRegistryManager $schemaRegistryManager = null,
    ) {
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
        $this->ack($envelope);
    }

    private function getEnvelope(Message $message): iterable
    {
        if ($this->schemaRegistryManager && $this->serializer instanceof PhpSerializer) {
            throw new TransportException('Schema registry is enabled but the defined serializer is not compatible with it. You must use a different serializer.');
        }

        if ($this->schemaRegistryManager) {
            $message->payload = json_encode($this->schemaRegistryManager->decode($message));
        }

        $messageToConvertToEnvelope = [
            'body' => $message->payload,
            'headers' => $message->headers,
        ];

        $envelope = $this->serializer->decode($messageToConvertToEnvelope);

        if ($this->metadata) {
            $envelope = $this->metadata->afterConsume($envelope);
        }

        yield $envelope->with(new KafkaMessageStamp($message));
    }
}
