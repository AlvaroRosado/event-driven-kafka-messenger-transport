<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\SchemaRegistry\SchemaRegistryManager;
use ARO\KafkaMessenger\Transport\Metadata\KafkaMetadataHookInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use ARO\KafkaMessenger\Transport\Serializer\MessageSerializer;

final readonly class KafkaTransportFactory implements TransportFactoryInterface
{
    private KafkaTransportSettingResolver $configuration;
    private ?array $globalConfig;
    private SchemaRegistryManager $schemaRegistryManager;
    private ?KafkaMetadataHookInterface $metadata;

    public function __construct(
        KafkaTransportSettingResolver $configuration,
        SchemaRegistryManager         $schemaRegistryManager,
        ?KafkaMetadataHookInterface   $metadata = null,
        ?array                        $globalConfig = null,
    ) {
        $this->configuration = $configuration;
        $this->globalConfig = $globalConfig;
        $this->schemaRegistryManager = $schemaRegistryManager;
        $this->metadata = $metadata;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $options = $this->configuration->resolve($dsn, $this->globalConfig, $options);

        $serializer = new MessageSerializer(
            staticMethodIdentifier: $options->staticMethodIdentifier,
            routingMap: $options->consumer->routing,
            serializer: ($options->serializer)
                ? new $options->serializer
                : null,
        );

        $connection = new KafkaConnection(
            generalSetting: $options,
        );

        return new KafkaTransport(
            sender: new KafkaTransportSender(
                connection: $connection,
                metadata: $this->metadata,
                serializer: $serializer,
                schemaRegistryManager: ($options->producer->validateSchema)
                    ? $this->schemaRegistryManager
                    : null,
            ),
            receiver: new KafkaTransportReceiver(
                connection: $connection,
                metadata: $this->metadata,
                serializer: $serializer,
                schemaRegistryManager: ($options->consumer->validateSchema)
                    ? $this->schemaRegistryManager
                    : null,
            )
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'kafka://');
    }
}
