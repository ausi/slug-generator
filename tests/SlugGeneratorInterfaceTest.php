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

namespace Ausi\SlugGenerator\Tests;

use Ausi\SlugGenerator\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugGeneratorInterfaceTest extends TestCase
{
	public function testInterfaceImplementation(): void
	{
		$this->assertSame('slug', (new class implements SlugGeneratorInterface {
			public function generate(string $text, iterable $options = []): string
			{
				return $text;
			}
		})->generate('slug'));
	}
}
