<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger;

use ARO\KafkaMessenger\DependencyInjection\CompilerPass\KafkaCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EventDrivenKafkaTransportBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new KafkaCompilerPass());
    }
}