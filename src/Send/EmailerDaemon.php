<?php

declare(strict_types=1);

namespace Baraja\Emailer\Command;


use Baraja\Emailer\Helper;
use Baraja\Emailer\QueueRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

final class EmailerDaemon extends Command
{
	private QueueRunner $runner;


	public function __construct(QueueRunner $runner)
	{
		parent::__construct();
		$this->runner = $runner;
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

			return 0;
		} catch (\Throwable $e) {
			Debugger::log($e);
			$output->writeln('<error>' . $e->getMessage() . '</error>');

			return 1;
		}
	}
}
