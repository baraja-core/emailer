<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Nette\Mail\Mailer;
use Nette\Mail\Message as NetteMessage;
use Nette\Mail\SendmailMailer;
use Nette\Mail\SmtpMailer;

final class Sender implements Mailer
{
	/** @var array<string, mixed> */
	private array $config;

	private ?Mailer $mailer = null;


	/**
	 * @param array<string, mixed> $config
	 */
	public function __construct(array $config)
	{
		$this->config = array_merge([
			'smtp' => false,
			'host' => null,
			'port' => null,
			'username' => null,
			'password' => null,
			'secure' => null,
			'timeout' => null,
			'context' => null,
			'clientHost' => null,
			'persistent' => false,
		], $config);
	}


	public function send(NetteMessage $mail): void
	{
		$this->createInstance()->send($mail);
	}


	private function createInstance(): Mailer
	{
		if ($this->mailer === null) {
			$this->mailer = ($this->config['smtp'] ?? false) === true
				? self::createSmtpMailer($this->config)
				: new SendmailMailer;
		}

		return $this->mailer;
	}


	private static function createSmtpMailer(array $config): SmtpMailer
	{
		$reflection = new \ReflectionClass(SmtpMailer::class);
		$firstParameter = $reflection->getConstructor()?->getParameters()[0] ?? null;
		if ($firstParameter === null || $firstParameter->getName() === 'options') { // nette/mail 3.x
			return new SmtpMailer($config);
		}

		$toString = static fn(mixed $value): string => is_scalar($value) ? (string) $value : '';
		$streamOptions = $config['streamOptions'] ?? $config['context'] ?? null;

		$arguments = [
			// $host, $username and $password are required and non-nullable since 4.0
			'host' => $config['host'] === null ? (string) ini_get('SMTP') : $toString($config['host']),
			'username' => $toString($config['username']),
			'password' => $toString($config['password']),
			'port' => $config['port'] === null ? null : (int) $toString($config['port']),
			// the 'secure' option was renamed to 'encryption' in 4.0
			'encryption' => $config['secure'] === null ? null : $toString($config['secure']),
			'persistent' => (bool) $config['persistent'],
			'timeout' => $config['timeout'] === null ? 20 : (int) $toString($config['timeout']),
			'clientHost' => $config['clientHost'] === null ? null : $toString($config['clientHost']),
			// the 'context' option became 'streamOptions' (an array) in 4.0
			'streamOptions' => is_array($streamOptions) ? $streamOptions : null,
		];

		/** @var SmtpMailer $mailer */
		$mailer = $reflection->newInstanceArgs($arguments);

		return $mailer;
	}
}
