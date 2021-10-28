<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class GarbageCollector
{
	public function __construct(
		private EntityManagerInterface $entityManager,
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
		/** @var array<int, Log> $logs */
		$logs = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Log::class)
		))
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
		/** @var array<int, Log> $logs */
		$logs = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Log::class)
		))
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
		$emails = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Email::class)
		))
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
	 * @param array<int, object> $entities
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
