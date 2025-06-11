<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Tests\Unit\Transport\JsonSerializer;

use ARO\KafkaMessenger\Tests\Fixture\Message\AnotherTestMessage;
use ARO\KafkaMessenger\Tests\Fixture\Message\CamelCaseMessage;
use ARO\KafkaMessenger\Tests\Fixture\Message\TestMessage;
use ARO\KafkaMessenger\Transport\JsonSerializer\HeaderSerializer;
use ARO\KafkaMessenger\Transport\JsonSerializer\JsonSerializer;
use ARO\KafkaMessenger\Transport\Stamp\KafkaIdentifierStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaCustomHeadersStamp;
use Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[CoversClass(JsonSerializer::class)]
#[CoversClass(HeaderSerializer::class)]
#[CoversClass(KafkaIdentifierStamp::class)]
#[CoversClass(KafkaCustomHeadersStamp::class)]
class JsonSerializerTest extends TestCase
{
    private JsonSerializer $jsonSerializer;
    private array $routingMap;
    private SymfonySerializer $symfonySerializer;

    protected function setUp(): void
    {
        $this->routingMap = [
            'test_message' => TestMessage::class,
            'another_message' => AnotherTestMessage::class,
            'camel_case_message' => CamelCaseMessage::class,
        ];

        $this->symfonySerializer = new SymfonySerializer(
            [new ObjectNormalizer()],
            [new JsonEncoder()]
        );

        $this->jsonSerializer = new JsonSerializer($this->routingMap);
    }


    public function testEncodeWithBasicMessage(): void
    {
        $message = new TestMessage('hello', 123);
        $envelope = new Envelope($message);

        $encoded = $this->jsonSerializer->encode($envelope);

        $this->assertIsArray($encoded);
        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);

