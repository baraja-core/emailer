<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Doctrine\EntityManager;
use Baraja\DoctrineMailMessage\MessageEntity;
use Baraja\Emailer\Entity\Configuration;
use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\Entity\Log;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Nette\Mail\SendException;
use Tracy\Debugger;

final class QueueRunner
{
	private Sender $sender;

	private MessageEntity $messageEntity;

	private EmailerLogger $logger;

	private Configuration $configuration;


	public function __construct(
		private EntityManager $entityManager,
		Emailer $emailer
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
				$this->entityManager->getRepository(Email::class)
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
				Debugger::log($e);

				$this->logger->log(Log::LEVEL_ERROR, 'Failed to send: ' . $e->getMessage() . ', details on Tracy logger.', $email);

				if ($email->getFailedAttemptsCount() >= $this->configuration->getMaxAllowedAttempts()) {
					$email->setStatus($e instanceof SendException ? Email::STATUS_SENDING_ERROR : Email::STATUS_PREPARING_ERROR);
					$email->addNote(date('Y-m-d H:i:s') . ' - ' . $e->getMessage());
				} else { // We'll try sending again in a few minutes at the earliest
					$email->setStatus(Email::STATUS_WAITING_FOR_NEXT_ATTEMPT);
					$email->setSendEarliestNextAttemptAt(new \DateTimeImmutable('now + 15 minutes'));
					$email->incrementFailedAttemptsCount();
				}
				$this->entityManager->flush();
			}

			usleep((int) ($this->configuration->getQueueEmailDelay() * 1_000 * 1_000));
		}

		$this->logger->log(Log::LEVEL_INFO, 'FINISHED: sender was running for ' . Helper::formatDurationFrom((int) $startTime) . ' and it sent ' . $result . ' e-mails');

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
			$this->entityManager->flush();

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
		$this->entityManager->flush();

		$this->logger->log(
			Log::LEVEL_INFO,
			'E-mail was successfully sent to '
			. '"' . $email->getMessage()->getTo() . '" '
			. 'with subject "' . trim($message->getSubject() ?? 'NULL') . '". '
			. 'Preparation took "' . Helper::formatMicroTime((int) $email->getPreparingDuration()) . '" '
			. 'and sending took "' . Helper::formatMicroTime((int) $email->getSendingDuration()) . '"',
			$email,
		);
	}
}
