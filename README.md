# Event Driven Kafka Messenger Transport

A custom transport for Symfony Messenger specifically designed to work with Apache Kafka as an event streaming platform.

## Why another Kafka transport?

Existing packages for Kafka + Symfony Messenger are outdated or don't cover advanced event streaming use cases.

This transport is designed for:

- **Event Streaming**: Optimized for real-time event flows
- **Flexibility**: Granular configuration for producers and consumers
- **Simplicity**: Automatic JSON serialization without additional configuration
- **Multi-topic**: Produce to multiple topics with a single configuration
- **Selective Consumption**: Consume specific event types from topics containing multiple event types (Design your topics by event streams is now possible!)

## Installation

```bash
composer require alvarorosado/event-driven-kafka-messenger-transport
```

## Environment Variables

```bash
# .env
KAFKA_DSN=ed+kafka://localhost:9092
```

## Optional Security Parameters in DSN

```bash
# With SASL authentication
KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN=ed+kafka://localhost:9092?security_protocol=SASL_PLAINTEXT&username=myuser&password=mypass&sasl_mechanisms=PLAIN

# With SSL/TLS
KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN=ed+kafka://localhost:9092?security_protocol=SSL

# Without authentication (default)
KAFKA_EVENTS_MESSENGER_TRANSPORT_DSN=ed+kafka://localhost:9092
```

## Why `ed+kafka://` instead of `kafka://`?

The `ed+kafka://` DSN prefix allows this transport to coexist with other Kafka packages in the same project. This enables gradual migration and safe testing without conflicts - you can keep your existing Kafka transport while evaluating this one.

## Configuration File

Create the global configuration file for Kafka settings:

```yaml
# config/packages/event_drive_kafka_transport.yaml
event_driven_kafka_transport:
  consumer:
    commit_async: true
    consume_timeout_ms: 500
    config:
      group.id: 'default-group'
      auto.offset.reset: 'earliest'
  producer:
    config:
      enable.idempotence: 'false'
```

## Quick Start

### Basic Configuration
```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          topics: ['user_events']
          consumer:
            config:
              group.id: '%env(APP_ENV)%-app-events'
    routing:
      'App\Message\UserRegistered': kafka_events
```

*Works like any standard Symfony Messenger transport. Messages are serialized using PHP's native serialization and routed using Symfony's traditional routing system.*

### Advanced Configuration
```yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          topics: ['user_events']
          json_serialization:
            enabled: true  # Enables advanced mode
          consumer:
            routing:
              - name: 'user_registered'
                class: 'App\Message\UserRegistered'
              - name: 'user_updated'
                class: 'App\Message\UserUpdated'
            config:
              group.id: '%env(APP_ENV)%-app-events'
```

*When producing, messages are automatically serialized to JSON and sent to Kafka with the message body as JSON and Messenger metadata stored in Kafka headers. When consuming, the transport examines the message type and deserializes it to the corresponding PHP class based on the routing configuration.*

