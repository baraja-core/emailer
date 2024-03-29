<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\DoctrineMailMessage\MessageEntity;
use Baraja\Emailer\Entity\Configuration;
use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Nette\Mail\SendException;
use Psr\Log\LoggerInterface;

final class QueueRunner
{
	private Sender $sender;

	private MessageEntity $messageEntity;

	private EmailerLogger $logger;

	private Configuration $configuration;


	public function __construct(
		private EntityManagerInterface $entityManager,
		Emailer $emailer,
		private ?LoggerInterface $psrLogger = null,
	) {
		$this->configuration = $emailer->getConfiguration();
		$this->sender = $emailer->getSender();
		$this->messageEntity = $emailer->getMessageEntity();
		$this->logger = $emailer->getLogger();
	}


	public function run(): int
	{
		$result = 0;
		$startTime = microtime(true);

		while (true) {
			echo '.';
			if (time() - $startTime > $this->configuration->getQueueTimeout()) {
				break;
			}

			/** @var Email[] $emails */
			$emails = (new Paginator(
				(new EntityRepository(
					$this->entityManager,
					$this->entityManager->getClassMetadata(Email::class),
				))
					->createQueryBuilder('email')
					->select('email, message')
					->leftJoin('email.message', 'message')
					->where('email.status = :statusInQueue OR (
						email.status = :statusWaitingForNextAttempt
						AND (
							email.sendEarliestNextAttemptAt IS NULL
							OR email.sendEarliestNextAttemptAt <= :now
						)
					)')
					->andWhere('email.sendEarliestAt IS NULL OR email.sendEarliestAt <= :now')
					->setParameter('statusInQueue', Email::STATUS_IN_QUEUE)
					->setParameter('statusWaitingForNextAttempt', Email::STATUS_WAITING_FOR_NEXT_ATTEMPT)
					->setParameter('now', new \DateTimeImmutable('now'))
					->orderBy('message.priority', 'ASC')
					->setMaxResults(1),
			))->getIterator();

			if (isset($emails[0]) === true) {
				$email = $emails[0];
			} else {
				usleep($this->configuration->getQueueCheckIterationDelay() * 1_000 * 1_000);
				continue;
			}

			try {
				echo 'M';
				$this->process($email);
				$result++;
			} catch (\Throwable $e) {
				echo 'E';

				$this->logger->log(
					level: Log::LevelError,
					message: 'Failed to send: ' . $e->getMessage() . ', details has been logged.',
					email: $email,
				);

				if ($email->getFailedAttemptsCount() >= $this->configuration->getMaxAllowedAttempts()) {
					if ($this->psrLogger !== null) {
						$this->psrLogger->critical($e->getMessage(), ['exception' => $e]);
					}
					$email->setStatus($e instanceof SendException ? Email::STATUS_SENDING_ERROR : Email::STATUS_PREPARING_ERROR);
					$email->addNote(date('Y-m-d H:i:s') . ': ' . $e->getMessage());
				} else { // We'll try sending again in a few minutes at the earliest
					if ($this->psrLogger !== null) {
						$this->psrLogger->debug($e->getMessage(), ['exception' => $e]);
					}
					$email->setStatus(Email::STATUS_WAITING_FOR_NEXT_ATTEMPT);
					$email->setSendEarliestNextAttemptAt(new \DateTimeImmutable('now + 15 minutes'));
					$email->incrementFailedAttemptsCount();
				}
			}
			$this->entityManager->flush();
			$this->entityManager->clear();

			usleep((int) ($this->configuration->getQueueEmailDelay() * 1_000 * 1_000));
		}

		$this->logger->log(Log::LevelInfo, 'FINISHED: sender was running for ' . Helper::formatDurationFrom((int) $startTime) . ' and it sent ' . $result . ' e-mails');

		return $result;
	}


	private function process(Email $email): void
	{
		// build Message instance
		$builderStartTime = microtime(true);
		$message = $this->messageEntity->toMessage($email->getMessage());
		$builderDuration = microtime(true) - $builderStartTime;

		if (trim($message->getHtmlBody()) === '' && trim($message->getBody()) === '') {
			$email->setStatus(Email::STATUS_PREPARING_ERROR);
			$email->addNote(date('Y-m-d H:i:s') . ' - E-mail was not sent (empty body)');

			return;
		}

		// send Message
		$mailerStartTime = microtime(true);
		$this->sender->send($message);
		$mailerDuration = microtime(true) - $mailerStartTime;

		$email->setStatus(Email::STATUS_SENT);
		$email->setPreparingDuration($builderDuration);
		$email->setSendingDuration($mailerDuration);
		$email->setDatetimeSent(new \DateTimeImmutable('now'));

		$this->logger->log(
			Log::LevelInfo,
			'E-mail was successfully sent to '
			. '"' . $email->getMessage()->getTo() . '" '
			. 'with subject "' . trim($message->getSubject() ?? 'NULL') . '". '
			. 'Preparation took "' . Helper::formatMicroTime((float) $email->getPreparingDuration()) . '" '
			. 'and sending took "' . Helper::formatMicroTime((float) $email->getSendingDuration()) . '"',
			$email,
		);
	}
}
