# Event Driven Kafka Messenger Transport

A custom transport for Symfony Messenger specifically designed to work with Apache Kafka as an event streaming platform.

## Why another Kafka transport?

Existing packages for Kafka + Symfony Messenger are outdated or don't cover advanced event streaming use cases. Additionally, Symfony Messenger's serialization has limitations for event streaming scenarios:

**Default PHP Serialization**: Messenger uses PHP's native serializer by default, which produces binary data that's only readable by PHP applications - not suitable for multi-language architectures.

**Built-in JSON Limitations**: While Symfony supports JSON serialization, it ties message types to PHP namespaces in headers. This creates issues when:
- Two Symfony applications need to communicate (namespace conflicts)
- Non-PHP services need to consume messages (PHP-specific headers)
- You want business-friendly message identifiers

**Custom Serializer Complexity**: Symfony allows custom serializers per transport, but the configuration is cumbersome and requires extensive setup for each transport.

**This transport solves these issues automatically** by:
- Producing clean JSON with business identifiers you define
- Adding a simple header with your custom identifier (not PHP namespace)
- Enabling seamless interoperability with other languages
- Requiring minimal configuration

This transport is designed for:

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
KAFKA_DSN=ed+kafka://localhost:9092
APP_ENV=dev
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

## Supported DSN Parameters

| Parameter | Alternative Names | Description | Default | Example Values |
|---|---|---|---|---|
| `security_protocol` | `protocol`, `sec_protocol` | Kafka security protocol | `PLAINTEXT` | `PLAINTEXT`, `SASL_PLAINTEXT`, `SASL_SSL`, `SSL` |
| `sasl_mechanisms` | `mechanisms` | SASL authentication mechanism | `PLAIN` | `PLAIN`, `SCRAM-SHA-256`, `SCRAM-SHA-512` |
| `username` | `user`, `sasl_username` | SASL username | *(empty)* | `myuser` |
| `password` | `pass`, `sasl_password` | SASL password | *(empty)* | `mypass` |

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

**Why `ed+kafka://` instead of `kafka://`?**

The `ed+kafka://` DSN prefix allows this transport to coexist with other Kafka packages in the same project. This enables gradual migration and safe testing without conflicts - you can keep your existing Kafka transport while evaluating this one.

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