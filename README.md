# Symfony Kafka Messenger Transport

A custom transport for Symfony Messenger specifically designed to work with Apache Kafka as an event streaming platform, not just as a traditional message queue.

## Why another Kafka transport?

Existing packages for Kafka + Symfony Messenger are often outdated or don't cover advanced event streaming use cases. This transport is designed for:

- **Event Streaming**: Optimized for real-time event flows, not just message queuing
- **Flexibility**: Granular configuration for producers and consumers
- **Simplicity**: Automatic JSON serialization without additional configuration
- **Multi-topic**: Produce to multiple topics with a single configuration

## Key Features

### Multi-topic with single configuration
Produce the same message to different topics simultaneously, ideal for distributed event architectures.

### 📋 Global and specific configuration
Define base configurations for all transports and customize each one according to specific needs.

### Flexible event flows
A transport can consume from one topic and produce to a completely different one, enabling complex processing pipelines.

### Automatic JSON serialization
Without needing to create custom serializers, automatically handles conversion between PHP objects and JSON.

### Advanced Stamp system
Granular control over metadata, partition keys, and custom headers.

### Event Streaming with multiple message types
Allows multiple message types in the same topic, using the routing system to selectively process only the events you need. Messages not included in routing are automatically committed, enabling complex event streaming scenarios.

## Installation

```bash
composer require your-username/symfony-kafka-messenger-transport
```

## Basic Mode vs Advanced Mode

This transport can work in two different modes depending on your needs:

### Basic Mode (PHP serialization by default)

By default, the transport works like any other Symfony Messenger transport, using standard PHP serialization:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN)%'
        options:
          topics: ['test_topic']
          json_serialization:
            enabled: false  # Default
          consumer:
            config:
              group.id: '%env(APP_ENV)%-app-test-events'
    routing:
      'App\Message\TestMessage': kafka_events
```

In this mode:
- **Standard serialization**: Messages are serialized using Symfony's PHP serializer
- **Simple routing**: Uses Messenger's traditional routing system
- **One type per transport**: Each transport typically handles a single message type
- **Less flexibility**: You can't selectively filter messages by topic

### Advanced Mode (Event Streaming)

By enabling JSON serialization and custom routing, you unlock all advanced features:

```yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN)%'
        options:
          topics: ['test_topic']
          json_serialization:
            enabled: true  # Enables advanced mode
          consumer:
            routing:
              - name: 'test_message'
                class: 'App\Message\TestMessage'
            config:
              group.id: '%env(APP_ENV)%-app-test-events'
```

In this mode you get:
- **Automatic JSON serialization**: Interoperability with other systems
- **Multiple types per topic**: A topic can contain different event types
- **Selective filtering**: Process only the events you configure in routing
- **Real event streaming**: True heterogeneous event streams
- **Custom headers**: Full control over Kafka metadata
- **Multi-topic**: Produce to multiple topics simultaneously

### When to use each mode?

**Use basic mode when**:
- You only need a simple message queue
- You work exclusively with PHP applications
- You don't need interoperability with other systems
- You want maximum compatibility with the Symfony ecosystem

**Use advanced mode when**:
- You implement event streaming architectures
- You need interoperability with non-PHP systems
- You want multiple event types in the same topic
- You need selective message filtering
- You work with distributed microservices

## Configuration

### Global Configuration

Create a global configuration file to define base configurations that will apply to all transports:

```yaml
# config/packages/kafka_messenger.yaml
kafka_messenger:
  consumer:
    commit_async: true
    consume_timeout_ms: 500
    config:
      group.id: 'rms-api'
      security.protocol: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SECURITY_PROTOCOL)%"
      sasl.mechanisms: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SASL_MECHANISMS)%"
      sasl.username: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SASL_USERNAME)%"
      sasl.password: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SASL_PASSWORD)%"
      allow.auto.create.topics: 'true'
      auto.offset.reset: 'earliest'
      enable.partition.eof: 'true'
      queued.max.messages.kbytes: '10000' # 10MB
      partition.assignment.strategy: 'roundrobin'
      max.poll.interval.ms: '600000' # 10 minutes
      socket.keepalive.enable: 'true'
      fetch.max.bytes: '104857600'
      max.partition.fetch.bytes: '52428800'
      topic.metadata.refresh.interval.ms: '60000'
  producer:
    config:
      security.protocol: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SECURITY_PROTOCOL)%"
      sasl.mechanisms: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SASL_MECHANISMS)%"
      sasl.username: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SASL_USERNAME)%"
      sasl.password: "%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_SASL_PASSWORD)%"
      allow.auto.create.topics: 'true'
      enable.idempotence: 'false'
      max.in.flight.requests.per.connection: '1000000'
