<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\DependencyInjection\CompilerPass;

use ARO\KafkaMessenger\Transport\KafkaTransportFactory;
use ARO\KafkaMessenger\Transport\Metadata\KafkaMetadataHookInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class KafkaCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(KafkaMetadataHookInterface::class) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $kafkaTransportDefinition = $container->getDefinition(KafkaTransportFactory::class);
            $kafkaTransportDefinition->replaceArgument(2, $definition);
            return;
        }
    }
}
