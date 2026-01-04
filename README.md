# Emailer

Automation tool for e-mail distribution, queue management and advanced logging for Nette Framework with Doctrine ORM.

## Main Principles

- **Queue-based delivery** - E-mails are stored in database and sent asynchronously via daemon process
- **Template rendering** - Support for Latte, HTML and plain text templates with localization
- **Retry mechanism** - Failed e-mails are automatically retried up to 5 times with exponential backoff
- **Logging & monitoring** - Complete audit trail of all sent e-mails with timing metrics
- **Garbage collection** - Automatic cleanup of old logs and message bodies
- **Recipient fixing** - Automatic typo correction for common e-mail domains (gmail.com, seznam.cz, etc.)

## Architecture

```
+------------------+     +------------------+     +------------------+
|                  |     |                  |     |                  |
|  Your App Code   |---->|     Emailer      |---->|  Database Queue  |
|                  |     |                  |     |  (Doctrine ORM)  |
+------------------+     +------------------+     +------------------+
                                                          |
                                                          v
+------------------+     +------------------+     +------------------+
|                  |     |                  |     |                  |
|   SMTP Server    |<----|  Queue Runner   |<----|  Emailer Daemon  |
|   / Sendmail     |     |                  |     |   (CLI Command)  |
+------------------+     +------------------+     +------------------+
```

## Main Components

| Component | Description |
|-----------|-------------|
| `Emailer` | Main service implementing `Nette\Mail\Mailer` interface. Handles sending e-mails directly or via queue. |
| `Message` | Extended `Nette\Mail\Message` with support for priority, scheduled sending and localization. |
| `QueueRunner` | Processes e-mail queue, handles retries and logs results. |
| `EmailerDaemon` | Symfony Console command for running queue processor as daemon. |
| `TemplateRenderer` | Renders e-mail templates using pluggable renderers (Latte, HTML, TXT). |
| `EmailerLogger` | Persists logs to database with INFO/WARNING/ERROR levels. |
| `GarbageCollector` | Cleans old logs and message bodies to keep database size manageable. |
| `Fixer` | Interface for e-mail address correction (typo fixing). |

## Entity Structure

| Entity | Table | Purpose |
|--------|-------|---------|
| `Email` | `core__emailer_email` | Queue entry with status, attempts count, timing metrics |
| `Log` | `core__emailer_log` | Log entries linked to e-mails |
| `Configuration` | - | Runtime configuration (not persisted) |

