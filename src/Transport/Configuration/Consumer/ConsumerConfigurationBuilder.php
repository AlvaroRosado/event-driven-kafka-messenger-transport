<?php

namespace ARO\KafkaMessenger\Transport\Configuration\Consumer;


final class ConsumerConfigurationBuilder
{
    public function build(array $options, ?array $globalTopics = []): ConsumerConfiguration
    {
        $validator = new ConsumerConfigurationValidator();
        $consumerOptions = $validator->validate($options);

        return new ConsumerConfiguration(
            routing: $this->buildRouting($consumerOptions['routing'] ?? []),
            config: $consumerOptions['config'] ?? [],
            topics: empty($consumerOptions['topics']) ? $globalTopics : [],
            consumeTimeout: $consumerOptions['consume_timeout_ms'] ?? 1000,
            commitAsync: $consumerOptions['commit_async'] ?? true,
        );
    }

    private function buildRouting(array $routingConfig): array
    {
        return array_column($routingConfig, 'class', 'name');
    }
}