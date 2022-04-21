<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\DoctrineMailMessage\MessageEntity;
use Baraja\Emailer\Email\Email as EmailService;
use Baraja\Emailer\Entity\Configuration;
use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\RecipientFixer\DefaultFixer;
use Baraja\Emailer\RecipientFixer\Fixer;
use Baraja\Emailer\Renderer\Renderer;
use Baraja\Emailer\Renderer\TemplateRenderer;
use Baraja\Localization\Localization;
use Baraja\Url\Url;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\LinkGenerator;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Localization\Translator;
use Nette\Mail\Mailer;
use Nette\Mail\Message as NetteMessage;
use Psr\Log\LoggerInterface;

/**
 * Emailer is a complex Mail services tool for smart e-mail management.
 */
final class Emailer implements Mailer
{
	private MessageEntity $messageEntity;

	private EmailerLogger $logger;

	private Sender $sender;

	private ?TemplateRenderer $templateRenderer = null;

	private Fixer $fixer;


	/**
	 * @param array<string, mixed> $config
	 */
	public function __construct(
		private Configuration $configuration,
		private EntityManagerInterface $entityManager,
		private Container $container,
		private Localization $localization,
		private ?Translator $translator,
		private ?LoggerInterface $psrLogger,
		string $attachmentBasePath,
		array $config,
		?Fixer $fixer = null,
	) {
		$this->messageEntity = new MessageEntity($attachmentBasePath, $entityManager);
		$this->logger = new EmailerLogger($entityManager);
		$this->sender = new Sender($config);
		$this->fixer = $fixer ?? new DefaultFixer;
	}


	/**
	 * Try send a given message.
	 * If a queue is active in the project, the message will be inserted at the end of the queue.
	 * When sending messages, please prefer own Message entity from this package.
	 * Attachments will be physically stored on disk.
	 */
	public function send(NetteMessage $mail): void
	{
		$urgent = ($mail instanceof Message && $mail->isUrgent());
		if ($urgent === false && $this->configuration->isUseQueue() === true) {
			$this->insertMessageToQueue($mail);
			return;
		}
		$mail->setPriority($urgent === true ? Message::HIGH : $mail->getPriority() ?? Message::NORMAL);
		$this->sendNow($mail);
	}


	public function sendNow(NetteMessage $message): Email
	{
		$email = $this->insertMessageToQueue($message);
		if ($email->getStatus() === Email::STATUS_SENT) {
			return $email;
		}
		try {
			$this->sender->send($this->messageEntity->toMessage($email->getMessage()));
			$email->setStatus(Email::STATUS_SENT);
			$email->setDatetimeSent(new \DateTimeImmutable('now'));
		} catch (\Throwable $e) {
			if ($this->psrLogger !== null) {
				$this->psrLogger->critical($e->getMessage(), ['exception' => $e]);
			}
			$email->setStatus(Email::STATUS_WAITING_FOR_NEXT_ATTEMPT);
			$email->incrementFailedAttemptsCount();
			$email->setSendEarliestNextAttemptAt(new \DateTimeImmutable('now + 10 seconds'));
		}
		$this->entityManager->flush();

		return $email;
	}


	/**
	 * @param array<int, string>|null $additionalEmails
	 */
	public function sendMessageToAdministrators(string $subject, string $message, ?array $additionalEmails = null): void
	{
		$messageEntity = (new Message)
			->setPriority(Message::URGENT)
			->setSubject($subject)
			->setHtmlBody(
				'<p>Hi there,</p>'
				. '<p><strong>The following alert was logged on ' . date('Y-m-d') . ' at ' . date('H:i:s') . ':</strong></p>'
				. '<p><span style="color:#cc3333">' . $message . '</span></p>'
				. ((static function (): string {
					try {
						$url = Url::get()->getCurrentUrl();

						return '<p>URL: <a href="' . $url . '" target="_blank">' . $url . '</a></p>';
					} catch (\Throwable) {
						// Silence is golden.
					}
					if (isset($_SERVER['argv'][0])) {
						return '<p>CRON - ARGS: ' . implode(' | ', $_SERVER['argv']) . '</p>';
					}

					return '<p>CRON without args</p>';
				})()),
			);

		foreach (array_merge($this->configuration->getAdminEmails(), $additionalEmails ?? []) as $email) {
			if (Helper::isEmail($email) === true) {
				$messageEntity->addTo($this->fixer->fix($email));
			}
		}

		$this->send($messageEntity);
	}


