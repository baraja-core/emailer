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

/**
 * @method array{
 *    mail: array<string, mixed>,
 *    useQueue: bool,
 *    adminEmails: array<int, string>,
 *    defaultFrom?: string
 * } getConfig()
 */
final class EmailerExtension extends CompilerExtension
{
	/**
	 * @return array<int, string>
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
			'adminEmails' => Expect::arrayOf(Expect::string())->default([]),
			'defaultFrom' => Expect::string(),
		])->castTo('array');
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		if (class_exists(OrmAnnotationsExtension::class)) {
			OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Emailer\Entity', __DIR__ . '/Entity');
		}
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
			throw new \LogicException('Mailer service is broken: ' . $e->getMessage(), $e->getCode(), $e);
		}

		$config = $this->getConfig();

		if (isset($builder->parameters['tempDir']) === false) {
			throw new \RuntimeException('System parameter "tempDir" is required. Please check your project configuration.');
		}

		$builder->addDefinition($this->prefix('configuration'))
			->setFactory(Configuration::class)
			->setArguments([
				'tempDir' => $builder->parameters['tempDir'],
				'useQueue' => $config['useQueue'],
				'adminEmails' => $config['adminEmails'],
				'defaultFrom' => $config['defaultFrom'] ?? null,
			]);

		FileSystem::createDir($attachmentBasePath = $builder->parameters['tempDir'] . '/emailer-attachments');
		$builder->addAlias('mail.mailer', Emailer::class);

		if (is_array($dicMailConfiguration) === false) {
			throw new \RuntimeException(
				'Your project Nette Mailer configuration is broken, '
				. 'because type "' . get_debug_type($dicMailConfiguration) . '" given. '
				. 'Rewriting default configuration is not recommended. '
				. 'Did you use native service?' . "\n"
				. 'To solve this issue: Please check configuration of service "mail.mailer".',
			);
		}

		$builder->addDefinition($this->prefix('emailer'))
			->setFactory(Emailer::class)
			->setArgument('config', array_merge($dicMailConfiguration, $config['mail']))
			->setArgument('attachmentBasePath', $attachmentBasePath);

		$builder->addAccessorDefinition($this->prefix('emailerAccessor'))
			->setImplement(EmailerAccessor::class);

		$builder->addDefinition($this->prefix('queueRunner'))
			->setFactory(QueueRunner::class);

		$builder->addDefinition($this->prefix('daemon'))
			->setFactory(EmailerDaemon::class);
	}
}
