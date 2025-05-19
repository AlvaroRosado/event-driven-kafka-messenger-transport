<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Serializer;

use Exception;
use ARO\KafkaMessenger\Transport\Stamp\KafkaCustomHeadersStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageIdentifierStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
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
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

class MessageSerializer implements SerializerInterface
{
    public const HEADER_EXOTICCA_PREFIX = 'X-exoticca-';
    public const HEADER_SYMFONY_PREFIX = 'X-symfony-';

    public const WILDCARD = '*';

    private SymfonySerializerInterface $serializer;
    private string $staticMethodIdentifier;
    private array $routingMap;

    public function __construct(
        string $staticMethodIdentifier,
        array $routingMap,
        ?SymfonySerializerInterface $serializer = null,
    ) {
        $this->routingMap = $routingMap;
        $this->staticMethodIdentifier = $staticMethodIdentifier;
        $this->serializer = $serializer ?? $this->createDefaultSerializer();
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $stamps = $this->decodeHeaders($encodedEnvelope);
        $identifier = (string)($stamps[KafkaMessageIdentifierStamp::class] ?? null);

        if (isset($this->routingMap[self::WILDCARD])) {
            $routing = $this->routingMap[self::WILDCARD] ?? null;
        } else {
            $routing = $this->routingMap[$identifier] ?? null;
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
        $referenceName = $this->getReferenceName($message);

        $envelope = $envelope->with(new KafkaMessageIdentifierStamp($referenceName));

        $serializedMessageStamp = $envelope->last(SerializedMessageStamp::class);

        $body = $serializedMessageStamp
            ? $serializedMessageStamp->getSerializedMessage()
            : $this->serializer->serialize($message, 'json');

        return [
            'body' => $body,
            'headers' => $this->encodeHeaders($envelope),
        ];
    }

    private function getReferenceName(object $message): string
    {
        if (!$this->staticMethodIdentifier || !method_exists($message, $this->staticMethodIdentifier)) {
            throw new \InvalidArgumentException(sprintf('Static method "%s" does not exist in class "%s".', $this->staticMethodIdentifier, $message::class));
        }

        $referenceName = $message::{$this->staticMethodIdentifier}();

        if (!is_string($referenceName) || trim($referenceName) === '') {
            throw new \InvalidArgumentException(sprintf('Static method "%s::%s" must return a non-empty string.', $message::class, $this->staticMethodIdentifier));
        }

        return $referenceName;
    }

    private function decodeHeaders(array $encodedEnvelope): array
    {
        $stamps = [];
        $customHeadersStamp = new KafkaCustomHeadersStamp();

        foreach ($encodedEnvelope['headers'] as $name => $value) {
            if (str_starts_with($name, self::HEADER_SYMFONY_PREFIX)) {
                $stamps += $this->deserializeSymfonyHeader($value);
                continue;
            }
            if (str_starts_with($name, self::identifierHeaderKey())) {
                $stamps[KafkaMessageIdentifierStamp::class] = new KafkaMessageIdentifierStamp($value);
                continue;
            }
            if (str_starts_with($name, self::customAttributesHeaderKey())) {
                $stamps[KafkaCustomHeadersStamp::class] = $this->extractCustomHeaders($value, $encodedEnvelope['headers'], $customHeadersStamp);
            }
        }

        return $stamps;
    }

    private function deserializeSymfonyHeader(string $value): array
    {
        try {
            $content = unserialize($value);
            return [$content::class => $content];
        } catch (\Throwable $e) {
            throw new MessageDecodingFailedException('Could not decode stamp: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    private function extractCustomHeaders(string $value, array $headers): KafkaCustomHeadersStamp
    {
        $customHeadersStamp = new KafkaCustomHeadersStamp();
        foreach (json_decode($value, true) as $key) {
            $customHeadersStamp = $customHeadersStamp->withHeader($key, $headers[$key]);
        }
        return $customHeadersStamp;
    }

    private function encodeHeaders(Envelope $envelope): array
    {
        $headers = [];
        foreach ($envelope->withoutStampsOfType(NonSendableStampInterface::class)->all() as $class => $stamps) {
            foreach ($stamps as $stamp) {
                if ($stamp instanceof KafkaCustomHeadersStamp) {
                    $headers += $stamp->getHeaders();
                    $headers[self::customAttributesHeaderKey()] = json_encode(array_keys($stamp->getHeaders()));
                    continue;
                }
                if ($stamp instanceof KafkaMessageIdentifierStamp) {
                    $headers[self::identifierHeaderKey()] = $stamp->identifier;
                    continue;
                }
                $headers[self::HEADER_SYMFONY_PREFIX.$class] = serialize($stamp);
            }
        }
        return $headers;
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

    public static function identifierHeaderKey(): string
    {
        return self::HEADER_EXOTICCA_PREFIX.'identifier';
    }

    public static function customAttributesHeaderKey(): string
    {
        return self::HEADER_EXOTICCA_PREFIX.'custom-attr';
    }
}
