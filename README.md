# Event Driven Kafka Messenger Transport

A custom transport for Symfony Messenger specifically designed to work with Apache Kafka as an event streaming platform.

## Why another Kafka transport?

Existing packages for Kafka + Symfony Messenger are outdated or don't cover advanced event streaming use cases. This transport is designed for:

- **Event Streaming**: Optimized for real-time event flows
- **Flexibility**: Granular configuration for producers and consumers
- **Simplicity**: Automatic JSON serialization without additional configuration
- **Multi-topic**: Produce to multiple topics with a single configuration
- **Selective Consumption**: Consume specific event types from topics containing multiple event types

## Installation

```bash
composer require alvarorosado/event-driven-kafka-messenger-transport
```

## Environment Variables

```bash
# .env
KAFKA_DSN=kafka://localhost:9092
KAFKA_SECURITY_PROTOCOL=PLAINTEXT
KAFKA_USERNAME=your_username
KAFKA_PASSWORD=your_password
APP_ENV=dev
```

## Required Configuration File

Create the global configuration file for Kafka settings:

```yaml
# config/packages/kafka_messenger.yaml
kafka_messenger:
  consumer:
    commit_async: true
    consume_timeout_ms: 500
    config:
      group.id: 'default-group'
      security.protocol: "%env(KAFKA_SECURITY_PROTOCOL)%"
      sasl.username: "%env(KAFKA_USERNAME)%"
      sasl.password: "%env(KAFKA_PASSWORD)%"
      auto.offset.reset: 'earliest'
  producer:
    config:
      security.protocol: "%env(KAFKA_SECURITY_PROTOCOL)%"
      sasl.username: "%env(KAFKA_USERNAME)%"
      sasl.password: "%env(KAFKA_PASSWORD)%"
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

**⚠️ Important**: To use advanced mode, you **must implement the Hook interface** and define `KafkaIdentifierStamp` for each message type. This identifier is used as the JSON key for message type mapping during consumption. See the [Stamp System](https://github.com/AlvaroRosado/event-driven-kafka-messenger-transport/blob/main/README.md#%EF%B8%8F-stamp-system) section below for complete implementation details.

## Key Features

### 🔄 Two Operation Modes

| Feature | Basic Mode | Advanced Mode |
|---|---|---|
| Serialization | Native PHP | Automatic JSON |
| Types per topic | Single | Multiple |
| Selective filtering | No | Yes |
| Interoperability | PHP only | Multi-language |
| Routing | Symfony standard | Custom |

**When to use each mode?**

- **Basic Mode**: Simple message queues, PHP only, maximum compatibility
- **Advanced Mode**: Event streaming, microservices, interoperability, selective filtering

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
- They don't accumulate in the queue
- Multiple services can process different subsets of the same topic

### 🏷️ Stamp System

Control Kafka behavior through Stamps in a custom Hook. **This Hook implementation is required for advanced mode** to properly handle JSON serialization and message routing.

**Recommended Pattern - Base Message Class:**
```php
abstract class BaseKafkaMessage
{
    abstract public function getKafkaIdentifier(): string;
}

class UserRegistered extends BaseKafkaMessage
{
    public function getKafkaIdentifier(): string
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
        if ($message instanceof BaseKafkaMessage) {
            $stamps[] = new KafkaIdentifierStamp($message->getKafkaIdentifier());
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
          consumer:
            routing:
              - name: 'user_registered'
                class: 'App\Message\UserRegistered'
            config:
              group.id: '%env(APP_ENV)%-user-events'  # Overrides global
              auto.offset.reset: 'latest'             # Only new messages
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

### 3. Multi-tenancy
```php
public function beforeProduce(Envelope $envelope): Envelope
{
    $message = $envelope->getMessage();
    
    if ($message instanceof TenantAwareMessage) {
        return $envelope->with(
            new KafkaKeyStamp($message->getTenantId()),
            new KafkaCustomHeadersStamp(['tenant_id' => $message->getTenantId()])
        );
    }
    
    return $envelope;
}
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


### Inspiration and References

This project has been inspired by and builds upon ideas from several excellent packages in the PHP/Symfony ecosystem:

- **[koco/messenger-kafka](https://github.com/KonstantinCodes/messenger-kafka)** - Early Kafka transport implementation that demonstrated basic integration patterns
- **[symfony/amazon-sqs-messenger](https://github.com/symfony/amazon-sqs-messenger)** - Excellent reference for implementing custom Symfony Messenger transports
- **[enqueue/enqueue](https://github.com/php-enqueue/enqueue)** - Comprehensive message queue abstraction that provided insights into multi-broker support

Special thanks to the Symfony Messenger team for creating such an extensible and well-designed messaging framework that makes custom transports like this possible.

### Differences from Existing Solutions

While inspired by existing packages, this transport introduces several unique features:

- **True event streaming support** with selective message filtering
- **Automatic JSON serialization** without complex configuration
- **Multi-topic production** from single transport configuration
- **Advanced stamp system** for granular Kafka control
- **Hook-based extensibility** for custom business logic integration

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
