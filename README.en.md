# AccountReview

**AccountReview** is a Symfony bundle designed to extract data from entities. It offers features to retrieve and export information in different formats (JSON, CSV, XML).

## Table of Contents

- [Installation](#installation)
- [Prerequisites](#prerequisites)
- [Design and Symfony Version](#design-and-symfony-version)
- [Configuration](#configuration)
  - [Mailer Configuration](#mailer-configuration)
- [Usage](#usage)
  - [Basic Options](#basic-options)
  - [Local Export](#local-export)
  - [Email Sending](#sending-by-email)
- [Commands](#commands)

## Installation

To install `AccountReview`, add it to your Symfony project via Composer:

```bash
composer require cordon/account-review
```

Activate the bundle by adding the following lines to the `config/bundles.php` file:

```php
return [
    // ...
    Cordon\AccountReview\AccountReviewBundle::class => ['all' => true],
];
```

## Prerequisites

* PHP 7.4 or higher
* Symfony 4.4, 5.x, 6.x, or 7.0
* Doctrine ORM
* Symfony Mailer (for email sending)

## Design and Symfony Version

The bundle is compatible with Symfony versions 4.4 to 7.0.

The need for compatibility with Symfony versions 4.4 to 7.0 means that new features from Symfony 5.0 and 6.0 are not used.

## Configuration

### Mailer Configuration

Configure the mailer DSN in your .env file:

```dotenv
MAILER_DSN=smtp://user:pass@smtp.example.com:25
```

Example for MailCatcher with Docker:

```dotenv
MAILER_DSN=smtp://host.docker.internal:1025
```

### Entity Configuration

Add the `cordon.exportable_entity` tag to entities in `services.yaml`:

```yaml
services:
  App\Entity\User:
    tags:
      - { name: 'cordon.exportable_entity' }

  App\Entity\Customer:
    tags:
      - { name: 'cordon.exportable_entity' }
```

### Excluding Properties

Exclude properties in `services.yaml`:

```yaml
cordon.account_review.entity_locator:
  class: Cordon\AccountReview\EntityLocator
    arguments:
      $config:
        entities:
          App\Entity\User:
            exclude_fields: [ 'roles', 'password', '...' ]
          App\Entity\Customer:  
            exclude_fields: [ 'imageName', '...' ]
```

## Usage

Extract user data:

```bash
php bin/console app:account-review
```

### Basic Options

```
php bin/console app:account-review [options]
```

**Available Options:**

* --method or -m: Data sending method (log, local, mail) (default: 'log')
* --format or -f: Output format (json, csv, xml) (default: 'json')

### Default Export

Export user data in JSON format:

```bash
php bin/console app:account-review
```

### Local Export

Save data locally:

```bash
php bin/console app:account-review --method=local --format=json
```

### Sending by Email

Send data by email:

```bash
php bin/console app:account-review --method=mail --format=csv --recipient=audit@example.com --emitter=no-reply@company.com
```

Add multiple recipients:

```bash
--recipient=audit@example.com --recipient=manager@example.com
```

**Email-specific Options:**

* --recipient or -r: Recipient email address(es)
* --emitter or -em: Sender email address (default: 'no-reply@account-review.com')

## Commands

List available commands:

```bash
php bin/console app:account-review --help
```

## License

Licensed under the MIT License.
