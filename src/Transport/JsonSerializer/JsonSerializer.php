<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\JsonSerializer;

use ARO\KafkaMessenger\Transport\Stamp\KafkaIdentifierStamp;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class JsonSerializer implements SerializerInterface
{
    public const WILDCARD = '*';
    private array $routingMap;
    private SymfonySerializer $serializer;

    public function __construct(
        array $routingMap,
        ?SymfonySerializer $serializer  = null,
    ) {
        $this->routingMap = $routingMap;
        $this->serializer = $serializer ?? $this->createDefaultSerializer();
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $stamps = HeaderSerializer::deserializeHeaders($encodedEnvelope);
        $identifier = (string)($stamps[KafkaIdentifierStamp::class] ?? null);

        if (isset($this->routingMap[self::WILDCARD])) {
            $routing = $this->routingMap[self::WILDCARD] ?? null;
        } else {
            $routing = $this->routingMap[$identifier] ?? null;
        }

        if (class_exists($identifier)) {
            $routing = $identifier;
        }

        if (!$routing || !class_exists($routing)) {
            throw new Exception(sprintf('No routing found for message "%s".', $routing));
        }

        $body = $this->serializer->deserialize($encodedEnvelope['body'], $routing, 'json');

        return new Envelope($body, $stamps);
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        $serializedMessageStamp = $envelope->last(SerializedMessageStamp::class);

        $body = $serializedMessageStamp
            ? $serializedMessageStamp->getSerializedMessage()
            : $this->serializer->serialize($message, 'json');

        return [
            'body' => $body,
            'headers' => HeaderSerializer::serializeHeaders($envelope),
        ];
    }

    private function createDefaultSerializer(): SymfonySerializer
    {
        return new SymfonySerializer(normalizers: [
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: null,
                nameConverter: null,
                propertyAccessor:   null,
                propertyTypeExtractor:  new PropertyInfoExtractor(
                    [],
                    [new PhpDocExtractor(), new ReflectionExtractor()]
                ),
                defaultContext: [
                    AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ]
            )
        ], encoders: [new JsonEncoder()]);
    }
}
