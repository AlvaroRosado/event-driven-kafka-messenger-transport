<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\Transport\Configuration\ConfigurationBuilder;
use ARO\KafkaMessenger\Transport\JsonSerializer\JsonSerializer;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class KafkaTransportFactory implements TransportFactoryInterface
{
    private ConfigurationBuilder $configuration;
    private ?array $globalConfig;
    private ?KafkaTransportHookInterface $hook;

    public function __construct(
        ConfigurationBuilder         $configuration,
        ?KafkaTransportHookInterface $metadata = null,
        ?array                       $globalConfig = null,
    ) {
        $this->configuration = $configuration;
        $this->globalConfig = $globalConfig;
        $this->hook = $metadata;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $options = $this->configuration->build($dsn, $this->globalConfig, $options);

        $jsonSerializer = new JsonSerializer(
            routingMap: $options->getConsumer()->routing,
        );

        $customSerializer = $serializer;

        if ($options->isJsonSerializationEnabled()) {
            $customSerializer = $jsonSerializer;
        }

        $connection = new KafkaConnection(
            configuration: $options,
            hook: $this->hook,
        );

        return new KafkaTransport(
            sender: new KafkaTransportSender(
                connection: $connection,
                hook: $this->hook,
                serializer: $customSerializer,
            ),
            receiver: new KafkaTransportReceiver(
                connection: $connection,
                hook: $this->hook,
                serializer: $customSerializer,
            )
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'ed+kafka://');
    }
}