```

### Per-Transport Configuration

Each transport can override global configurations according to its specific needs:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN)%'
        options:
          topics: ['user_events']
          json_serialization:
            enabled: true
          consumer:
            routing:
              - name: 'user_registered'
                class: 'App\Message\UserRegistered'
            config:
              group.id: '%env(APP_ENV)%-app-user-events'  # Overrides global group.id
```

**Important about group.id**: In Kafka, the `group.id` determines which consumers belong to the same group. Consumers in the same group share the partitions of topics, but each message is only processed by one consumer in the group. It's crucial to use a specific `group.id` for each use case to prevent different services from interfering with each other.

### Multi-topic Configuration

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN)%'
        options:
          topics: ['user_events', 'audit_events', 'notification_events']
          json_serialization:
            enabled: true
          consumer:
            routing:
              - name: 'user_registered'
                class: 'App\Message\UserRegistered'
              - name: 'user_updated'
                class: 'App\Message\UserUpdated'
            config:
              group.id: '%env(APP_ENV)%-app-events'
```

## Automatic JSON Serialization

### How does it work?

1. **When producing**: Your PHP object is automatically serialized to JSON
2. **When consuming**: The JSON is automatically deserialized to the correct PHP object using the routing system

### Routing Configuration

Routing determines which PHP class to use for deserializing each message type:

```yaml
consumer:
  routing:
    - name: 'user_registered'      # Message identifier in Kafka
      class: 'App\Message\UserRegistered'  # Target PHP class
    - name: 'order_created'
      class: 'App\Message\OrderCreated'
```

## Advanced Event Streaming

### Multiple message types in a topic

One of the most powerful features of this transport is the ability to handle multiple message types within the same topic, creating true event streams.

#### How does selective filtering work?

When you have a topic with multiple event types (for example: `user_registered`, `user_updated`, `user_deleted`) and you only want to process some of them, you can configure routing to include only the events you're interested in:

```yaml
# Topic with multiple event types: user_events
kafka_user_processor:
  dsn: '%env(KAFKA_DSN)%'
  options:
    topics: ['user_events']  # Topic containing multiple types
    json_serialization:
      enabled: true
    consumer:
      routing:
        # Only process registrations and updates, ignore deletions
        - name: 'user_registered'
          class: 'App\Message\UserRegistered'
        - name: 'user_updated'
          class: 'App\Message\UserUpdated'
        # user_deleted is not in routing, automatically committed
      config:
        group.id: 'user-processor-service'
```

#### Advantages of this approach

**Selective processing**: The transport automatically commits (marks as processed) all messages that don't match any configured routing. This means:

- `user_deleted` messages are skipped without processing
- They don't accumulate in the queue
- They don't affect consumer performance
- They allow other services to process them if needed

**Multiple specialized consumers**: Different services can consume the same topic but process different subsets of events:

```yaml
# Notification service - only processes registrations
notification_service:
  options:
    topics: ['user_events']
    consumer:
      routing:
        - name: 'user_registered'
          class: 'App\Message\UserRegistered'
      config:
        group.id: 'notification-service'

# Audit service - processes all events
audit_service:
  options:
    topics: ['user_events']
    consumer:
      routing:
        - name: 'user_registered'
          class: 'App\Message\UserRegistered'
        - name: 'user_updated'
          class: 'App\Message\UserUpdated'
        - name: 'user_deleted'
          class: 'App\Message\UserDeleted'
      config:
        group.id: 'audit-service'