        $decodedBody = json_decode($encoded['body'], true);
        $this->assertEquals('hello', $decodedBody['name']);
        $this->assertEquals(123, $decodedBody['value']);
    }

    public function testEncodeWithSerializedMessageStamp(): void
    {
        $message = new TestMessage('test', 456);
        $serializedStamp = new SerializedMessageStamp('{"custom":"serialized","data":"value"}');
        $envelope = new Envelope($message, [$serializedStamp]);

        $encoded = $this->jsonSerializer->encode($envelope);

        $this->assertEquals('{"custom":"serialized","data":"value"}', $encoded['body']);
        $this->assertIsArray($encoded['headers']);
    }

    public function testEncodeWithStamps(): void
    {
        $message = new TestMessage();
        $identifierStamp = new KafkaIdentifierStamp('test_id');
        $customHeadersStamp = new KafkaCustomHeadersStamp(['custom' => 'value']);
        $envelope = new Envelope($message, [$identifierStamp, $customHeadersStamp]);

        $encoded = $this->jsonSerializer->encode($envelope);

        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('X-KAFKA-identifier', $encoded['headers']);
        $this->assertEquals('test_id', $encoded['headers']['X-KAFKA-identifier']);
        $this->assertArrayHasKey('custom', $encoded['headers']);
        $this->assertEquals('value', $encoded['headers']['custom']);
    }

    public function testDecodeWithValidRoutingMapEntry(): void
    {
        $encodedEnvelope = [
            'body' => '{"name":"decoded","value":789}',
            'headers' => [
                'X-KAFKA-identifier' => 'test_message'
            ]
        ];

        $envelope = $this->jsonSerializer->decode($encodedEnvelope);

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());
        $this->assertEquals('decoded', $envelope->getMessage()->name);
        $this->assertEquals(789, $envelope->getMessage()->value);

        $identifierStamp = $envelope->last(KafkaIdentifierStamp::class);
        $this->assertInstanceOf(KafkaIdentifierStamp::class, $identifierStamp);
        $this->assertEquals('test_message', $identifierStamp->identifier);
    }

    public function testDecodeWithWildcardfRouting(): void
    {
        $wildcardRoutingMap = [
            JsonSerializer::WILDCARD => TestMessage::class
        ];
        $serializer = new JsonSerializer($wildcardRoutingMap);

        $encodedEnvelope = [
            'body' => '{"name":"wildcard","value":999}',
            'headers' => [
                'X-KAFKA-identifier' => 'unknown_message'
            ]
        ];

        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());
        $this->assertEquals('wildcard', $envelope->getMessage()->name);
        $this->assertEquals(999, $envelope->getMessage()->value);
    }

    public function testDecodeWithClassExistsAsIdentifier(): void
    {
        $encodedEnvelope = [
            'body' => '{"name":"class_exists","value":555}',
            'headers' => [
                'X-KAFKA-identifier' => TestMessage::class
            ]
        ];

        $envelope = $this->jsonSerializer->decode($encodedEnvelope);

        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());
        $this->assertEquals('class_exists', $envelope->getMessage()->name);
        $this->assertEquals(555, $envelope->getMessage()->value);
    }

    public function testDecodeWithoutIdentifierStamp(): void
    {
        $encodedEnvelope = [
            'body' => '{"name":"no_identifier","value":111}',
            'headers' => []
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No routing found for message "".');

        $this->jsonSerializer->decode($encodedEnvelope);
    }

    public function testDecodeWithInvalidRouting(): void
    {
        $encodedEnvelope = [
            'body' => '{"name":"invalid","value":222}',
            'headers' => [
                'X-KAFKA-identifier' => 'invalid_message_type'
            ]
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No routing found for message "".');

        $this->jsonSerializer->decode($encodedEnvelope);
    }

    public function testDecodeWithNonExistentClass(): void
    {
        $invalidRoutingMap = [
            'test_message' => 'NonExistentClass'
        ];
        $serializer = new JsonSerializer($invalidRoutingMap);

        $encodedEnvelope = [
            'body' => '{"name":"invalid","value":333}',
            'headers' => [
                'X-KAFKA-identifier' => 'test_message'
            ]
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No routing found for message "NonExistentClass".');

        $serializer->decode($encodedEnvelope);
    }

    public function testDecodeWithComplexStamps(): void
    {
        $encodedEnvelope = [
            'body' => '{"name":"complex","value":444}',
            'headers' => [
                'X-KAFKA-identifier' => 'test_message',
                'X-KAFKA-custom-attr' => '["header1","header2"]',
                'header1' => 'value1',
                'header2' => 'value2'
            ]
        ];

        $envelope = $this->jsonSerializer->decode($encodedEnvelope);

        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());

        $identifierStamp = $envelope->last(KafkaIdentifierStamp::class);
        $this->assertEquals('test_message', $identifierStamp->identifier);

        $customHeadersStamp = $envelope->last(KafkaCustomHeadersStamp::class);
        $this->assertInstanceOf(KafkaCustomHeadersStamp::class, $customHeadersStamp);
        $this->assertEquals('value1', $customHeadersStamp->getHeaders()['header1']);
        $this->assertEquals('value2', $customHeadersStamp->getHeaders()['header2']);
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $originalMessage = new TestMessage('roundtrip', 666);
        $identifierStamp = new KafkaIdentifierStamp('test_message');
        $originalEnvelope = new Envelope($originalMessage, [$identifierStamp]);

        $encoded = $this->jsonSerializer->encode($originalEnvelope);

        $decodedEnvelope = $this->jsonSerializer->decode($encoded);

        $this->assertInstanceOf(TestMessage::class, $decodedEnvelope->getMessage());
        $this->assertEquals('roundtrip', $decodedEnvelope->getMessage()->name);
        $this->assertEquals(666, $decodedEnvelope->getMessage()->value);

        $decodedIdentifierStamp = $decodedEnvelope->last(KafkaIdentifierStamp::class);
        $this->assertEquals('test_message', $decodedIdentifierStamp->identifier);
    }

    public function testDefaultSerializerWithCamelCaseConversion(): void
    {
        $message = new CamelCaseMessage('Jane', 'Smith');
        $envelope = new Envelope($message);

        $encoded = $this->jsonSerializer->encode($envelope);
        $decodedBody = json_decode($encoded['body'], true);

        $this->assertArrayHasKey('first_name', $decodedBody);
        $this->assertArrayHasKey('last_name', $decodedBody);
        $this->assertEquals('Jane', $decodedBody['first_name']);
        $this->assertEquals('Smith', $decodedBody['last_name']);
    }

    public function testDecodeWithEmptyBody(): void
    {
        $encodedEnvelope = [
            'body' => '{}',
            'headers' => [
                'X-KAFKA-identifier' => 'test_message'
            ]
        ];

        $envelope = $this->jsonSerializer->decode($encodedEnvelope);

        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());
        $this->assertEquals('test', $envelope->getMessage()->name);
        $this->assertEquals(42, $envelope->getMessage()->value);
    }

    public function testDecodeWithWildcardAndUnknownIdentifier(): void
    {
        $wildcardRoutingMap = [
            JsonSerializer::WILDCARD => TestMessage::class,
            'known_message' => AnotherTestMessage::class
        ];
        $serializer = new JsonSerializer($wildcardRoutingMap);

        $encodedEnvelope = [
            'body' => '{"name":"unknown_but_wildcard","value":888}',
            'headers' => [
                'X-KAFKA-identifier' => 'completely_unknown'
            ]
        ];

        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());
        $this->assertEquals('unknown_but_wildcard', $envelope->getMessage()->name);
    }

    public function testDecodePreferClassExistsOverWildcard(): void
    {
        $wildcardRoutingMap = [
            JsonSerializer::WILDCARD => AnotherTestMessage::class
        ];
        $serializer = new JsonSerializer($wildcardRoutingMap);

        $encodedEnvelope = [
            'body' => '{"name":"class_exists_priority","value":999}',
            'headers' => [
                'X-KAFKA-identifier' => TestMessage::class
            ]
        ];

        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(TestMessage::class, $envelope->getMessage());
        $this->assertEquals('class_exists_priority', $envelope->getMessage()->name);
    }

    public function testDecodeWithEmptyIdentifierStamp(): void
    {
        $emptyRoutingMap = [];
        $serializer = new JsonSerializer($emptyRoutingMap);

        $encodedEnvelope = [
            'body' => '{"name":"empty","value":123}',
            'headers' => []
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No routing found for message "".');

        $serializer->decode($encodedEnvelope);
    }
}
