<?php

declare(strict_types=1);

/*
 * This file is part of the ausi/slug-generator package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ausi\SlugGenerator;

/**
 * Slug generator.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugGenerator implements SlugGeneratorInterface
{
	/**
	 * @var SlugOptions
	 */
	private $options;

	/**
	 * @var array<\Transliterator>
	 */
	private $transliterators = [];

	/**
	 * @param SlugOptions|iterable<string,mixed> $options
	 */
	public function __construct(iterable $options = [])
	{
		static::checkPcreSupport();

		if (!$options instanceof SlugOptions) {
			$options = new SlugOptions($options);
		}

		$this->options = $options;
	}

	/**
	 * @throws \RuntimeException If PCRE unicode support is missing
	 *
	 * @internal
	 */
	public static function checkPcreSupport(): void
	{
		static $supported = null;

		if ($supported === null) {
			$supported = @preg_match('/\pL/u', 'a') === 1;
		}

		if (!$supported) {
			throw new \RuntimeException(sprintf('Missing PCRE unicode support'));
		}
	}

	/**
	 * @param SlugOptions|iterable<string,mixed> $options
	 */
	public function generate(string $text, iterable $options = []): string
	{
		if (preg_match('//u', $text) !== 1) {
			throw new \InvalidArgumentException('Text is invalid UTF-8');
		}

		$options = $this->options->merge($options);

		if ($options->getValidChars() === '') {
			return '';
		}

		$transliterator = $this->getTransliterator($options);
		$transformed = $transliterator->transliterate($text);

		if ($transformed === false) {
			throw new \RuntimeException(sprintf('Failed to transliterate "%s": %s', $text, $transliterator->getErrorMessage() ?: ''));
		}

		return $transformed;
	}

	private function getTransliterator(SlugOptions $options): \Transliterator
	{
		$rules = [
			$this->buildTransformRules('NFC'),
			$this->buildRemoveIgnoredRules($options->getIgnoreChars()),
		];

		foreach ($options->getTransforms() as $transform) {
			$rules[] = $this->buildTransformRules($transform, $options->getValidChars(), $options->getLocale());
		}

		$rules[] = $this->buildRemoveIgnoredRules($options->getIgnoreChars());

		$rules[] = $this->buildDelimiterRules($options->getValidChars(), $options->getDelimiter());

		$transliterator = \Transliterator::createFromRules(implode(';', array_merge(...$rules)).';');

		if ($transliterator === null) {
			foreach ($options->getTransforms() as $transform) {
				if (
					\Transliterator::createFromRules(
						implode(
							';',
							$this->buildTransformRules(
								$transform,
								$options->getValidChars(),
								$options->getLocale()
							)
						).';'
					) === null
				) {
					throw new \InvalidArgumentException(sprintf('Invalid transform rule "%s".', $transform));
				}
			}

			throw new \RuntimeException(sprintf('Failed to build transliterator: %s', implode(';', array_merge(...$rules)).';'));
		}

		return $transliterator;
	}

	/**
	 * @return array<string>
	 */
	private function buildTransformRules(string $rule, string $validChars = '', string $locale = ''): array
	{
		$rule = trim($rule);

		if (!preg_match('(^[a-z0-9/_-]+$)i', $rule)) {
			return [
				// Skip valid chars by transforming them to themselves
				'(['.$this->buildUnicodeSetFromRegex('(['.$validChars.'])us').']) > $1',
				$rule,
				// Start over at the beginning of the string after the rules are applied
				':: Null',
			];
		}

		$transformId = $this->findMatchingRule($rule, $locale);

		$ruleset = $this->fixTransliteratorRule($transformId);

		if ($ruleset !== null) {
			/** @var array<array<string>> $ruleset */
			$ruleset = array_map(
				function ($rule) use ($validChars): array {
					return $this->buildTransformRules($rule, $validChars);
				},
				$ruleset
			);

			return array_merge(...$ruleset);
		}

		$filter = '';

		if ($validChars !== '') {
			if ($transformId === 'Lower' || $transformId === 'Upper') {
				$filter = '['.$this->buildUnicodeSetFromRegex($this->createCaseRegex($validChars)).'] ';
			} else {
				$filter = '[^'.$this->buildUnicodeSetFromRegex('(['.$validChars.'])us').'] ';
			}
		}

		$rules = [':: '.$filter.$transformId];

		if ($this->findMatchingRule($rule, '') !== $transformId) {
			$rules = array_merge($rules, $this->buildTransformRules($rule, $validChars));
		}

		return $rules;
	}

	/**
	 * @return array<string>
	 */
	private function buildRemoveIgnoredRules(string $ignoreChars): array
	{
		if ($ignoreChars === '') {
			return [];
		}

		return [
			'['.$this->buildUnicodeSetFromRegex('(['.$ignoreChars.'])us').'] > ',
			':: Null',
		];
	}

	/**
	 * @return array<string>
	 */
	private function buildDelimiterRules(string $validChars, string $delimiter): array
	{
		$delimiter = $this->quoteString($delimiter);
		$invalidSet = '[^'.$this->buildUnicodeSetFromRegex('(['.$validChars.'])us').']';

		if ($delimiter !== '') {
			$invalidSet = '['.$invalidSet.'{'.$delimiter.'}]';
		}

		return [
			$invalidSet.' + > '.$delimiter,
			':: Null',
			'^ { '.$delimiter.' > ',
			$delimiter.' } $ > ',
			':: Null',
		];
	}

	private function quoteUnicodeSet(string $charRange): string
	{
		return $this->quoteString($charRange);
	}

	/**
	 * Escape every non-alphanumeric ASCII character with a backslash.
	 */
	private function quoteString(string $string): string
	{
		$quoted = preg_replace('([\x00-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F])', '\\\\$0', $string);

		if ($quoted === null) {
			throw new \RuntimeException(sprintf('Unable to quote string "%s"', $string));
		}

		return $quoted;
	}

	private function buildUnicodeSetFromRegex(string $regex): string
	{
		static $cache = [];

		if (!isset($cache[$regex])) {
			$chars = [];

			for ($i = 1; $i <= 1114111; ++$i) {
				if (preg_match($regex, \IntlChar::chr($i)) === 1) {
					$chars[] = \IntlChar::chr($i);
				}
			}
			$cache[$regex] = $this->quoteUnicodeSet(implode('', $chars));
		}

		return $cache[$regex];
	}

	/**
	 * Create a regular expression that matches all characters that are invalid
	 * but whose lower/upper-case counterparts are valid.
	 */
	private function createCaseRegex(string $valid): string
	{
		$insensitive = $valid;

		// Fix case insensitive matching of turkish “I” characters
		if (preg_match('(['.$valid.'])us', 'İı')) {
			$insensitive .= 'İı';
		}

		$insensitive = preg_replace_callback(
			'(\\\\([pP])\{L([lu])\})s',
			static function (array $match) {
				return '\\'.$match[1].'{L'.($match[2] === 'l' ? 'u' : 'l').'}';
			},
			$insensitive
		);

		return '((?:(?=(?i)['.$insensitive.'])[^'.$valid.'])+)us';
	}

	/**
	 * Find the best matching Transliterator rule
	 * for the specified transform rule and locale.
	 */
	private function findMatchingRule(string $rule, string $locale): string
	{
		$candidates = [
			'Latin-'.$rule,
			$rule,
		];

		if ($locale) {
			array_unshift(
				$candidates,
				$locale.'-'.$rule,
				\Locale::getPrimaryLanguage($locale).'-'.$rule
			);
		}

		$errorLevel = ini_set('intl.error_level', '0');
		$useExceptions = ini_set('intl.use_exceptions', '0');

		try {
			foreach ($candidates as $candidate) {
				if (\in_array($candidate, \Transliterator::listIDs(), true) || $candidate === 'de-ASCII') {
					return $candidate;
				}

				if (\Transliterator::create($candidate)) {
					return $candidate;
				}
			}
		} finally {
			ini_set('intl.error_level', (string) $errorLevel);
			ini_set('intl.use_exceptions', (string) $useExceptions);
		}

		throw new \InvalidArgumentException(sprintf('No Transliterator transform rule found for "%s" with locale "%s".', $rule, $locale));
	}

	/**
	 * Apply fixes to a transform rule for older versions of the Intl extension.
	 *
	 * @return ?array<string>
	 */
	private function fixTransliteratorRule(string $rule): ?array
	{
		if ($rule !== 'de-ASCII' || \in_array('de-ASCII', \Transliterator::listIDs(), true)) {
			return null;
		}

		// https://github.com/unicode-org/cldr/blob/release-37/common/transforms/de-ASCII.xml
		return [
			implode('; ', [
				'[ä {a \u0308}] > ae',
				'[ö {o \u0308}] > oe',
				'[ü {u \u0308}] > ue',

				'[Ä {A \u0308}] } [:Lowercase:] > Ae',
				'[Ö {O \u0308}] } [:Lowercase:] > Oe',
				'[Ü {U \u0308}] } [:Lowercase:] > Ue',

				'[Ä {A \u0308}] > AE',
				'[Ö {O \u0308}] > OE',
				'[Ü {U \u0308}] > UE',
			]),
			'Latin-ASCII',
		];
	}
}
