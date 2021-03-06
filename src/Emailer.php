<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Doctrine\EntityManager;
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
use Nette\Application\LinkGenerator;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Localization\Translator;
use Nette\Mail\Mailer;
use Nette\Mail\Message as NetteMessage;
use Nette\Utils\DateTime;
use Nette\Utils\Validators;
use Tracy\Debugger;
use Tracy\ILogger;

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
	 * @param mixed[] $config
	 */
	public function __construct(
		private Configuration $configuration,
		private EntityManager $entityManager,
		private Container $container,
		private Localization $localization,
		private ?Translator $translator = null,
		string $attachmentBasePath,
		array $config,
		?Fixer $fixer = null
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
		} else {
			$mail->setPriority($urgent === true ? Message::HIGH : $mail->getPriority() ?? Message::NORMAL);
			$this->sendNow($mail);
		}
	}


	public function sendNow(NetteMessage $message): void
	{
		if (($email = $this->insertMessageToQueue($message)) !== null) {
			if ($email->getStatus() === Email::STATUS_SENT) {
				return;
			}
			try {
				$this->sender->send($this->messageEntity->toMessage($email->getMessage()));
				$email->setStatus(Email::STATUS_SENT);
				$email->setDatetimeSent(DateTime::from('now'));
			} catch (\Throwable $e) {
				Debugger::log($e, ILogger::CRITICAL);
				$email->setStatus(Email::STATUS_WAITING_FOR_NEXT_ATTEMPT);
				$email->incrementFailedAttemptsCount();
				$email->setSendEarliestNextAttemptAt(DateTime::from('now + 10 seconds'));
			}
			$this->entityManager->flush();
		}
	}


	/**
	 * @param string[]|null $additionalEmails
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
					} catch (\Throwable $e) {
					}
					if (isset($_SERVER['argv'][0])) {
						return '<p>CRON - ARGS: ' . implode(' | ', $_SERVER['argv']) . '</p>';
					}

					return '<p>CRON without args</p>';
				})()),
			);

		foreach (array_merge($this->configuration->getAdminEmails(), $additionalEmails ?? []) as $email) {
			if (Validators::isEmail($email) === true) {
				$messageEntity->addTo($this->fixer->fix($email));
			}
		}

		$this->send($messageEntity);
	}


	/**
	 * @param mixed[] $parameters
	 * @throws EmailerException
	 */
	public function getEmailServiceByType(
		string $type,
		array $parameters = [],
		bool $overwriteMailParameter = true
	): MessageReadyToSend {
		if (class_exists($type) === false) {
			throw new \InvalidArgumentException('Service class "' . $type . '" does not exist.');
		}

		try {
			/** @var EmailService|object $email */
			$email = $this->container->getByType($type);

			if (!$email instanceof EmailService) {
				throw new EmailerException('Service "' . $type . '" must be type of "' . EmailService::class . '".');
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
		} catch (\Throwable) {
			$locale = $this->localization->getDefaultLocale();
		}

		if (($templatePath = $email->getTemplate($locale)) === null) {
			throw new EmailerException('Email template for mail "' . $type . '" and locale "' . $locale . '" does not exist.');
		}

		$subject = $parameters['subject'] ?? $message->getSubject();
		if (PHP_SAPI !== 'cli' && $this->translator !== null) {
			$subject = $this->translator->translate($subject, $parameters);
		}

		$message->setLocale($locale);
		$message->setSubject($subject);
		$message->setHtmlBody($this->renderTemplate($templatePath, $parameters));

		if (isset($parameters['from']) === true) {
			$message->setFrom($this->fixer->fix($parameters['from']));
		}
		if (isset($parameters['to']) === true) {
			$message->clearHeader('To');
			$message->addTo($this->fixer->fix($parameters['to']));
		}
		if (isset($parameters['cc']) === true) {
			$message->clearHeader('Cc');
			foreach (explode(';', $parameters['cc']) as $cc) {
				if (Validators::isEmail($cc)) {
					$message->addCc($this->fixer->fix($cc));
				}
			}
		}
		if (isset($parameters['bcc']) === true) {
			$message->clearHeader('Bcc');
			foreach (explode(';', $parameters['bcc']) as $bcc) {
				if (Validators::isEmail($bcc)) {
					$message->addBcc($this->fixer->fix($bcc));
				}
			}
		}
		if (isset($parameters['sendEarliestAt']) === true) {
			$message->setSendEarliestAt($parameters['sendEarliestAt']);
		}

		return new MessageReadyToSend($message, $this);
	}


	/**
	 * @param mixed[] $parameters
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

			$this->templateRenderer = new TemplateRenderer($this->configuration->getTempDir(), $renderers, $this->localization, $linkGenerator, $this->translator);
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


	private function insertMessageToQueue(NetteMessage $message, string $sendEarliestAt = 'now'): ?Email
	{
		if (trim($message->getBody()) === '' && trim($message->getHtmlBody()) === '') {
			trigger_error(__METHOD__ . ': Empty mail (no body)');

			return null;
		}
		if (\count($message->getAttachments()) > 0) {
			$sendEarliestAt .= ' + 1 minute';
		}
		$email = new Email($this->messageEntity->toEntity($message));
		try {
			$email->setLocale($this->localization->getLocale());
		} catch (\Throwable $e) {
		}
		if ($sendEarliestAt !== 'now') {
			$email->setSendEarliestAt(DateTime::from($sendEarliestAt));
		} elseif ($message instanceof Message) {
			$email->setSendEarliestAt($message->getSendEarliestAt());
		}

		$this->entityManager->persist($email)->flush();

		return $email;
	}
}