**⚠️ Important**: To use advanced mode, you **must implement the Hook interface** and define `KafkaIdentifierStamp` for each message type. This identifier is used as the JSON key for message type mapping during consumption. See the [Stamp System](#%EF%B8%8F-stamp-system) section below for complete implementation details.

### 🎯 Selective Event Streaming

Process only the events you need from a topic with multiple types:

```yaml
# Topic: user_events (contains: user_registered, user_updated, user_deleted)
consumer:
  routing:
    # Only process registrations and updates
    - name: 'user_registered'
      class: 'App\Message\UserRegistered'
    - name: 'user_updated'
      class: 'App\Message\UserUpdated'
    # user_deleted is automatically ignored
```

**Advantages:**
- Unconfigured messages are automatically committed
- They don't accumulate as lag
- Multiple services can process different subsets of the same topic

### 🏷️ Stamp System

Control Kafka behavior through Stamps in a custom Hook. **This Hook implementation is required for advanced mode** to properly handle JSON serialization and message routing.

**Recommended Pattern - Base Message Class:**
```php
abstract class Message
{
    abstract public function identifier(): string;
}

class UserRegistered extends BaseKafkaMessage
{
    public function identifier(): string
    {
        return 'user_registered';
    }
}
```

**Complete Hook Implementation:**
```php
<?php
namespace App\Transport\Hook;

use App\Transport\Hook\KafkaTransportHookInterface;
use App\Transport\Stamp\{KafkaIdentifierStamp, KafkaKeyStamp, KafkaCustomHeadersStamp};
use Symfony\Component\Messenger\Envelope;

class EventStreamingHook implements KafkaTransportHookInterface
{
    public function beforeProduce(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        $stamps = [];
        
        // Required for advanced mode: Add identifier for all Kafka messages
        if ($message instanceof Message) {
            $stamps[] = new KafkaIdentifierStamp($message->identifier());
        }
        
        // Optional: Add partition key for ordering
        if ($message instanceof UserRegistered) {
            $stamps[] = new KafkaKeyStamp($message->getUserId());
        }
        
        // Optional: Add custom headers
        if ($message instanceof TenantAwareMessage) {
            $stamps[] = new KafkaCustomHeadersStamp([
                'tenant_id' => $message->getTenantId()
            ]);
        }
        
        return $envelope->with(...$stamps);
    }
    
    public function afterProduce(Envelope $envelope): void
    {
        // Logging, metrics, etc.
    }
    
    public function beforeConsume(\RdKafka\Message $message): \RdKafka\Message
    {
        // Validation, transformation, etc.
        return $message;
    }
    
    public function afterConsume(Envelope $envelope): void
    {
        // Cleanup, final logging, etc.
    }
}
```

## JSON Serialization Details

### Custom Symfony Serializer Support (Optional)

The transport includes **automatic JSON serialization** that works out of the box without any additional configuration. However, if you need special serialization behavior (custom date formats, field transformations, etc.), you can optionally use a custom Symfony Serializer:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          topics: ['user_events']
          json_serialization:
            enabled: true  # This is all you need for most cases
            # custom_serializer: 'App\Serializer\CustomMessageSerializer'  # Optional
          consumer:
            routing:
              - name: 'user_registered'
                class: 'App\Message\UserRegistered'
```

**Requirements for Custom Serializer (when needed):**

1. **Must extend Symfony Serializer**: Your custom serializer class must be a subclass of `Symfony\Component\Serializer\Serializer`

2. **Must be instantiable**: The transport will instantiate your serializer class automatically, so it must have a constructor that can be called without parameters or with default parameters

3. **Example Implementation 
```php
<?php
namespace App\Serializer;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class CustomMessageSerializer extends Serializer
{
    public function __construct()
    {
        $normalizers = [
            new DateTimeNormalizer(), // Handle DateTime objects
            new ObjectNormalizer(),   // Handle general objects
        ];
        
        $encoders = [
            new JsonEncoder()
        ];
        
        parent::__construct($normalizers, $encoders);
    }
}
```

### Serializer Limitations

**✅ Recommended Configuration (works automatically):**

```yaml
# ✅ Default automatic serialization (recommended for most use cases)
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          json_serialization:
            enabled: true  # Automatic JSON serialization - no custom serializer needed

# ✅ Custom Symfony Serializer (only when you need special serialization logic)
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          json_serialization:
            enabled: true
            custom_serializer: 'App\Serializer\CustomMessageSerializer'  # Optional
```

**❌ Unsupported Configurations:**

```yaml
# ❌ This will still cause conflicts
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        serializer: 'my_custom_serializer'  # Transport-level serializer conflicts
        options:
          json_serialization:
            enabled: true
```

The transport's automatic JSON serialization works great for most use cases. Only use `custom_serializer` when you have specific serialization requirements that the default behavior doesn't cover. Avoid the transport-level `serializer` option when using advanced mode.

## Configuration

### 🎛️ Per-Transport Configuration

Each transport can override global configurations:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          topics: ['user_events', 'audit_events']  # Multi-topic
          json_serialization:
            enabled: true 
            # custom_serializer: 'App\Serializer\EventSerializer'  # Only if needed
          consumer:
            routing:
              - name: 'user_registered'
                class: 'App\Message\UserRegistered'
            config:
              group.id: '%env(APP_ENV)%-user-events'  # Overrides global
              auto.offset.reset: 'latest'             # Only new messages
```

## Coexistence with Other Kafka Transports

The `ed+kafka://` DSN allows you to use this transport alongside existing Kafka packages:

```yaml
framework:
  messenger:
    transports:
      # Existing Kafka transport
      legacy_kafka:
        dsn: 'kafka://localhost:9092'

      # This event-driven transport
      event_stream:
        dsn: 'ed+kafka://localhost:9092'  # Same broker, different transport
        options:
          topics: ['user_events']
          json_serialization:
            enabled: true

    routing:
      'App\Legacy\OrderCreated': legacy_kafka
      'App\Event\UserRegistered': event_stream
```

## Common Use Cases

### 1. Event Sourcing
```yaml
domain_events:
  options:
    topics: ['domain_events']
    json_serialization:
      enabled: true
    consumer:
      routing:
        - name: 'aggregate_created'
          class: 'App\Event\AggregateCreated'
        - name: 'aggregate_updated'
          class: 'App\Event\AggregateUpdated'
```

### 2. Transformation Pipeline
```yaml
# Consume from one topic, process and produce to another
data_pipeline:
  options:
    topics: ['processed_data']      # Output topic
    consumer:
      topics: ['raw_data']          # Input topic (different)
      routing:
        - name: 'raw_event'
          class: 'App\Message\RawEvent'
```

## Best Practices

### ✅ Consistent Naming
```php
// ❌ Avoid
new KafkaIdentifierStamp('userReg');
new KafkaIdentifierStamp('user-registered');

// ✅ Recommended
new KafkaIdentifierStamp('user_registered');
```

### ✅ Effective Partition Keys
```php
// For user events, use user ID
new KafkaKeyStamp($user->getId());

// For order events, use customer ID
new KafkaKeyStamp($order->getCustomerId());
```

### ✅ Specific Group IDs
```yaml
# ❌ Avoid generic IDs
group.id: 'app'

# ✅ Specific IDs with environment
group.id: '%env(APP_ENV)%-user-service-events'
```

## Available Stamps

| Stamp | Purpose | Example |
|---|---|---|
| `KafkaIdentifierStamp` | Identifies message type for routing | `new KafkaIdentifierStamp('user_registered')` |
| `KafkaKeyStamp` | Defines partition key | `new KafkaKeyStamp($userId)` |
| `KafkaCustomHeadersStamp` | Adds custom headers | `new KafkaCustomHeadersStamp(['tenant_id' => $id])` |

## Important Notes

### Hook System
- **Automatic detection**: Just implement `KafkaTransportHookInterface` - no service configuration needed
- **Application-specific**: Hook implementation depends on your message types and business logic
- **Stamp timing**: Stamps must be added in `beforeProduce` method
- **Error handling**: You can throw exceptions in any hook method to halt processing

### Group ID Strategy
In Kafka, `group.id` determines which consumers belong to the same group. Consumers in the same group share topic partitions, but each message is only processed by one consumer in the group. Use specific `group.id` for each use case to prevent different services from interfering with each other.

## Acknowledgments

This transport builds upon the excellent work and ideas from the Kafka community and previous implementations:

- **[Symfony Kafka Transport PR](https://github.com/symfony/symfony/pull/51070)** - Early exploration of native Kafka support in Symfony Messenger
- **[messenger-kafka](https://github.com/KonstantinCodes/messenger-kafka)** - Clean implementation patterns and configuration approaches
- **[php-enqueue/rdkafka](https://github.com/php-enqueue/rdkafka)** - Solid foundation for PHP-Kafka integration
- **[exoticca/kafka-transport](https://packagist.org/packages/exoticca/kafka-transport)** - A transport I developed with colleagues during my time at Exoticca, which became the foundation and inspiration for this project, incorporating lessons learned from production use

Each of these projects contributed valuable insights that helped shape the design and implementation of this transport.