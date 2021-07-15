<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Doctrine\EntityManager;
use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\Entity\Log;

final class GarbageCollector
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function run(): void
	{
		$this->entityManager->clear();
		$this->removeCommonLogs();
		$this->removeEmailLogs();
		$this->removeMessageBodies();
	}


	private function removeCommonLogs(string $interval = '14 days'): void
	{
		/** @var Log[] $logs */
		$logs = $this->entityManager->getRepository(Log::class)
			->createQueryBuilder('log')
			->select('PARTIAL log.{id}')
			->where('log.email IS NULL')
			->andWhere('log.insertedDate < :date')
			->setParameter('date', 'now - ' . $interval)
			->setMaxResults(1000)
			->getQuery()
			->getResult();

		$this->remove($logs);
	}


	private function removeEmailLogs(string $interval = '3 months'): void
	{
		/** @var Log[] $logs */
		$logs = $this->entityManager->getRepository(Log::class)
			->createQueryBuilder('log')
			->select('PARTIAL log.{id}')
			->where('log.email IS NOT NULL')
			->andWhere('log.insertedDate < :date')
			->setParameter('date', 'now - ' . $interval)
			->setMaxResults(1000)
			->getQuery()
			->getResult();

		$this->remove($logs);
	}


	private function removeMessageBodies(string $interval = '3 months'): void
	{
		/** @var Email[] $emails */
		$emails = $this->entityManager->getRepository(Email::class)
			->createQueryBuilder('email')
			->select('email, message')
			->leftJoin('email.message', 'message')
			->andWhere('message.htmlBody IS NOT NULL')
			->andWhere('email.status = :status')
			->andWhere('email.datetimeInserted < :date')
			->setParameter('status', Email::STATUS_SENT)
			->setParameter('date', 'now - ' . $interval)
			->setMaxResults(1000)
			->getQuery()
			->getResult();

		foreach ($emails as $email) {
			$email->getMessage()->setHtmlBody(null);
		}
		$this->entityManager->flush();
	}


	/**
	 * @param object[] $entities
	 */
	private function remove(array $entities): void
	{
		foreach ($entities as $entity) {
			$this->entityManager->remove($entity);
		}
		$this->entityManager->flush();
		$this->entityManager->clear();
	}
}
