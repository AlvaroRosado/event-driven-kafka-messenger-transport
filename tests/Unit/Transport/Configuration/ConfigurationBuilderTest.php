<?php

namespace ARO\KafkaMessenger\Tests\Unit\Transport\Configuration;

use ARO\KafkaMessenger\Tests\ObjectMother\GlobalOptionsObjectMother;
use ARO\KafkaMessenger\Tests\ObjectMother\TransportOptionsObjectMother;
use ARO\KafkaMessenger\Transport\Configuration\ConfigurationBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConfigurationBuilder::class)]
class ConfigurationBuilderTest extends TestCase
{
    private ConfigurationBuilder $configurationBuilder;

    protected function setUp(): void
    {
        $this->configurationBuilder = new ConfigurationBuilder();
    }

    public function testBuildWithCompleteValidConfiguration(): void
    {
        $dsn = 'ed+kafka://localhost:9092/kafka_events';
        $globalOptions = GlobalOptionsObjectMother::createDefault();
        $transportOptions = TransportOptionsObjectMother::createWithJsonSerializationEnabled();

        $result = $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);

        $this->assertEquals('localhost:9092', $result->getHost());
        $this->assertTrue($result->isJsonSerializationEnabled());
        $this->assertNotEmpty($result->getConsumer()->topics);
        $this->assertContains('user_events', $result->getConsumer()->topics);
    }

    public function testBuildWithMinimalValidConfiguration(): void
    {
        $dsn = 'ed+kafka://localhost:9092/minimal_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createMinimal();

        $result = $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);

        $this->assertFalse($result->isJsonSerializationEnabled());
        $this->assertNull($result->customSymfonySerializer());
    }

    public function testBuildWithJsonSerializationDisabled(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createDefault();
        $transportOptions = TransportOptionsObjectMother::createWithJsonSerializationDisabled();

        $result = $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);

        $this->assertFalse($result->isJsonSerializationEnabled());
        $this->assertEquals('CustomSerializer', $result->customSymfonySerializer());
    }

    public function testBuildWithDefaultJsonSerialization(): void
    {
        $dsn = 'ed+kafka://localhost:9092/default_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithoutJsonSerialization();

        $result = $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);

        $this->assertFalse($result->isJsonSerializationEnabled());
        $this->assertNull($result->customSymfonySerializer());
    }

    public function testBuildThrowsExceptionForInvalidOptions(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidOptions();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid option\(s\).*invalid_option.*another_invalid_option.*for transport.*test_transport/');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionWhenNoTopicsConfigured(): void
    {
        $dsn = 'ed+kafka://localhost:9092/no_topics_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithoutTopics();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be configured for transport "no_topics_transport"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildMergesGlobalAndTransportOptionsCorrectly(): void
    {
        $dsn = 'ed+kafka://localhost:9092/merge_test_transport';
        $globalOptions = GlobalOptionsObjectMother::createWithConsumerConfig();
        $transportOptions = TransportOptionsObjectMother::createWithOverridingConsumerConfig();

        $result = $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);

        $this->assertContains('transport_topic', $result->getConsumer()->topics);
        $this->assertNotContains('global_topic', $result->getConsumer()->topics);
    }

    public function testBuildWithProducerOnlyTopics(): void
    {
        $dsn = 'ed+kafka://localhost:9092/producer_only_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithProducerOnlyTopics();

        $result = $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);

        $this->assertNotEmpty($result->getProducer()->topics);
        $this->assertContains('producer_topic', $result->getProducer()->topics);
    }
}

