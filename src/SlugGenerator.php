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

		/** @phpstan-ignore-next-line */
		$text = \Normalizer::normalize($text, \Normalizer::FORM_C);
		$text = $this->removeIgnored($text, $options->getIgnoreChars());
		$text = $this->transform($text, $options->getValidChars(), $options->getTransforms(), $options->getLocale());
		$text = $this->removeIgnored($text, $options->getIgnoreChars());

		return $this->replaceWithDelimiter($text, $options->getValidChars(), $options->getDelimiter());
	}

	/**
	 * Remove ignored characters from text.
	 */
	private function removeIgnored(string $text, string $ignore): string
	{
		if ($ignore === '') {
			return $text;
		}

		$replaced = preg_replace('(['.$ignore.'])us', '', $text);

		if ($replaced === null) {
			throw new \RuntimeException(sprintf('Failed to replace "%s" in "%s".', '['.$ignore.']', $text));
		}

		return $replaced;
	}

	/**
	 * Replace all invalid characters with a delimiter
	 * and strip the delimiter from the beginning and the end.
	 */
	private function replaceWithDelimiter(string $text, string $valid, string $delimiter): string
	{
		$quoted = preg_quote($delimiter);

		// Replace all invalid characters with a single delimiter
		$replaced = preg_replace(
			'((?:[^'.$valid.']|'.$quoted.')+)us',
			$delimiter,
			$text
		);

		if ($replaced === null) {
			throw new \RuntimeException(sprintf('Failed to replace "%s" with "%s" in "%s".', '(?:[^'.$valid.']|'.$quoted.')+', $delimiter, $text));
		}

		// Remove delimiters from the beginning and the end
		$removed = preg_replace('(^(?:'.$quoted.')+|(?:'.$quoted.')+$)us', '', $replaced);

		if ($removed === null) {
			throw new \RuntimeException(sprintf('Failed to replace "%s" in "%s".', '^(?:'.$quoted.')+|(?:'.$quoted.')+$', $replaced));
		}

		return $removed;
	}

	/**
	 * Apply all transforms with the specified locale
	 * to the invalid parts of the text.
	 *
	 * @param iterable<string> $transforms
	 */
	private function transform(string $text, string $valid, iterable $transforms, string $locale): string
	{
		$regexRegular = '([^'.$valid.']+)us';
		$regexCase = $this->createCaseRegex($valid);

		foreach ($transforms as $transform) {
			$regex = $transform === 'Lower' || $transform === 'Upper' ? $regexCase : $regexRegular;

			if ($locale) {
				$text = $this->applyTransformRule($text, $transform, $locale, $regex);
			}
			$text = $this->applyTransformRule($text, $transform, '', $regex);
		}

		return $text;
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
	 * Apply a transform rule with the specified locale
	 * to the parts that match the regular expression.
	 */
	private function applyTransformRule(string $text, string $rule, string $locale, string $regex): string
	{
		$transliterator = $this->getTransliterator($rule, $locale);
		$newText = '';
		$offset = 0;

		foreach ($this->getRanges($text, $regex) as $range) {
			$newText .= substr($text, $offset, $range[0] - $offset);
			$newText .= $this->transformWithContext($transliterator, $text, $range[0], $range[1]);
			$offset = $range[0] + $range[1];
		}

		$newText .= substr($text, $offset);

		return $newText;
	}

	/**
	 * Transform the text at the specified position
	 * and use a one character context if possible.
	 *
	 * `Transliterator::transliterate()` doesn’t yet support context parameters
	 * of the underlying ICU implementation.
	 * Because of that, we add the context before the transform
	 * and check afterwards that the context didn’t change.
	 */
	private function transformWithContext(\Transliterator $transliterator, string $text, int $index, int $length): string
	{
		$left = mb_substr(substr($text, 0, $index), -1, null, 'UTF-8');
		$right = mb_substr(substr($text, $index + $length), 0, 1, 'UTF-8');

		$leftLength = \strlen($left);
		$rightLength = \strlen($right);

		$text = substr($text, $index, $length);

		$transformed = $transliterator->transliterate($left.$text.$right);

		if ($transformed === false) {
			throw new \RuntimeException(sprintf('Failed to transliterate "%s" with %s.', $left.$text.$right, $transliterator->id));
		}

		if (
			(!$leftLength || strncmp($transformed, $left, $leftLength) === 0)
			&& (!$rightLength || substr_compare($transformed, $right, -$rightLength) === 0)
		) {
			return substr($transformed, $leftLength, $rightLength ? -$rightLength : \strlen($transformed));
		}

		$transformed = $transliterator->transliterate($text);

		if ($transformed === false) {
			throw new \RuntimeException(sprintf('Failed to transliterate "%s" with %s.', $text, $transliterator->id));
		}

		return $transformed;
	}

	/**
	 * Get the Transliterator for the specified transform rule and locale.
	 */
	private function getTransliterator(string $rule, string $locale): \Transliterator
	{
		$key = $rule.'|'.$locale;

		if (!isset($this->transliterators[$key])) {
			$this->transliterators[$key] = $this->findMatchingTransliterator($rule, $locale);
		}

		return $this->transliterators[$key];
	}

	/**
	 * Find the best matching Transliterator
	 * for the specified transform rule and locale.
	 */
	private function findMatchingTransliterator(string $rule, string $locale): \Transliterator
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
				$candidate = $this->fixTransliteratorRule($candidate);

				if ($transliterator = \Transliterator::create($candidate)) {
					return $transliterator;
				}

				if ($transliterator = \Transliterator::createFromRules($candidate)) {
					return $transliterator;
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
	 */
	private function fixTransliteratorRule(string $rule): string
	{
		static $latinAsciiFix;
		static $deAsciiFix;

		if ($latinAsciiFix === null) {
			$latinAsciiFix = \in_array('Latin-ASCII', \Transliterator::listIDs(), true)
				? false
				: file_get_contents(__DIR__.'/Resources/Latin-ASCII.txt')
			;
		}

		if ($deAsciiFix === null) {
			$deAsciiFix = \in_array('de-ASCII', \Transliterator::listIDs(), true)
				? false
				: file_get_contents(__DIR__.'/Resources/de-ASCII.txt')
			;

			if ($latinAsciiFix && $deAsciiFix) {
				$deAsciiFix = str_replace('::Latin-ASCII;', $latinAsciiFix, $deAsciiFix);
			}
		}

		// Add the de-ASCII transform if a CLDR version lower than 32.0 is used.
		if ($deAsciiFix && $rule === 'de-ASCII') {
			return $deAsciiFix;
		}

		// Add the Latin-ASCII transform if a CLDR version lower than 1.9 is used.
		if ($latinAsciiFix && $rule === 'Latin-ASCII') {
			return $latinAsciiFix;
		}

		return $rule;
	}

	/**
	 * Get all matching ranges.
	 *
	 * @return array<array<int>> Array of range arrays, each consisting of index and length
	 */
	private function getRanges(string $text, string $regex): array
	{
		preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE);

		return array_map(
			static function (array $match) {
				return [$match[1], \strlen($match[0])];
			},
			$matches[0]
		);
	}
}
