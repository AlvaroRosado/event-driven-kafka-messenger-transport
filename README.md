# Event Driven Kafka Messenger Transport

A custom transport for Symfony Messenger specifically designed to work with Apache Kafka as an event streaming platform.

## Compatibility

This transport is compatible with:
- **Symfony 5.x || 6.*x || 7.*x
- **PHP 8.0+**

## Why another Kafka transport?

Existing packages for Kafka + Symfony Messenger are outdated or don't cover advanced event streaming use cases.

This transport is designed for:

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
framework:
  messenger:
    transports:
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        options:
          topics: ['user_events']
          json_serialization:
            enabled: true
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
    
    public function key(): ?string 
    {
        return null; // Optional: Override to provide partition key
    }
}

class UserRegistered extends BaseKafkaMessage
{
    public function identifier(): string
    {
        return 'user_registered';
    }
}
```

**Example of Hook Implementation:**
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
               
            // Optional: Add partition key if available
            if ($message->key()) {
                $stamps[] = new KafkaKeyStamp($message->key());
            }
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

## Error Handling and Retry Strategy

### Message Commit on Error

By default, the transport commits the offset even when message processing fails. This prevents the same message from being reprocessed indefinitely:

```yaml
# config/packages/event_drive_kafka_transport.yaml
event_driven_kafka_transport:
  consumer:
    commit_on_error: true  # Default behavior - always commit offset
    # commit_on_error: false  # Uncomment to retry failed messages from Kafka
```

### Retry Strategy with Failure Transports

**Important**: Retries policies are not supported in Kafka. Instead, use Symfony Messenger's failure transport system for a robust retry strategy:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      # Main Kafka transport - no retries
      kafka_events:
        dsn: '%env(KAFKA_DSN)%'
        retry_strategy:
          max_retries: 0  # No retries in Kafka transport
        failure_transport: retry_transport
        options:
          topics: ['user_events']
          json_serialization:
            enabled: true

      # Retry transport - use a transport that supports retries
      retry_transport:
        dsn: '%env(DATABASE_URL)%'  # Doctrine DBAL transport (recommended)
        # dsn: 'redis://localhost:6379/messages'  # Alternative: Redis transport
        retry_strategy:
          max_retries: 3
          delay: 5000       # 5 seconds
          multiplier: 2     # Exponential backoff
        failure_transport: dead_letter_transport

      # Dead letter transport - final destination for failed messages  
      dead_letter_transport:
        dsn: '%env(DATABASE_URL)%'  # Store permanently failed messages in database
        # dsn: '%env(KAFKA_DSN)%'    # Alternative: Kafka topic for dead letters
        # options:
        #   topics: ['user_events_dead_letter']

    routing:
      'App\Message\UserRegistered': kafka_events
```

**How it works:**

1. **Main Transport**: Processes messages with `max_retries: 0` using Kafka
2. **Failure**: Failed messages go to `retry_transport` (Doctrine DBAL recommended)
3. **Retry Transport**: Database/Redis transport attempts message 3 times with exponential backoff
4. **Final Failure**: Permanently failed messages go to `dead_letter_transport`

**Why use Doctrine DBAL for retries?**
- ✅ Native retry support with configurable strategies
- ✅ Persistent storage ensures retries survive application restarts
- ✅ Better performance for retry scenarios than Kafka
- ✅ Transactional guarantees for retry logic
- ✅ Easy to query and monitor failed messages in database

## Important Notes

### Hook System
- **Automatic detection**: Just implement `KafkaTransportHookInterface` - no service configuration needed
- **Application-specific**: Hook implementation depends on your message types and business logic
- **Stamp timing**: Stamps must be added in `beforeProduce` method

### Group ID Strategy
In Kafka, `group.id` determines which consumers belong to the same group. Consumers in the same group share topic partitions, but each message is only processed by one consumer in the group. Use specific `group.id` for each use case to prevent different services from interfering with each other.

## Acknowledgments

This transport builds upon the excellent work and ideas from the Kafka community and previous implementations:

- **[Symfony Kafka Transport PR](https://github.com/symfony/symfony/pull/51070)** - Early exploration of native Kafka support in Symfony Messenger
- **[messenger-kafka](https://github.com/KonstantinCodes/messenger-kafka)** - Clean implementation patterns and configuration approaches
- **[php-enqueue/rdkafka](https://github.com/php-enqueue/rdkafka)** - Solid foundation for PHP-Kafka integration
- **[exoticca/kafka-transport](https://packagist.org/packages/exoticca/kafka-transport)** - A transport I developed with colleagues during my time at Exoticca, which became the foundation and inspiration for this project, incorporating lessons learned from production use

Each of these projects contributed valuable insights that helped shape the design and implementation of this transport.