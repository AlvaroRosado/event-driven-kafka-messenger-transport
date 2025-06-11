<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Tests\Unit\Transport\JsonSerializer;

use ARO\KafkaMessenger\Tests\Fixture\CustomStamp;
use ARO\KafkaMessenger\Tests\Fixture\NonSendableStamp;
use ARO\KafkaMessenger\Transport\JsonSerializer\HeaderSerializer;
use ARO\KafkaMessenger\Transport\Stamp\KafkaCustomHeadersStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaIdentifierStamp;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(HeaderSerializer::class)]
#[CoversClass(KafkaCustomHeadersStamp::class)]
#[CoversClass(KafkaIdentifierStamp::class)]
class HeaderSerializerTest extends TestCase
{
    private object $testMessage;

    protected function setUp(): void
    {
        $this->testMessage = new \stdClass();
    }

    public function testIdentifierHeaderKey(): void
    {
        $expected = 'X-KAFKA-identifier';
        $this->assertEquals($expected, HeaderSerializer::identifierHeaderKey());
    }

    public function testCustomAttributesHeaderKey(): void
    {
        $expected = 'X-KAFKA-custom-attr';
        $this->assertEquals($expected, HeaderSerializer::customAttributesHeaderKey());
    }

    public function testSerializeHeadersWithEmptyEnvelope(): void
    {
        $envelope = new Envelope($this->testMessage);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $this->assertIsArray($headers);
        $this->assertEmpty($headers);
    }

