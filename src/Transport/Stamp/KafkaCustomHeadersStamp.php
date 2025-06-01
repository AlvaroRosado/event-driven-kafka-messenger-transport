<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class KafkaCustomHeadersStamp implements StampInterface
{
    private array $headers;

    public function __construct(array $headers = [])
    {
        $this->headers = $headers;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function withHeader(string $key, string $value): self
    {
        $new = clone $this;
        $new->headers[$key] = $value;

        return $new;
    }
}