## 📦 Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/emailer) and
[GitHub](https://github.com/baraja-core/emailer).

To install, simply use the command:

```shell
$ composer require baraja-core/emailer
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

### Requirements

- PHP 8.0+
- Nette Framework 3.0+
- Doctrine ORM
- Latte 2.5+

## Configuration

Register the extension in your `config.neon`:

```neon
extensions:
    emailer: Baraja\Emailer\EmailerExtension

emailer:
    useQueue: true
    defaultFrom: noreply@example.com
    adminEmails:
        - admin@example.com
    mail:
        smtp: true
        host: smtp.example.com
        port: 465
        username: user@example.com
        password: secret
        secure: ssl
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `useQueue` | bool | `true` | Enable queue mode (recommended for production) |
| `defaultFrom` | string | - | Default sender address |
| `adminEmails` | array | `[]` | Admin e-mails for system notifications |
| `mail` | array | - | SMTP configuration (same as Nette mailer) |

## Basic Usage

### Sending Simple E-mail

```php
use Baraja\Emailer\Emailer;
use Baraja\Emailer\Message;

class OrderService
{
    public function __construct(
        private Emailer $emailer,
    ) {}

    public function sendConfirmation(string $to): void
    {
        $message = new Message;
        $message->setFrom('shop@example.com');
        $message->addTo($to);
        $message->setSubject('Order Confirmation');
        $message->setHtmlBody('<h1>Thank you for your order!</h1>');

        $this->emailer->send($message);
    }
}
```

### Sending Urgent E-mail (Bypasses Queue)

```php
$message = new Message;
$message->setPriority(Message::URGENT);
$message->setSubject('Critical Alert');
// ...

$this->emailer->send($message); // Sent immediately
```

### Using E-mail Service Classes

Create a reusable e-mail service by extending `BaseEmail`:

```php
use Baraja\Emailer\Email\BaseEmail;
use Baraja\Emailer\Message;

class WelcomeEmail extends BaseEmail
{
    public function getName(): string
    {
        return 'Welcome Email';
    }

    public function getDescription(): ?string
    {
        return 'Sent to new users after registration';
    }

    public function getTemplate(string $locale): ?string
    {
        return __DIR__ . "/templates/welcome.{$locale}.latte";
    }

    public function getMessage(): Message
    {
        $message = parent::getMessage();
        $message->setSubject('Welcome to Our Service');
        return $message;
    }
}
```

Then send it:

```php
$this->emailer
    ->getEmailServiceByType(WelcomeEmail::class, [
        'to' => 'user@example.com',
        'userName' => 'John',
    ])
    ->send();
```

### Scheduled Sending

```php
$message = new Message;
$message->setSendEarliestAt('tomorrow 9:00');
// or
$message->setSendEarliestAt(new \DateTimeImmutable('+2 hours'));

$this->emailer->send($message);
```

### Sending to Administrators

```php
$this->emailer->sendMessageToAdministrators(
    subject: 'Server Alert',
    message: 'Disk space is running low!',
    additionalEmails: ['ops@example.com'],
);
```

## Running the Daemon

Set up a cron job to run the e-mail daemon:

```shell
# Run every 5 minutes
*/5 * * * * php bin/console baraja:emailer-daemon
```

The daemon will:
1. Process queued e-mails for ~5 minutes (configurable timeout)
2. Handle retries for failed e-mails
3. Run garbage collection after processing

### Queue Configuration

Adjust queue behavior via `Configuration` entity:

| Setting | Default | Description |
|---------|---------|-------------|
| `queueTimeout` | 295s | How long daemon runs before exiting |
| `queueEmailDelay` | 0.3s | Delay between sending e-mails |
| `queueCheckIterationDelay` | 2s | Delay when queue is empty |
| `maxAllowedAttempts` | 5 | Max retry attempts before marking as failed |

## Template Rendering

### Supported Formats

| Format | Extension | Renderer |
|--------|-----------|----------|
| Latte | `.latte` | `LatteRenderer` |
| HTML | `.html` | `HtmlRenderer` |
| Plain Text | `.txt` | `TextRenderer` |

### Latte Template Example

```latte
{* templates/welcome.en.latte *}
<h1>Welcome, {$userName}!</h1>
<p>Thank you for joining our service.</p>
<p><a n:href="Front:Homepage:default">Visit our website</a></p>
```

### Translation Support

Use `{_}` tags for translations:

```latte
<p>{_}email.welcome.greeting{/_}</p>
<p>{_'email.welcome.message'}</p>
```

### Custom Renderers

Implement `Renderer` interface and tag it:

```neon
services:
    -
        factory: App\Email\MjmlRenderer
        tags: [emailer-renderer]
```

## E-mail Statuses

| Status | Description |
|--------|-------------|
| `in-queue` | Waiting to be sent |
| `not-ready-to-queue` | Scheduled for future |
| `waiting-for-next-attempt` | Failed, will retry |
| `sent` | Successfully delivered |
| `preparing-error` | Template/build error |
| `sending-error` | SMTP/delivery error |

## Custom Recipient Fixer

Implement `Fixer` interface to customize e-mail correction:

```php
use Baraja\Emailer\RecipientFixer\Fixer;

class MyFixer implements Fixer
{
    public function fix(string $email): string
    {
        // Your correction logic
        return $email;
    }
}
```

Register in DI container and it will be used automatically.

## Logging

All e-mail operations are logged with three severity levels:

- `INFO` - Successful operations
- `WARNING` - Non-critical issues
- `ERROR` - Failed operations

Logs are automatically cleaned up:
- Common logs: after 14 days
- E-mail-related logs: after 3 months
- Message bodies: after 3 months

## Author

Jan Barasek ([https://baraja.cz](https://baraja.cz))

## 📄 License

`baraja-core/emailer` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/emailer/blob/master/LICENSE) file for more details.