    public function testSerializeHeadersWithKafkaIdentifierStamp(): void
    {
        $identifierStamp = new KafkaIdentifierStamp('test-identifier-123');
        $envelope = new Envelope($this->testMessage, [$identifierStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $this->assertArrayHasKey('X-KAFKA-identifier', $headers);
        $this->assertEquals('test-identifier-123', $headers['X-KAFKA-identifier']);
    }

    public function testSerializeHeadersWithKafkaCustomHeadersStamp(): void
    {
        $customHeaders = ['custom-key-1' => 'value1', 'custom-key-2' => 'value2'];
        $customHeadersStamp = new KafkaCustomHeadersStamp($customHeaders);
        $envelope = new Envelope($this->testMessage, [$customHeadersStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $this->assertArrayHasKey('custom-key-1', $headers);
        $this->assertArrayHasKey('custom-key-2', $headers);
        $this->assertArrayHasKey('X-KAFKA-custom-attr', $headers);
        $this->assertEquals('value1', $headers['custom-key-1']);
        $this->assertEquals('value2', $headers['custom-key-2']);
        $this->assertEquals('["custom-key-1","custom-key-2"]', $headers['X-KAFKA-custom-attr']);
    }

    public function testSerializeHeadersWithSymfonyStamp(): void
    {
        $delayStamp = new DelayStamp(5000);
        $envelope = new Envelope($this->testMessage, [$delayStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $expectedKey = 'X-SYMFONY-' . DelayStamp::class;
        $this->assertArrayHasKey($expectedKey, $headers);
        $this->assertEquals(serialize($delayStamp), $headers[$expectedKey]);
    }

    public function testSerializeHeadersWithCustomStamp(): void
    {
        $customStamp = new CustomStamp('test-data');
        $envelope = new Envelope($this->testMessage, [$customStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $expectedKey = 'X-SYMFONY-' . CustomStamp::class;
        $this->assertArrayHasKey($expectedKey, $headers);
        $this->assertEquals(serialize($customStamp), $headers[$expectedKey]);
    }

    public function testSerializeHeadersExcludesNonSendableStamps(): void
    {
        $nonSendableStamp = new NonSendableStamp('should-not-appear');
        $identifierStamp = new KafkaIdentifierStamp('test-identifier');
        $envelope = new Envelope($this->testMessage, [$nonSendableStamp, $identifierStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $this->assertArrayHasKey('X-KAFKA-identifier', $headers);
        $this->assertArrayNotHasKey('X-SYMFONY-' . NonSendableStamp::class, $headers);
    }

    public function testSerializeHeadersWithMultipleStamps(): void
    {
        $identifierStamp = new KafkaIdentifierStamp('test-id');
        $customHeadersStamp = new KafkaCustomHeadersStamp(['key1' => 'value1']);
        $delayStamp = new DelayStamp(3000);

        $envelope = new Envelope($this->testMessage, [$identifierStamp, $customHeadersStamp, $delayStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $this->assertArrayHasKey('X-KAFKA-identifier', $headers);
        $this->assertArrayHasKey('key1', $headers);
        $this->assertArrayHasKey('X-KAFKA-custom-attr', $headers);
        $this->assertArrayHasKey('X-SYMFONY-' . DelayStamp::class, $headers);
        $this->assertEquals('test-id', $headers['X-KAFKA-identifier']);
        $this->assertEquals('value1', $headers['key1']);
    }

    public function testDeserializeHeadersWithEmptyHeaders(): void
    {
        $decodedEnvelope = ['headers' => []];

        $stamps = HeaderSerializer::deserializeHeaders($decodedEnvelope);

        $this->assertIsArray($stamps);
        $this->assertEmpty($stamps);
    }

    public function testDeserializeHeadersWithKafkaIdentifierStamp(): void
    {
        $decodedEnvelope = [
            'headers' => [
                'X-KAFKA-identifier' => 'test-identifier-456'
            ]
        ];

        $stamps = HeaderSerializer::deserializeHeaders($decodedEnvelope);

        $this->assertArrayHasKey(KafkaIdentifierStamp::class, $stamps);
        $this->assertInstanceOf(KafkaIdentifierStamp::class, $stamps[KafkaIdentifierStamp::class]);
        $this->assertEquals('test-identifier-456', $stamps[KafkaIdentifierStamp::class]->identifier);
    }

    public function testDeserializeHeadersWithKafkaCustomHeadersStamp(): void
    {
        $decodedEnvelope = [
            'headers' => [
                'X-KAFKA-custom-attr' => '["header1","header2"]',
                'header1' => 'value1',
                'header2' => 'value2'
            ]
        ];

        $stamps = HeaderSerializer::deserializeHeaders($decodedEnvelope);

        $this->assertArrayHasKey(KafkaCustomHeadersStamp::class, $stamps);
        $this->assertInstanceOf(KafkaCustomHeadersStamp::class, $stamps[KafkaCustomHeadersStamp::class]);
        $customHeadersStamp = $stamps[KafkaCustomHeadersStamp::class];
        $this->assertEquals('value1', $customHeadersStamp->getHeaders()['header1']);
        $this->assertEquals('value2', $customHeadersStamp->getHeaders()['header2']);
    }

    public function testDeserializeHeadersWithSymfonyStamp(): void
    {
        $originalStamp = new DelayStamp(7000);
        $serializedStamp = serialize($originalStamp);

        $decodedEnvelope = [
            'headers' => [
                'X-SYMFONY-' . DelayStamp::class => $serializedStamp
            ]
        ];

        $stamps = HeaderSerializer::deserializeHeaders($decodedEnvelope);

        $this->assertArrayHasKey(DelayStamp::class, $stamps);
        $this->assertInstanceOf(DelayStamp::class, $stamps[DelayStamp::class]);
        $this->assertEquals(7000, $stamps[DelayStamp::class]->getDelay());
    }

    public function testDeserializeHeadersWithMultipleStamps(): void
    {
        $originalDelayStamp = new DelayStamp(2000);
        $serializedStamp = serialize($originalDelayStamp);

        $decodedEnvelope = [
            'headers' => [
                'X-KAFKA-identifier' => 'multi-test-id',
                'X-KAFKA-custom-attr' => '["custom1"]',
                'custom1' => 'customValue',
                'X-SYMFONY-' . DelayStamp::class => $serializedStamp
            ]
        ];

        $stamps = HeaderSerializer::deserializeHeaders($decodedEnvelope);

        $this->assertCount(3, $stamps);
        $this->assertArrayHasKey(KafkaIdentifierStamp::class, $stamps);
        $this->assertArrayHasKey(KafkaCustomHeadersStamp::class, $stamps);
        $this->assertArrayHasKey(DelayStamp::class, $stamps);

        $this->assertEquals('multi-test-id', $stamps[KafkaIdentifierStamp::class]->identifier);
        $this->assertEquals('customValue', $stamps[KafkaCustomHeadersStamp::class]->getHeaders()['custom1']);
        $this->assertEquals(2000, $stamps[DelayStamp::class]->getDelay());
    }


    public function testSerializeHeadersWithEmptyCustomHeaders(): void
    {
        $customHeadersStamp = new KafkaCustomHeadersStamp([]);
        $envelope = new Envelope($this->testMessage, [$customHeadersStamp]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $this->assertArrayHasKey('X-KAFKA-custom-attr', $headers);
        $this->assertEquals('[]', $headers['X-KAFKA-custom-attr']);
    }

    public function testHeaderConstantsValues(): void
    {
        $this->assertEquals('X-KAFKA-', HeaderSerializer::HEADER_PACKAGE);
        $this->assertEquals('X-SYMFONY-', HeaderSerializer::HEADER_SYMFONY_PREFIX);
    }

    public function testSerializeHeadersWithMultipleStampsOfSameType(): void
    {
        $customStamp1 = new CustomStamp('data1');
        $customStamp2 = new CustomStamp('data2');
        $envelope = new Envelope($this->testMessage, [$customStamp1, $customStamp2]);

        $headers = HeaderSerializer::serializeHeaders($envelope);

        $expectedKey = 'X-SYMFONY-' . CustomStamp::class;
        $this->assertArrayHasKey($expectedKey, $headers);
        $this->assertEquals(serialize($customStamp2), $headers[$expectedKey]);
    }
}
