<?php

/*
 * This file is part of the ausi/slug-generator package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Ausi\SlugGenerator\Tests;

use Ausi\SlugGenerator\SlugOptions;
use PHPUnit\Framework\TestCase;

/**
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugOptionsTest extends TestCase
{
	public function testInstantiation()
	{
		$this->assertInstanceOf(SlugOptions::class, new SlugOptions);
		$this->assertInstanceOf(SlugOptions::class, new SlugOptions([]));
		$this->assertInstanceOf(SlugOptions::class, (new SlugOptions)->merge([]));
	}

	public function testMerge()
	{
		$options = (new SlugOptions)->merge(new SlugOptions(['delimiter' => 'x']));

		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('a-z0-9', $options->getValid());

		$options2 = $options->merge(new SlugOptions(['valid' => 'x']));
		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('a-z0-9', $options->getValid());
		$this->assertSame('x', $options2->getDelimiter());
		$this->assertSame('x', $options2->getValid());
	}

	public function testGetIterator()
	{
		$options = new SlugOptions;
		$this->assertSame([], iterator_to_array($options));

		$options = new SlugOptions(['delimiter' => 'x']);
		$this->assertSame(['delimiter' => 'x'], iterator_to_array($options));

		$options->setDelimiter('-');
		$this->assertSame(['delimiter' => '-'], iterator_to_array($options));

		$options->setValid('x');
		$this->assertSame(['delimiter' => '-', 'valid' => 'x'], iterator_to_array($options));
	}

	public function testSetDelimiter()
	{
		$options = new SlugOptions;
		$this->assertSame('-', $options->getDelimiter());

		$options = new SlugOptions(['delimiter' => 'x']);
		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('', $options->setDelimiter('')->getDelimiter());
		$this->assertSame('x', $options->setDelimiter('x')->getDelimiter());
		$this->assertSame('xx', $options->setDelimiter('xx')->getDelimiter());
	}

	public function testSetValid()
	{
		$options = new SlugOptions;
		$this->assertSame('a-z0-9', $options->getValid());

		$options = new SlugOptions(['valid' => 'x']);
		$this->assertSame('x', $options->getValid());
		$this->assertSame('', $options->setValid('')->getValid());
		$this->assertSame('a', $options->setValid('a')->getValid());
		$this->assertSame('a-c', $options->setValid('a-c')->getValid());
		$this->assertSame('\p{Ll}', $options->setValid('\p{Ll}')->getValid());
		$this->assertSame('/', $options->setValid('/')->getValid());
		$this->assertSame('\\\\', $options->setValid('\\\\')->getValid());
		$this->assertSame('{}*?.\\]', $options->setValid('{}*?.\\]')->getValid());
	}

	/**
	 * @dataProvider getInvalidCharacterClasses
	 *
	 * @param mixed $valid
	 */
	public function testSetValidThrows($valid)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageRegExp('("'.preg_quote($valid).'")');

		$options->setValid($valid);
	}

	public function testSetIgnore()
	{
		$options = new SlugOptions;
		$this->assertSame('\p{Mn}\p{Lm}', $options->getIgnore());

		$options = new SlugOptions(['ignore' => 'x']);
		$this->assertSame('x', $options->getIgnore());
		$this->assertSame('', $options->setIgnore('')->getIgnore());
		$this->assertSame('a', $options->setIgnore('a')->getIgnore());
		$this->assertSame('a-c', $options->setIgnore('a-c')->getIgnore());
		$this->assertSame('\p{Ll}', $options->setIgnore('\p{Ll}')->getIgnore());
		$this->assertSame('/', $options->setIgnore('/')->getIgnore());
		$this->assertSame('\\\\', $options->setIgnore('\\\\')->getIgnore());
		$this->assertSame('{}*?.\\]', $options->setIgnore('{}*?.\\]')->getIgnore());
	}

	/**
	 * @dataProvider getInvalidCharacterClasses
	 *
	 * @param mixed $ignore
	 */
	public function testSetIgnoreThrows($ignore)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageRegExp('("'.preg_quote($ignore).'")');

		$options->setIgnore($ignore);
	}

	/**
	 * @return array
	 */
	public function getInvalidCharacterClasses()
	{
		return [
			['^a'],
			['\\'],
			['\p'],
			['\p{'],
			['a]'],
		];
	}

	public function testSetLocale()
	{
		$options = new SlugOptions;
		$this->assertSame('', $options->getLocale());

		$options = new SlugOptions(['locale' => 'en']);
		$this->assertSame('en', $options->getLocale());
		$this->assertSame('', $options->setLocale('')->getLocale());
		$this->assertSame('en', $options->setLocale('en')->getLocale());
		$this->assertSame('en_US', $options->setLocale('en_US')->getLocale());
		$this->assertSame('en_US', $options->setLocale('en-us')->getLocale());
		$this->assertSame('en_US_LATN_XXXX', $options->setLocale('en-us-latn-xxxx')->getLocale());
	}

	/**
	 * @dataProvider getSetLocaleThrows
	 *
	 * @param mixed $locale
	 */
	public function testSetLocaleThrows($locale)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageRegExp('("'.preg_quote($locale).'")');

		$options->setLocale($locale);
	}

	/**
	 * @return array
	 */
	public function getSetLocaleThrows()
	{
		return [
			["\0"],
			['-'],
			[str_repeat('a', INTL_MAX_LOCALE_LEN + 1)],
		];
	}

	public function testSetTransforms()
	{
		$options = new SlugOptions;
		$this->assertSame(['Upper', 'Lower', 'Latn', 'ASCII', 'Upper', 'Lower'], $options->getTransforms());

		$options = new SlugOptions(['transforms' => ['Upper']]);
		$this->assertSame(['Upper'], $options->getTransforms());
		$this->assertSame(['Lower'], $options->setTransforms(['Lower'])->getTransforms());
		$this->assertSame(['a > b'], $options->setTransforms(['a > b'])->getTransforms());
		$this->assertSame(['a > b; ::Lower();'], $options->setTransforms(['a > b; ::Lower();'])->getTransforms());
		$this->assertSame(['Lower'], $options->setTransforms(new \ArrayIterator(['Lower']))->getTransforms());
		$this->assertSame([], $options->setTransforms([])->getTransforms());

		$this->assertSame(['Lower'], $options->addTransform('Lower')->getTransforms());
		$this->assertSame(['Upper', 'Lower'], $options->addTransform('Upper')->getTransforms());
		$this->assertSame(['ASCII', 'Upper', 'Lower'], $options->addTransform('ASCII', true)->getTransforms());
		$this->assertSame(['ASCII', 'Upper', 'Lower', 'Latn'], $options->addTransform('Latn', false)->getTransforms());
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @param mixed $transform
	 */
	public function testSetTransformsThrows($transform)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);

		$options->setTransforms([$transform]);
	}

	public function testSetPreTransforms()
	{
		$options = new SlugOptions(['transforms' => [], 'preTransforms' => ['Upper']]);
		$this->assertSame(['Upper'], $options->getTransforms());
		$this->assertSame(['Lower', 'Upper'], $options->setPreTransforms(['Lower'])->getTransforms());
		$this->assertSame(['a > b', 'b > c', 'Lower', 'Upper'], $options->setPreTransforms(new \ArrayIterator(['a > b', 'b > c']))->getTransforms());
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @param mixed $transform
	 */
	public function testSetPreTransformsThrows($transform)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);

		$options->setPreTransforms([$transform]);
	}

	public function testSetPostTransforms()
	{
		$options = new SlugOptions(['transforms' => [], 'postTransforms' => ['Upper']]);
		$this->assertSame(['Upper'], $options->getTransforms());
		$this->assertSame(['Upper', 'Lower'], $options->setPostTransforms(['Lower'])->getTransforms());
		$this->assertSame(['Upper', 'Lower', 'a > b', 'b > c'], $options->setPostTransforms(new \ArrayIterator(['a > b', 'b > c']))->getTransforms());
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @param mixed $transform
	 */
	public function testSetPostTransformsThrows($transform)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);

		$options->setPreTransforms([$transform]);
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @param mixed       $transform
	 * @param string|null $expectedException
	 */
	public function testAddTransformThrows($transform, $expectedException)
	{
		$options = new SlugOptions(['transforms' => []]);

		if ($expectedException) {
			$this->expectException($expectedException);
		}

		$this->assertEquals([$transform], $options->addTransform($transform)->getTransforms());
	}

	/**
	 * @return array
	 */
	public function getAddTransformThrows()
	{
		return [
			['', \InvalidArgumentException::class],
			[123, \TypeError::class],
			[[], \TypeError::class],
			[new \stdClass, \TypeError::class],
		];
	}
}
