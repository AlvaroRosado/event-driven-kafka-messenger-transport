<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\Transport\Configuration\Configuration;
use ARO\KafkaMessenger\Transport\JsonSerializer\HeaderSerializer;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use ARO\KafkaMessenger\Transport\Stamp\KafkaKeyStamp;
use ARO\KafkaMessenger\Transport\Stamp\KafkaMessageStamp;
use Exception;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaConnection
{
    private bool $consumerSubscribed = false;
    private bool $consumerMustBeRunning = false;
    private const WILDCARD_ROUTING = '*';
    private ?KafkaConsumer $consumer;
    private ?Producer $producer;
    private Configuration $configuration;
    private SignalRegistry $signalRegistry;
    private ?KafkaTransportHookInterface $hook;
    private SerializerInterface $serializer;

    public function __construct(
        Configuration                $configuration,
        ?SerializerInterface   $serializer,
        ?KafkaTransportHookInterface $hook = null,
    ) {
        $this->configuration = $configuration;
        $this->signalRegistry = new SignalRegistry();
        $this->hook = $hook;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(
        array $topicsToFilter,
    ): ?iterable {
        $consumer = $this->getConsumer();

        if (!$this->consumerSubscribed) {
            $consumer->subscribe(!empty($topicsToFilter) ? $topicsToFilter : $this->configuration->getConsumer()->topics);
            $this->consumerSubscribed = true;
        }
        $this->consumerMustBeRunning = true;

        yield from $this->doReceive(
            timeout: $this->generalSetting->consumer->consumeTimeout ?? 500
        );
    }

    private function doReceive(
        int $timeout,
    ): iterable {
        $this->signalRegistry->register(SIGINT, fn () => $this->consumerMustBeRunning = false);
        $this->signalRegistry->register(SIGTERM, fn () => $this->consumerMustBeRunning = false);

        while ($this->consumerMustBeRunning) {
            $kafkaMessage = $this->getConsumer()->consume($timeout);

            if (null === $kafkaMessage) {
                yield null;
            }

            switch ($kafkaMessage->err) {
                case \RD_KAFKA_RESP_ERR_NO_ERROR:
                    if ($this->hook) {
                        $kafkaMessage = $this->hook->beforeConsume($kafkaMessage);
                    }

                    $messageIdentifier = $kafkaMessage->headers[HeaderSerializer::identifierHeaderKey()] ?? null;


                    if (!$messageIdentifier && !$this->configuration->isJsonSerializationEnabled()) {
                        yield $kafkaMessage;
                        break;
                    }

                    $forceAckByRoutingMap = false;
                    $messageFoundInRouting = false;

                    foreach ($this->configuration->getConsumer()->routing as $name => $class) {
                        if ($name == self::WILDCARD_ROUTING) {
                            $messageFoundInRouting = true;
                            break;
                        }

                        if (is_null($messageIdentifier)) {
                            break;
                        }

                        if ($name == $messageIdentifier) {
                            $messageFoundInRouting = true;
                            break;
                        }
                    }

                    if (!$messageFoundInRouting) {
                        $forceAckByRoutingMap = true;
                    }

                    if ($forceAckByRoutingMap) {
                        $this->ack($kafkaMessage);
                        break;
                    }

                    yield $kafkaMessage;
                    // no break
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                case RD_KAFKA_RESP_ERR__TRANSPORT:
                case RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART:
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    yield null;
                    // no break
                default:
                    throw new \LogicException($kafkaMessage->errstr(), $kafkaMessage->err);
            }
        }
    }


    public function ack(Message $message): void
    {
        $consumer = $this->getConsumer();

        if ($this->configuration->getConsumer()->commitAsync) {
            $consumer->commitAsync($message);
        } else {
            $consumer->commit($message);
        }
    }

    public function flush(): void
    {
        for ($flushRetries = 0; $flushRetries < 10; ++$flushRetries) {
            $result = $this->getProducer()->flush($this->configuration->getProducer()->flushTimeoutMs);

            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            throw new TransportException('Was unable to flush, messages might be lost!: '.$result, $result);
        }
    }

    public function produce(Envelope $envelope): void
    {
        if ($this->hook) {
            $envelope = $this->hook->beforeProduce($envelope);
        }
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $decodedEnvelope = $this->serializer->encode($envelope);

        [$partition, $messageFlags, $key] = $this->extractMessageMetadata($envelope);
        $headers = $decodedEnvelope['headers'] ?? [];
        $body = $decodedEnvelope['body'];

        $topicFromRouting = $this->resolveRoutingTopic($headers);

        $producer = $this->getProducer();

        try {

            foreach ($this->configuration->getProducer()->topics as $topicName) {
                if ($topicFromRouting && $topicName !== $topicFromRouting) {
                    continue;
                }

                $this->sendMessage($producer, $topicName, $partition, $messageFlags, $body, $key, $headers);
            }

            $this->flush();
        } catch (Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        $this->hook?->afterProduce($envelope);
    }

    private function extractMessageMetadata(Envelope $envelope): array
    {
        $partition = \RD_KAFKA_PARTITION_UA;
        $messageFlags = \RD_KAFKA_CONF_OK;
        $key = null;

        if ($messageStamp = $envelope->last(KafkaMessageStamp::class)) {
            $partition = $messageStamp->partition ?? $partition;
            $messageFlags = $messageStamp->messageFlags ?? $messageFlags;
            $key = $messageStamp->key ?? null;
        }

        if ($keyStamp = $envelope->last(KafkaKeyStamp::class)) {
            $key = $keyStamp->key;
        }

        return [$partition, $messageFlags, $key];
    }

    private function resolveRoutingTopic(array $headers): ?string
    {
        if (!$this->configuration->isJsonSerializationEnabled()) {
            return null;
        }

        $identifierKey = HeaderSerializer::identifierHeaderKey();

        if (!isset($headers[$identifierKey])) {
            throw new \RuntimeException('Identifier stamp has not been set. This is required for JSON serialization.');
        }

        $identifier = $headers[$identifierKey];

        return $this->configuration->getProducer()->routing[$identifier] ?? null;
    }

    private function sendMessage(
        Producer $producer,
        string $topicName,
        int $partition,
        int $messageFlags,
        string $body,
        ?string $key,
        array $headers
    ): void {
        $topic = $producer->newTopic($topicName);
        $topic->producev($partition, $messageFlags, $body, $key, $headers);
        $producer->poll($this->configuration->getProducer()->flushTimeoutMs);
    }

    private function getConsumer(): KafkaConsumer
    {
        return $this->consumer ??= $this->createConsumer($this->configuration->getConsumer()->config);
    }

    private function getProducer(): Producer
    {
        return $this->producer ??= $this->createProducer($this->configuration->getProducer()->config);
    }

    private function getBaseConf(): Conf
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->configuration->getHost());
        foreach ($this->configuration->getSecurityConfig() as $key => $value) {
            $conf->set($key, $value);
        }
        return $conf;
    }

    private function createConsumer(array $kafkaConfig): KafkaConsumer
    {
        $conf = $this->getBaseConf();
        $conf->setRebalanceCb(function (KafkaConsumer $kafka, $err, ?array $partitions = null) {
            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    $kafka->assign($partitions);

                    break;

                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    $kafka->assign(null);

                    break;

                default:
                    throw new Exception($err);
            }
        });

        foreach ($kafkaConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return new KafkaConsumer($conf);
    }

    /**
     * @param array<string, string> $kafkaConfig
     */
    private function createProducer(array $kafkaConfig): Producer
    {
        $conf = $this->getBaseConf();

        foreach ($kafkaConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return new Producer($conf);
    }
}
