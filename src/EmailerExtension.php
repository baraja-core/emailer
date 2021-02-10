<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Emailer\Command\EmailerDaemon;
use Baraja\Emailer\Entity\Configuration;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\MissingServiceException;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\FileSystem;

final class EmailerExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [OrmAnnotationsExtension::class];
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'mail' => Expect::array(),
			'useQueue' => Expect::bool(true),
			'adminEmails' => Expect::arrayOf(Expect::string()),
		])->castTo('array');
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Emailer\Entity', __DIR__ . '/Entity');

		$dicMailConfiguration = [];
		try {
			/** @var ServiceDefinition $netteMailer */
			$netteMailer = $builder->getDefinition('mail.mailer');
			$netteMailerArguments = $netteMailer->getFactory()->arguments;
			if (isset($netteMailerArguments[0]) === true) {
				$netteMailerArguments = $netteMailerArguments[0];
			}
			$dicMailConfiguration = $netteMailerArguments;
			$builder->removeDefinition('mail.mailer');
		} catch (MissingServiceException $e) {
		}

		/** @var mixed[] $config */
		$config = $this->getConfig();

		if (isset($builder->parameters['tempDir']) === false) {
			throw new \RuntimeException('System parameter "tempDir" is required. Please check your project configuration.');
		}

		$builder->addDefinition($this->prefix('configuration'))
			->setFactory(Configuration::class)
			->setArguments([
				'tempDir' => $builder->parameters['tempDir'],
				'useQueue' => $config['useQueue'] ?? true,
				'adminEmails' => $config['adminEmails'] ?? [],
			]);

		FileSystem::createDir($attachmentBasePath = $builder->parameters['tempDir'] . '/emailer-attachments');
		$builder->addAlias('mail.mailer', Emailer::class);

		$builder->addDefinition($this->prefix('emailer'))
			->setFactory(Emailer::class)
			->setArgument('config', array_merge($dicMailConfiguration, $config['mail'] ?? []))
			->setArgument('attachmentBasePath', $attachmentBasePath);

		$builder->addAccessorDefinition($this->prefix('emailerAccessor'))
			->setImplement(EmailerAccessor::class);

		$builder->addDefinition($this->prefix('queueRunner'))
			->setFactory(QueueRunner::class);

		$builder->addDefinition($this->prefix('daemon'))
			->setFactory(EmailerDaemon::class);
	}
}
