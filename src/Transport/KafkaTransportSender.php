<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use Exception;
use ARO\KafkaMessenger\Transport\Metadata\KafkaMetadataHookInterface;
use ARO\KafkaMessenger\Transport\Serializer\MessageSerializer;
use ARO\KafkaMessenger\Transport\Stamp\KafkaForceFlushStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageKeyStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageVersionStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaNoFlushStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class KafkaTransportSender implements SenderInterface
{
    public function __construct(
        private KafkaConnection             $connection,
        private ?KafkaMetadataHookInterface $metadata = null,
        private ?SerializerInterface        $serializer = new PhpSerializer(),
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $targetVersion = $envelope->last(KafkaMessageVersionStamp::class);

        if ($this->metadata) {
            $envelope = $this->metadata->beforeProduce($envelope);
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

        if ($keyStamp = $envelope->last(KafkaMessageKeyStamp::class)) {
            $key = $keyStamp->key;
        }

        $forceFlush = true;

        if ($envelope->last(KafkaNoFlushStamp::class)) {
            $forceFlush = false;
        }

        if ($envelope->last(KafkaNoFlushStamp::class) && $envelope->last(KafkaForceFlushStamp::class)) {
            $forceFlush = true;
        }

        $identifier = $decodedEnvelope["headers"][MessageSerializer::identifierHeaderKey()] ?? throw new Exception('Discriminatory name not found in envelope');

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
                forceFlush: $forceFlush,
                identifier: $identifier,
            );
        } catch (Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }

}
