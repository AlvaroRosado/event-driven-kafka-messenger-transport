<?php

namespace ARO\KafkaMessenger\Transport\Configuration\Dsn;

final class DsnConfigurationBuilder
{
    public function build(string $dsn): DsnConfiguration
    {
        $parsedUrl = parse_url($dsn);

        if (false === $parsedUrl || !isset($parsedUrl['host'], $parsedUrl['scheme'])) {
            throw new \InvalidArgumentException(sprintf('Invalid Kafka DSN: "%s"', $dsn));
        }

        if ('ed+kafka' !== $parsedUrl['scheme']) {
            throw new \InvalidArgumentException(sprintf('Kafka DSN must start with "ed+kafka://": "%s"', $dsn));
        }

        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? 9092; // Default Kafka port
        $fullHost = $host . ':' . $port;

        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        $securityConfig = $this->extractSecurityConfig($queryParams);

        return new DsnConfiguration(
            host: $fullHost,
            transportName: ltrim($parsedUrl['path'] ?? 'kafka', '/'),
            securityConfig: $securityConfig,
        );
    }

    private function extractSecurityConfig(array $queryParams): array
    {
        $parameterMap = [
            'security.protocol' => ['security_protocol', 'protocol', 'sec_protocol'],
            'sasl.mechanisms' => ['sasl_mechanisms'],
            'sasl.username' => ['username', 'user', 'sasl_username'],
            'sasl.password' => ['password', 'pass', 'sasl_password']
        ];

        $defaultConfig = [];

        foreach ($parameterMap as $configKey => $possibleKeys) {
            foreach ($possibleKeys as $key) {
                if (isset($queryParams[$key])) {
                    $defaultConfig[$configKey] = $queryParams[$key];
                    break;
                }
            }
        }

        return $defaultConfig;
    }
}
