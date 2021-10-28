<?php

declare(strict_types=1);

namespace Baraja\Emailer\Command;


use Baraja\Emailer\GarbageCollector;
use Baraja\Emailer\Helper;
use Baraja\Emailer\QueueRunner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class EmailerDaemon extends Command
{
	public function __construct(
		private QueueRunner $runner,
		private EntityManagerInterface $entityManager,
		private ?LoggerInterface $logger = null,
	) {
		parent::__construct();
	}


	protected function configure(): void
	{
		$this->setName('baraja:emailer-daemon');
		$this->setDescription('Run daemon for sending mails from queue.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$start = microtime(true);
			$output->writeln('Start time: ' . date('Y-m-d H:i:s'));

			$this->runner->run();

			$output->writeln('End time: ' . date('Y-m-d H:i:s') . ' [' . Helper::formatDurationFrom((int) $start) . ']');
			$output->writeln('Start garbage collector.');
			try {
				(new GarbageCollector($this->entityManager))->run();
				$output->writeln('Garbage collector run successfully.');
			} catch (\Throwable $e) {
				if ($this->logger !== null) {
					$this->logger->critical($e->getMessage(), ['exception' => $e]);
				}
				$output->writeln('Garbage collector failed: <error>' . htmlspecialchars($e->getMessage()) . '</error>');
			}

			return 0;
		} catch (\Throwable $e) {
			if ($this->logger !== null) {
				$this->logger->critical($e->getMessage(), ['exception' => $e]);
			}
			$output->writeln('<error>' . $e->getMessage() . '</error>');

			return 1;
		}
	}
}
