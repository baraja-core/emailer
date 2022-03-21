<?php

declare(strict_types=1);

namespace Baraja\Emailer\Renderer;


use Baraja\Localization\Localization;
use Nette\Application\LinkGenerator;
use Nette\Localization\Translator;

final class TemplateRenderer
{
	/** @var string[] (format => package name) */
	private static array $hintRendererPackages = [];

	/** @var Renderer[] */
	private array $renderers;


	/**
	 * @param Renderer[] $renderers
	 */
	public function __construct(
		string $tempDir,
		array $renderers,
		Localization $localization,
		LinkGenerator $linkGenerator,
		?Translator $translator = null,
	) {
		$renderers[] = new TextRenderer;
		$renderers[] = new HtmlRenderer;
		$renderers[] = new LatteRenderer;
		foreach ($renderers as $renderer) {
			if ($renderer instanceof BaseTemplateRenderer) {
				$renderer->injectPrimary($tempDir, $localization, $linkGenerator, $translator);
			}
		}

		$this->renderers = $renderers;
	}


	/**
	 * @param array<string, mixed> $parameters
	 */
	public function render(string $templatePath, array $parameters = []): string
	{
		$template = $this->read($templatePath);
		$format = strtolower((string) preg_replace('/^.*\.([a-zA-Z0-9]+)$/', '$1', $templatePath));
		$basicParameters = [];
		$lastException = null;

		foreach ($this->renderers as $renderer) {
			if ($renderer->isCompatible($format) === true) {
				if ($renderer instanceof BaseTemplateRenderer) {
					$template = $renderer->beforeRenderProcess($template);
					$basicParameters[] = $renderer->getDefaultParameters();
				}
				$basicParameters[] = ['templatePath' => $templatePath];
				$basicParameters[] = $parameters;

				try {
					return $renderer->render($template, array_merge([], ...$basicParameters));
				} catch (\Throwable $e) {
					$lastException = $e;
				}
			}
		}

		if ($lastException !== null) {
			throw new \RuntimeException($lastException->getMessage(), $lastException->getCode(), $lastException);
		}

		throw new \RuntimeException(
			'Renderer: Compatible renderer for format "' . $format . '" does not exist.'
			. (isset(self::$hintRendererPackages[$format]) ? ' Did you install "' . self::$hintRendererPackages[$format] . '"?' : '')
			. ($this->renderers === []
				? ' Empty renderers list.'
				: "\n\n" . 'Used renderers: ' . implode(', ', array_map(static fn(Renderer $renderer): string => $renderer::class, $this->renderers))),
		);
	}


	private function read(string $file): string
	{
		$content = @file_get_contents($file); // @ is escalated to exception
		if ($content === false) {
			throw new \RuntimeException('Unable to read file "' . $file . '"". ' . (error_get_last()['message'] ?? ''));
		}

		return $content;
	}
}
