<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\Transport\Configuration\ConfigurationBuilder;
use ARO\KafkaMessenger\Transport\JsonSerializer\JsonSerializer;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

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

        $customSerializer = $serializer;

        if ($options->isJsonSerializationEnabled()) {
            $symfonySerializer = null;

            if ($options->customSymfonySerializer()) {
                $symfonySerializer = $options->customSymfonySerializer();
            }

            if ($symfonySerializer &&
                !is_subclass_of($symfonySerializer, SymfonySerializer::class)
            ) {
                throw new \InvalidArgumentException(sprintf(
                    'The custom serializer "%s" must be a subclass of "%s".',
                    $symfonySerializer,
                    SymfonySerializer::class
                ));
            }

            $jsonSerializer = new JsonSerializer(
                routingMap: $options->getConsumer()->routing,
                serializer: $symfonySerializer ? new $symfonySerializer() : null
            );

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
