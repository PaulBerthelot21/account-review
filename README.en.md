# AccountReview

**AccountReview** is a Symfony bundle designed to extract user data as part of an ISO 27001 audit. It provides functionality to retrieve and export user information in various formats (JSON, CSV, XML).

## Table of Contents

- [Installation](#installation)
- [Prerequisites](#prerequisites)
- [Design and Symfony Version](#design-and-symfony-version)
- [Configuration](#configuration)
    - [Mailer Configuration](#mailer-configuration)
- [Usage](#usage)
    - [Basic Options](#basic-options)
    - [Local Export](#local-export)
    - [Email Sending](#email-sending)
- [Commands](#commands)

## Installation

To install `AccountReview`, add it to your Symfony project via Composer:

```bash
composer require cordon/account-review
```

Then, enable the bundle by adding the following lines to your `config/bundles.php` file:

```php
return [
    // ...
    Cordon\AccountReview\CordonAccountReviewBundle::class => ['all' => true],
];
```

## Prerequisites

* PHP 7.4 or higher
* Symfony 4.4, 5.x, 6.x or 7.0
* Doctrine ORM
* Symfony Mailer (for email sending)

## Design and Symfony Version

The bundle is compatible with Symfony versions 4.4 through 7.0.

Due to the need for compatibility across Symfony versions 4.4 to 7.0, new features from Symfony 5.0 and 6.0 are not utilized.

## Configuration

### Mailer Configuration
To use the email sending functionality, configure the mailer DSN in your .env file:

```dotenv
MAILER_DSN=smtp://user:pass@smtp.example.com:25
```

Another example for using a MailCatcher with Docker:

```dotenv
MAILER_DSN=smtp://host.docker.internal:1025
```

## Usage

To extract user data, run the following command:

```bash
php bin/console app:account-review
```

### Basic Options

The main command supports several options:
```
php bin/console app:account-review [options]
```
**Available Options:**
* --class or -c: User entity class to use (default: 'App\Entity\User')
* --method or -m: Data sending method (log, local, mail) (default: 'log')
* --format or -f: Output format (json, csv, xml) (default: 'json')

### Local Export
To save data to a local file:

```bash
php bin/console app:account-review --method=local --format=json --output=users.json
```

### Email Sending
To send data via email:

```bash
php bin/console app:account-review --method=mail --format=csv --recipient=audit@example.com --emitter=no-reply@company.com
```

**Email-specific Options:**
* --recipient or -r: Recipient email address
* --emitter or -em: Sender email address (default: 'no-reply@account-review.com')

## Commands

To display the list of available commands, run:

```bash
php bin/console app:account-review --help
```

## License
This bundle is licensed under the MIT License.
