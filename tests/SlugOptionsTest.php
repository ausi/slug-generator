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
		$this->assertSame('a-z0-9', $options->getValidChars());

		$options2 = $options->merge(new SlugOptions(['validChars' => 'x']));
		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('a-z0-9', $options->getValidChars());
		$this->assertSame('x', $options2->getDelimiter());
		$this->assertSame('x', $options2->getValidChars());
	}

	public function testGetIterator()
	{
		$options = new SlugOptions;
		$this->assertSame([], iterator_to_array($options));

		$options = new SlugOptions(['delimiter' => 'x']);
		$this->assertSame(['delimiter' => 'x'], iterator_to_array($options));

		$options->setDelimiter('-');
		$this->assertSame(['delimiter' => '-'], iterator_to_array($options));

		$options->setValidChars('x');
		$this->assertSame(['delimiter' => '-', 'validChars' => 'x'], iterator_to_array($options));
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

	public function testSetValidChars()
	{
		$options = new SlugOptions;
		$this->assertSame('a-z0-9', $options->getValidChars());

		$options = new SlugOptions(['validChars' => 'x']);
		$this->assertSame('x', $options->getValidChars());
		$this->assertSame('', $options->setValidChars('')->getValidChars());
		$this->assertSame('a', $options->setValidChars('a')->getValidChars());
		$this->assertSame('a-c', $options->setValidChars('a-c')->getValidChars());
		$this->assertSame('\p{Ll}', $options->setValidChars('\p{Ll}')->getValidChars());
		$this->assertSame('/', $options->setValidChars('/')->getValidChars());
		$this->assertSame('\\\\', $options->setValidChars('\\\\')->getValidChars());
		$this->assertSame('{}*?.\\]', $options->setValidChars('{}*?.\\]')->getValidChars());
	}

	/**
	 * @dataProvider getInvalidCharacterClasses
	 *
	 * @param mixed $valid
	 */
	public function testSetValidCharsThrows($valid)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageRegExp('("'.preg_quote($valid).'")');

		$options->setValidChars($valid);
	}

	public function testSetIgnoreChars()
	{
		$options = new SlugOptions;
		$this->assertSame('\p{Mn}\p{Lm}', $options->getIgnoreChars());

		$options = new SlugOptions(['ignoreChars' => 'x']);
		$this->assertSame('x', $options->getIgnoreChars());
		$this->assertSame('', $options->setIgnoreChars('')->getIgnoreChars());
		$this->assertSame('a', $options->setIgnoreChars('a')->getIgnoreChars());
		$this->assertSame('a-c', $options->setIgnoreChars('a-c')->getIgnoreChars());
		$this->assertSame('\p{Ll}', $options->setIgnoreChars('\p{Ll}')->getIgnoreChars());
		$this->assertSame('/', $options->setIgnoreChars('/')->getIgnoreChars());
		$this->assertSame('\\\\', $options->setIgnoreChars('\\\\')->getIgnoreChars());
		$this->assertSame('{}*?.\\]', $options->setIgnoreChars('{}*?.\\]')->getIgnoreChars());
	}

	/**
	 * @dataProvider getInvalidCharacterClasses
	 *
	 * @param mixed $ignore
	 */
	public function testSetIgnoreCharsThrows($ignore)
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageRegExp('("'.preg_quote($ignore).'")');

		$options->setIgnoreChars($ignore);
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
		$this->assertSame(['a > b', 'c > d'], $options->setTransforms(['a > b', 'c > d'])->getTransforms());
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

		// Test iterators with duplicate keys
		$iterator = new \AppendIterator;
		$iterator->append(new \ArrayIterator(['key1' => 'a > b']));
		$iterator->append(new \ArrayIterator(['key1' => 'b > c']));

		$this->assertSame(['a > b', 'b > c', 'Lower', 'Upper'], $options->setPreTransforms($iterator)->getTransforms());
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

		// Test iterators with duplicate keys
		$iterator = new \AppendIterator;
		$iterator->append(new \ArrayIterator(['key1' => 'a > b']));
		$iterator->append(new \ArrayIterator(['key1' => 'b > c']));

		$this->assertSame(['Upper', 'Lower', 'a > b', 'b > c'], $options->setPostTransforms($iterator)->getTransforms());
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

	public function testUnknownOptionThrows()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageRegExp('(unknown.*"foo")i');

		new SlugOptions(['foo' => 'bar']);
	}
}