	/**
	 * @param array<string, mixed> $parameters
	 * @throws EmailerException
	 */
	public function getEmailServiceByType(
		string $type,
		array $parameters = [],
		bool $overwriteMailParameter = true,
	): MessageReadyToSend {
		if (class_exists($type) === false) {
			throw new \InvalidArgumentException(sprintf('Service class "%s" does not exist.', $type));
		}

		try {
			/** @var EmailService|object $email */
			$email = $this->container->getByType($type);

			if (!$email instanceof EmailService) {
				throw new EmailerException(sprintf('Service "%s" must be type of "%s".', $type, EmailService::class));
			}
		} catch (MissingServiceException $e) {
			throw new EmailerException($e->getMessage(), $e->getCode(), $e);
		}

		$message = $email->getMessage();
		$parameters = $overwriteMailParameter === true
			? Helper::recursiveMerge($email->getParameters(), $parameters)
			: Helper::recursiveMerge($parameters, $email->getParameters());

		try {
			$locale = $parameters['locale'] ?? $this->localization->getLocale();
			assert(is_string($locale));
		} catch (\Throwable) {
			$locale = $this->localization->getDefaultLocale();
		}

		$templatePath = $email->getTemplate($locale);
		if ($templatePath === null) {
			throw new EmailerException(sprintf('Email template for mail "%s" and locale "%s" does not exist.', $type, $locale));
		}

		$subject = $parameters['subject'] ?? $message->getSubject();
		if (PHP_SAPI !== 'cli' && $this->translator !== null) {
			$subject = $this->translator->translate($subject, $parameters);
		}
		assert(is_string($subject));

		$message->setLocale($locale);
		$message->setSubject($subject);
		$message->setHtmlBody($this->renderTemplate($templatePath, $parameters));

		$from = $parameters['from']
			?? $this->configuration->getDefaultFrom()
			?? null;

		if ($from === null) {
			throw new \InvalidArgumentException('Parameter "from" does not exist. Did you defined default configuration?');
		}
		if (is_string($from) === false) {
			throw new \InvalidArgumentException(sprintf('From must be a string, but "%s" given.', get_debug_type($from)));
		}

		$message->setFrom($this->fixer->fix($from));
		if (isset($parameters['to']) === true) {
			if (is_string($parameters['to']) === false) {
				throw new \InvalidArgumentException(sprintf('To must be a string, but "%s" given.', get_debug_type($parameters['to'])));
			}
			$message->clearHeader('To');
			$message->addTo($this->fixer->fix($parameters['to']));
		}
		if (isset($parameters['cc']) === true) {
			if (is_string($parameters['cc']) === false && is_array($parameters['cc']) === false) {
				throw new \InvalidArgumentException(sprintf('Cc must be a string or array, but "%s" given.', get_debug_type($parameters['cc'])));
			}
			$message->clearHeader('Cc');
			foreach ((array) $parameters['cc'] as $ccs) {
				foreach (explode(';', $ccs) as $cc) {
					if (Helper::isEmail($cc)) {
						$message->addCc($this->fixer->fix($cc));
					}
				}
			}
		}
		if (isset($parameters['bcc']) === true) {
			if (is_string($parameters['bcc']) === false) {
				throw new \InvalidArgumentException(sprintf('Bcc must be a string, but "%s" given.', get_debug_type($parameters['bcc'])));
			}
			$message->clearHeader('Bcc');
			foreach (explode(';', $parameters['bcc']) as $bcc) {
				if (Helper::isEmail($bcc)) {
					$message->addBcc($this->fixer->fix($bcc));
				}
			}
		}
		if (isset($parameters['sendEarliestAt']) === true) {
			/**
			 * @phpstan-ignore-next-line
			 */
			$message->setSendEarliestAt($parameters['sendEarliestAt']);
		}

		return new MessageReadyToSend($message, $this);
	}


	/**
	 * @param array<string, mixed> $parameters
	 */
	public function renderTemplate(string $templatePath, array $parameters = []): string
	{
		if ($this->templateRenderer === null) {
			/** @var LinkGenerator $linkGenerator */
			$linkGenerator = $this->container->getByType(LinkGenerator::class);

			/** @var Renderer[] $renderers */
			$renderers = [];
			foreach (array_keys($this->container->findByTag('emailer-renderer')) as $serviceName) {
				$renderers[] = $this->container->getService((string) $serviceName);
			}

			$this->templateRenderer = new TemplateRenderer(
				tempDir: $this->configuration->getTempDir(),
				renderers: $renderers,
				localization: $this->localization,
				linkGenerator: $linkGenerator,
				translator: $this->translator,
			);
		}

		return $this->templateRenderer->render($templatePath, $parameters);
	}


	public function getSender(): Sender
	{
		return $this->sender;
	}


	public function getLogger(): EmailerLogger
	{
		return $this->logger;
	}


	public function getConfiguration(): Configuration
	{
		return $this->configuration;
	}


	public function getMessageEntity(): MessageEntity
	{
		return $this->messageEntity;
	}


	public function setUseQueue(bool $useQueue): void
	{
		$this->configuration->setUseQueue($useQueue);
	}


	public function insertMessageToQueue(NetteMessage $message, string $sendEarliestAt = 'now'): Email
	{
		if (trim($message->getBody()) === '' && trim($message->getHtmlBody()) === '') {
			throw new \InvalidArgumentException(__METHOD__ . ': Empty mail (no body)');
		}
		if (\count($message->getAttachments()) > 0) {
			$sendEarliestAt .= ' + 1 minute';
		}
		$email = new Email($this->messageEntity->toEntity($message));
		try {
			$email->setLocale($this->localization->getLocale());
		} catch (\Throwable) {
			// Locale should be unknown
		}
		if ($sendEarliestAt !== 'now') {
			$email->setSendEarliestAt(new \DateTimeImmutable($sendEarliestAt));
		} elseif ($message instanceof Message) {
			$email->setSendEarliestAt($message->getSendEarliestAt());
		}

		$this->entityManager->persist($email);
		$this->entityManager->flush();

		return $email;
	}
}
