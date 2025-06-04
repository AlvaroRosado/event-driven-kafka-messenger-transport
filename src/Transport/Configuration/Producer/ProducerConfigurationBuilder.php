<?php

namespace ARO\KafkaMessenger\Transport\Configuration\Producer;

final class ProducerConfigurationBuilder
{
    public function build(array $options, ?array $globalTopics = []): ProducerConfiguration
    {
        $validator = new ProducerConfigurationValidator();
        $producerOptions = $validator->validate($options);

        return new ProducerConfiguration(
            routing: $this->buildRouting($producerOptions['routing'] ?? []),
            config: $producerOptions['config'] ?? [],
            topics: empty($consumerOptions['topics']) ? $globalTopics : [],
            pollTimeoutMs: $producerOptions['poll_timeout_ms'] ?? 1000,
            flushTimeoutMs: $producerOptions['flush_timeout_ms'] ?? 1000,
        );
    }

    private function buildRouting(array $routingConfig): array
    {
        return array_column($routingConfig, 'topic', 'name');
    }
}