```

#### Typical use cases

**Event Sourcing**: A topic can contain the entire event history of an aggregate, and different services can reconstruct state by processing only the events they need.

**Specialized microservices**: Each service subscribes to the same stream but processes only events relevant to its domain.

**Processing pipelines**: Create processing chains where each stage consumes certain events and produces others.

## Transport Hooks and Stamps

The hook system allows you to intercept and modify messages at key points in processing, and it's where you'll typically add the Stamps that control Kafka behavior. This is **application-specific logic** that you need to customize based on your message types and business requirements.

### How it works

Simply implement the `KafkaTransportHookInterface` and the transport will automatically detect your implementation. This interface provides four key methods that are called at different stages of message processing:

```php
<?php

namespace App\Transport\Hook;

use App\Message\UserRegistered;
use App\Message\OrderCreated;
use App\Transport\Hook\KafkaTransportHookInterface;
use App\Transport\Stamp\KafkaIdentifierStamp;
use App\Transport\Stamp\KafkaKeyStamp;
use App\Transport\Stamp\KafkaCustomHeadersStamp;
use Symfony\Component\Messenger\Envelope;
use RdKafka\Message;

class EventStreamingHook implements KafkaTransportHookInterface
{
    /**
     * Called before producing a message to Kafka
     * This is where you add Stamps to control Kafka behavior
     */
    public function beforeProduce(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        $stamps = [];
        
        // Add KafkaIdentifierStamp for routing
        switch (true) {
            case $message instanceof UserRegistered:
                $stamps[] = new KafkaIdentifierStamp('user_registered');
                $stamps[] = new KafkaKeyStamp($message->getUserId());
                break;
                
            case $message instanceof OrderCreated:
                $stamps[] = new KafkaIdentifierStamp('order_created');
                $stamps[] = new KafkaKeyStamp($message->getCustomerId());
                // Add custom headers
                $stamps[] = new KafkaCustomHeadersStamp([
                    'source_system' => 'order-service',
                    'tenant_id' => $message->getTenantId()
                ]);
                break;
        }
        
        return $envelope->with(...$stamps);
    }
    
    /**
     * Called after successfully producing a message to Kafka
     * Useful for logging, metrics, notifications, etc.
     */
    public function afterProduce(Envelope $envelope): void
    {
        $this->logger->info('Message produced to Kafka', [
            'message_type' => get_class($envelope->getMessage()),
            'topic' => $this->getTopicFromEnvelope($envelope)
        ]);
        
        $this->metrics->increment('kafka.message.produced');
    }
    
    /**
     * Called before consuming a message from Kafka
     * Useful for message validation, transformation, etc.
     */
    public function beforeConsume(Message $message): Message
    {
        // Example: Validate message format
        if (!$this->isValidMessage($message)) {
            throw new \InvalidArgumentException('Invalid message format');
        }
        
        // Example: Transform message before processing
        return $this->transformMessage($message);
    }
    
    /**
     * Called after successfully consuming and processing a message
     * Useful for cleanup, final logging, etc.
     */
    public function afterConsume(Envelope $envelope): void
    {
    }
}
```

### Available Stamps

These are the Stamps you can add in the `beforeProduce` method to control Kafka behavior:

#### KafkaIdentifierStamp
**Purpose**: Identifies the message type for consumption routing (required for JSON serialization mode).

```php
$stamps[] = new KafkaIdentifierStamp('user_registered');
```

#### KafkaKeyStamp
**Purpose**: Defines the partition key to guarantee message ordering within partitions.

```php
// Messages with the same key go to the same partition
$stamps[] = new KafkaKeyStamp($user->getId());
```

#### KafkaCustomHeadersStamp
**Purpose**: Adds custom headers to messages for metadata, tracing, etc.

```php
$stamps[] = new KafkaCustomHeadersStamp([
    'source_system' => 'user-service',
    'correlation_id' => $correlationId,
    'tenant_id' => $tenantId
]);
```

### Important Notes

- **Application-specific**: The hook implementation is completely up to you and depends on your message types and business logic
- **Automatic detection**: No service configuration needed - just implement the interface
- **Stamp timing**: Stamps must be added in `beforeProduce` - they won't work in other methods
- **Error handling**: You can throw exceptions in any hook method to halt processing

## Common Use Cases

### 1. Event Sourcing
```yaml
kafka_events:
  dsn: '%env(KAFKA_DSN)%'
  options:
    topics: ['domain_events']
    json_serialization:
      enabled: true
    consumer:
      routing:
        - name: 'user_registered'
          class: 'App\Event\UserRegistered'
        - name: 'user_updated'
          class: 'App\Event\UserUpdated'
