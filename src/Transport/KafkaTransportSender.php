<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\Transport\Stamp\KafkaIdentifierStamp;
use Exception;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use ARO\KafkaMessenger\Transport\Stamp\KafkaKeyStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class KafkaTransportSender implements SenderInterface
{
    private ?SerializerInterface   $serializer;

    public function __construct(
        private KafkaConnection              $connection,
        private ?KafkaTransportHookInterface $hook = null,
        ?SerializerInterface                 $serializer,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function send(Envelope $envelope): Envelope
    {
        if ($this->hook) {
            $envelope = $this->hook->beforeProduce($envelope);
        }

        $decodedEnvelope = $this->serializer->encode($envelope);

        $key = null;
        $partition = null;
        $messageFlags = null;

        if ($messageStamp = $envelope->last(KafkaMessageStamp::class)) {
            $partition = $messageStamp->partition ?? null;
            $messageFlags = $messageStamp->messageFlags ?? null;
            $key = $messageStamp->key ?? null;
        }

        if ($keyStamp = $envelope->last(KafkaKeyStamp::class)) {
            $key = $keyStamp->key;
        }

        if (!$partition) {
            $partition = \RD_KAFKA_PARTITION_UA;
        }

        if (!$messageFlags) {
            $messageFlags = \RD_KAFKA_CONF_OK;
        }

        try {
            $this->connection->produce(
                partition: $partition,
                messageFlags: $messageFlags,
                body: $decodedEnvelope["body"],
                key: $key,
                headers: $decodedEnvelope["headers"] ?? [],
            );
            $this->hook?->afterProduce($envelope);
        } catch (Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }

}
