<?php

declare(strict_types=1);

namespace Baraja\Emailer\Renderer;


use Baraja\Emailer\Route;
use Baraja\Localization\Localization;
use Baraja\Url\Url;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Localization\Translator;

abstract class BaseTemplateRenderer implements Renderer
{
	protected Localization $localization;

	protected LinkGenerator $linkGenerator;

	protected ?Translator $translator;

	private string $tempDir;

	/** @var mixed[] */
	private array $defaultParameters;


	/**
	 * @param mixed[] $defaultParameters
	 */
	final public function injectPrimary(
		string $tempDir,
		Localization $localization,
		LinkGenerator $linkGenerator,
		?Translator $translator = null,
		array $defaultParameters = []
	): void {
		$this->tempDir = $tempDir;
		$this->localization = $localization;
		$this->linkGenerator = $linkGenerator;
		$this->translator = $translator;
		$this->defaultParameters = $defaultParameters;
	}


	/**
	 * @return mixed[]
	 */
	final public function getDefaultParameters(): array
	{
		try {
			$locale = $this->localization->getLocale();
		} catch (\Throwable) {
			$locale = $this->localization->getDefaultLocale();
		}

		return array_merge([
			'basePath' => Url::get()->getBaseUrl(),
			'baseUrl' => Url::get()->getBaseUrl(),
			'linkGenerator' => $this->linkGenerator,
			'translator' => $this->translator,
			'locale' => $locale,
			'defaultLocale' => $this->localization->getDefaultLocale(),
		], $this->defaultParameters);
	}


	public function beforeRenderProcess(string $template): string
	{
		$template = (string) preg_replace_callback(
			'/n:href="(?<link>[^"]*)"/',
			function (array $match): string {
				try {
					$route = Route::createByPattern($match['link']);
					$parameters = $route->getParams();
					if (isset($parameters['locale']) === false) {
						try {
							$locale = $this->localization->getLocale();
						} catch (\Throwable) {
							$locale = $this->localization->getDefaultLocale();
						}
						$parameters['locale'] = $locale;
					}

					$renderParameters = [];
					foreach ($parameters as $parameterName => $parameterValue) {
						$escapeParamValue = static function (self $renderer, $value) {
							if (strncmp($value, '$', 1) === 0) {
								return $value;
							}
							if (is_string($value)) {
								return '"' . $renderer->safeHtmlSpecialChars($value) . '"';
							}

							return $value;
						};

						$renderParameters[] = '"' . $parameterName . '" => ' . $escapeParamValue($this, $parameterValue);
					}

					$route = ($route->getModule() ?? 'Front')
						. ':' . $route->getPresenterName(true)
						. ':' . $route->getActionName();

					return 'href="{$linkGenerator->link('
						. '"' . htmlspecialchars(str_replace('Front:Front:', 'Front:', $route), ENT_QUOTES) . '", '
						. '[' . implode(', ', $renderParameters) . '])}"';
				} catch (InvalidLinkException) {
					return 'href="' . Url::get()->getBaseUrl() . '"';
				}
			},
			$template,
		);

		$template = (string) preg_replace_callback(
			'/({_})(?<haystack>.+?)({\/_})/', // {_}hello{/_}
			function (array $match): string {
				$haystack = $match['haystack'];

				return '{$translator->translate('
					. (strncmp($haystack, '$', 1) === 0 ? $haystack : '"' . $this->safeHtmlSpecialChars($haystack) . '"')
					. ')}';
			},
			$template,
		);

		return (string) preg_replace_callback(
			'/{_(?:(?<haystack>.*?))}/', // {_hello}, {_'hello'}, {_"hello"}
			function (array $match): string {
				$haystack = trim($match['haystack'], '\'"');

				return '{$translator->translate('
					. (strncmp($haystack, '$', 1) === 0 ? $haystack : '"' . $this->safeHtmlSpecialChars($haystack) . '"')
					. ')}';
			},
			$template,
		);
	}


	final protected function getTempDir(): string
	{
		return $this->tempDir;
	}


	private function safeHtmlSpecialChars(string $haystack): string
	{
		return (string) str_replace('-&gt;', '->', htmlspecialchars($haystack, ENT_QUOTES));
	}
}