```

### 2. Transformation Pipeline
```yaml
# Consume from one topic, process and produce to another
data_pipeline:
  dsn: '%env(KAFKA_DSN)%'
  options:
    topics: ['processed_data']  # Output topic
    json_serialization:
      enabled: true
    consumer:
      topics: ['raw_data']      # Input topic (different)
      routing:
        - name: 'raw_event'
          class: 'App\Message\RawEvent'
```

### 3. Multi-tenancy
```php
public function beforeProduce(Envelope $envelope): Envelope
{
    $message = $envelope->getMessage();
    
    if ($message instanceof TenantAwareMessage) {
        $stamps = [
            new KafkaKeyStamp($message->getTenantId()),
            new KafkaCustomHeadersStamp([
                'tenant_id' => $message->getTenantId()
            ])
        ];
        
        return $envelope->with(...$stamps);
    }
    
    return $envelope;
}
```

## Best Practices

### 1. Consistent Message Identifiers
```php
// ❌ Avoid
$stamps[] = new KafkaIdentifierStamp('userReg');
$stamps[] = new KafkaIdentifierStamp('user-registered');

// ✅ Recommended
$stamps[] = new KafkaIdentifierStamp('user_registered');
```

### 2. Effective Partition Keys
```php
// ✅ For user events, use user ID
$stamps[] = new KafkaKeyStamp($user->getId());

// ✅ For order events, use customer ID
$stamps[] = new KafkaKeyStamp($order->getCustomerId());
```

### 3. Specific Group IDs
```yaml
# ❌ Avoid using the same group.id for different services
consumer:
  config:
    group.id: 'app'  # Too generic

# ✅ Use specific group.ids per functionality
consumer:
  config:
    group.id: '%env(APP_ENV)%-user-service-events'  # Specific and with environment
```

### 4. Centralized Routing
```php
class MessageRouter
{
    private const MESSAGE_TYPES = [
        UserRegistered::class => 'user_registered',
        UserUpdated::class => 'user_updated',
        OrderCreated::class => 'order_created',
    ];
    
    public function getIdentifier(object $message): ?string
    {
        return self::MESSAGE_TYPES[get_class($message)] ?? null;
    }
}
```

### 5. Error Handling
```php
public function afterConsume(Envelope $envelope): void
{
    $stamps = $envelope->all();
    
    if (isset($stamps[ErrorStamp::class])) {
        $this->handleFailedMessage($envelope);
    }
}
```

## Environment Variables

```bash
# .env
KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN=kafka://localhost:9092
APP_ENV=dev
```

## Advanced Configuration

The global configuration defined in `kafka_messenger.yaml` can be completely overridden in each specific transport. This allows you to have common base configurations and specific customizations according to the needs of each event flow.

### Transport Override Example
```yaml
# Override producer configurations for a specific transport
kafka_events:
  dsn: '%env(KAFKA_DSN)%'
  options:
    topics: ['critical_events']
    producer:
      config:
        acks: 'all'          # Overrides global configuration
        retries: 5           # More retries for critical events
        batch.size: 1        # Immediate sending for critical events
    consumer:
      config:
        group.id: '%env(APP_ENV)%-critical-events'  # Specific group ID
        auto.offset.reset: 'latest'                 # Only new messages
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/new-feature`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/new-feature`)
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.