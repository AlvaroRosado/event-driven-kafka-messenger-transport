<?php

namespace ARO\KafkaMessenger\Tests\Unit\Transport\Configuration;

use ARO\KafkaMessenger\Tests\ObjectMother\GlobalOptionsObjectMother;
use ARO\KafkaMessenger\Tests\ObjectMother\TransportOptionsObjectMother;
use ARO\KafkaMessenger\Transport\Configuration\Configuration;
use ARO\KafkaMessenger\Transport\Configuration\ConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfigurationValidator;
use ARO\KafkaMessenger\Transport\Configuration\Dsn\DsnConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Dsn\DsnConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\JsonSerialization\JsonSerializationConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfigurationValidator;
use ARO\KafkaMessenger\Transport\KafkaOptionList;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConfigurationBuilder::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(KafkaOptionList::class)]
#[CoversClass(ProducerConfigurationBuilder::class)]
#[CoversClass(DsnConfiguration::class)]
#[CoversClass(DsnConfigurationBuilder::class)]
#[CoversClass(ProducerConfigurationValidator::class)]
#[CoversClass(ConsumerConfigurationBuilder::class)]
#[CoversClass(JsonSerializationConfiguration::class)]
#[CoversClass(ProducerConfiguration::class)]
#[CoversClass(ConsumerConfiguration::class)]
#[CoversClass(ConsumerConfigurationValidator::class)]
#[CoversClass(ProducerConfigurationValidator::class)]
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

    // Producer Option Type Validation Tests

    public function testBuildThrowsExceptionForInvalidProducerPollTimeoutType(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidProducerPollTimeout();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "poll_timeout_ms" option type must be integer, "string"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidProducerFlushTimeoutType(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidProducerFlushTimeout();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "flush_timeout_ms" option type must be integer, "string"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidProducerTopicsType(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidProducerTopicsType();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "topics" option type must be array, "string"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidProducerRouting(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidProducerRouting();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each "routing" entry must contain "name" and "topic"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForNonExistentProducerClass(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithNonExistentProducerClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "NonExistentClass" specified in "routing" does not exist');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidConsumerCommitAsyncType(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidConsumerCommitAsync();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "commit_async" option type must be boolean, "string"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidConsumerTimeoutType(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidConsumerTimeout();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "consume_timeout_ms" option type must be integer, "string"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidConsumerTopicsType(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidConsumerTopicsType();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "topics" option type must be array, "string"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForInvalidConsumerRouting(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithInvalidConsumerRouting();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each "routing" entry must contain "name" and "class"');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForNonExistentConsumerClass(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithNonExistentConsumerClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "NonExistentClass" specified in "routing" does not exist');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }

    public function testBuildThrowsExceptionForMissingRequiredConsumerConfig(): void
    {
        $dsn = 'ed+kafka://localhost:9092/test_transport';
        $globalOptions = GlobalOptionsObjectMother::createEmpty();
        $transportOptions = TransportOptionsObjectMother::createWithMissingRequiredConsumerConfig();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The config(s) "group.id" are required');

        $this->configurationBuilder->build($dsn, $globalOptions, $transportOptions);
    }
}
