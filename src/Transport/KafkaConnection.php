<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport;

use Exception;
use ARO\KafkaMessenger\Transport\Serializer\MessageSerializer;
use ARO\KafkaMessenger\Transport\Setting\GeneralSetting;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Messenger\Exception\TransportException;

class KafkaConnection
{
    private bool $consumerSubscribed = false;
    private bool $consumerMustBeRunning = false;
    private const WILDCARD_ROUTING = '*';
    private ?KafkaConsumer $consumer;
    private ?Producer $producer;

    private SignalRegistry $signalRegistry;

    public function __construct(
        private readonly GeneralSetting      $generalSetting,
    ) {
        $this->signalRegistry = new SignalRegistry();
    }


    public function get(
        array $topicsToFilter,
    ): ?iterable {
        $consumer = $this->getConsumer();

        if (!$this->consumerSubscribed) {
            $consumer->subscribe(!empty($topicsToFilter) ? $topicsToFilter : $this->generalSetting->consumer->topics);
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
                    $messageIdentifier = $kafkaMessage->headers[MessageSerializer::identifierHeaderKey()] ?? null;
                    $forceAckByRoutingMap = false;

                    $messageFoundInRouting = false;
                    foreach ($this->generalSetting->consumer->routing as $name => $class) {
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
                    break;
            }
        }
    }


    public function ack(Message $message): void
    {
        $consumer = $this->getConsumer();

        if ($this->generalSetting->consumer->commitAsync) {
            $consumer->commitAsync($message);
        } else {
            $consumer->commit($message);
        }
    }

    /**
     * @param array<string, string> $headers
     * @throws \RdKafka\Exception
     */
    public function produce(
        int      $partition,
        int      $messageFlags,
        string   $body,
        ?string   $key = null,
        array    $headers = [],
        bool     $forceFlush = true,
        string   $identifier = null,
        callable $beforeProduceConvertBody = null,
    ): void {
        $producer = $this->getProducer();
        $topicFromRouting = $this->generalSetting->producer->routing[$identifier] ?? null;

        foreach ($this->generalSetting->producer->topics as $topic) {
            if ($topicFromRouting && $topic != $topicFromRouting) {
                continue;
            }

            if ($beforeProduceConvertBody) {
                $body = $beforeProduceConvertBody($topic);
            }

            $topic = $producer->newTopic($topic);
            $topic->producev(
                $partition,
                $messageFlags,
                $body,
                $key,
                $headers,
            );

            $producer->poll($this->generalSetting->producer->flushTimeoutMs);
        }

        if ($forceFlush) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        for ($flushRetries = 0; $flushRetries < 10; ++$flushRetries) {
            $result = $this->getProducer()->flush($this->generalSetting->producer->flushTimeoutMs);

            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            throw new TransportException('Was unable to flush, messages might be lost!: '.$result, $result);
        }
    }

    private function getConsumer(): KafkaConsumer
    {
        return $this->consumer ??= $this->createConsumer($this->generalSetting->consumer->config);
    }

    private function getProducer(): Producer
    {
        return $this->producer ??= $this->createProducer($this->generalSetting->producer->config);
    }

    private function getBaseConf(): Conf
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->generalSetting->host);
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
