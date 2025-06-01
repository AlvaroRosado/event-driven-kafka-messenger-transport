<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\JsonSerializer;

use ARO\KafkaMessenger\Transport\Stamp\KafkaCustomHeadersStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaIdentifierStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class HeaderSerializer
{
    public const HEADER_PACKAGE = 'X-KAFKA-';
    public const HEADER_SYMFONY_PREFIX = 'X-SYMFONY-';

    public static function identifierHeaderKey(): string
    {
        return self::HEADER_PACKAGE . 'identifier';
    }

    public static function customAttributesHeaderKey(): string
    {
        return self::HEADER_PACKAGE . 'custom-attr';
    }

    public static function serializeHeaders(Envelope $envelope): array
    {
        $headers = [];

        foreach ($envelope->withoutStampsOfType(NonSendableStampInterface::class)->all() as $class => $stamps) {
            foreach ($stamps as $stamp) {
                if ($stamp instanceof KafkaCustomHeadersStamp) {
                    $headers += $stamp->getHeaders();
                    $headers[self::customAttributesHeaderKey()] = json_encode(array_keys($stamp->getHeaders()));
                } elseif ($stamp instanceof KafkaIdentifierStamp) {
                    $headers[self::identifierHeaderKey()] = $stamp->identifier;
                } else {
                    $headers[self::HEADER_SYMFONY_PREFIX . $class] = serialize($stamp);
                }
            }
        }

        return $headers;
    }

    public static function deserializeHeaders(array $decodedEnvelope): array
    {
        $stamps = [];
        $customHeadersStamp = new KafkaCustomHeadersStamp();
        $headers = $decodedEnvelope["headers"];
        foreach ($headers as $name => $value) {
            if (str_starts_with($name, self::HEADER_SYMFONY_PREFIX)) {
                $stamp = self::decodeSymfonyStamp($value);
                $stamps[$stamp::class] = $stamp;
            } elseif ($name === self::identifierHeaderKey()) {
                $stamps[KafkaIdentifierStamp::class] = new KafkaIdentifierStamp($value);
            } elseif ($name === self::customAttributesHeaderKey()) {
                $stamps[KafkaCustomHeadersStamp::class] = self::extractCustomHeaders($value, $headers, $customHeadersStamp);
            }
        }

        return $stamps;
    }

    private static function decodeSymfonyStamp(string $value): object
    {
        try {
            return unserialize($value);
        } catch (\Throwable $e) {
            throw new MessageDecodingFailedException('Could not decode stamp: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private static function extractCustomHeaders(string $value, array $headers, KafkaCustomHeadersStamp $customHeadersStamp): KafkaCustomHeadersStamp
    {
        foreach (json_decode($value, true) as $key) {
            $customHeadersStamp = $customHeadersStamp->withHeader($key, $headers[$key] ?? null);
        }
        return $customHeadersStamp;
    }
}
