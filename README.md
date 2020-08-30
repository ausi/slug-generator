Slug Generator Library
======================

[![Build Status](https://img.shields.io/github/workflow/status/ausi/slug-generator/CI/master.svg?style=flat-square)](https://github.com/ausi/slug-generator/actions?query=branch%3Amaster)
[![Coverage](https://img.shields.io/codecov/c/github/ausi/slug-generator/master.svg?style=flat-square)](https://codecov.io/gh/ausi/slug-generator)
[![Packagist Version](https://img.shields.io/packagist/v/ausi/slug-generator.svg?style=flat-square)](https://packagist.org/packages/ausi/slug-generator)
[![Downloads](https://img.shields.io/packagist/dt/ausi/slug-generator.svg?style=flat-square)](https://packagist.org/packages/ausi/slug-generator)
[![MIT License](https://img.shields.io/github/license/ausi/slug-generator.svg?style=flat-square)](https://github.com/ausi/slug-generator/blob/master/LICENSE)

This library provides methods to generate slugs
for URLs, filenames or any other target that has a limited character set.
It’s based on PHPs Transliterator class which uses the data of the [CLDR][]
to transform characters between different scripts (e.g. Cyrillic to Latin)
or types (e.g. upper- to lower-case or from special characters to ASCII).

Usage
-----

```php
<?php
use Ausi\SlugGenerator\SlugGenerator;

$generator = new SlugGenerator;

$generator->generate('Hello Wörld!');  // Output: hello-world
$generator->generate('Καλημέρα');      // Output: kalemera
$generator->generate('фильм');         // Output: film
$generator->generate('富士山');         // Output: fu-shi-shan
$generator->generate('國語');           // Output: guo-yu

// Different valid character set, a specified locale and a delimiter
$generator = new SlugGenerator((new SlugOptions)
    ->setValidChars('a-zA-Z0-9')
    ->setLocale('de')
    ->setDelimiter('_')
);
$generator->generate('Äpfel und Bäume');  // Aepfel_und_Baeume
```

Installation
------------

To install the library use [Composer][] or download the source files from GitHub.

```sh
composer require ausi/slug-generator
```

Why create another slug library, aren’t there enough already?
-------------------------------------------------------------

There are many code snippets and some good libraries out there that create slugs,
but I didn’t find anything that met my requirements.
Options are often very limited which makes it hard to customize for different use cases.
Some libs carry large rulesets with them that try to convert characters to ASCII,
no one uses Unicode’s [CLDR][]
which is the standard for transliteration rules and many other transforms.

But most importantly no library was able to do the “correct” conversions,
like `Ö-Äpfel` to `OE-Aepfel` for German or `İNATÇI` to `inatçı` for Turkish.
Because the CLDR transliteration rules are context sensitive
they know how to correctly convert to `OE-Aepfel`
instead of `Oe-Aepfel` or `OE-AEpfel`.
CLDR also takes the language into account
and knows that the turkish uppercase letter `I`
has the lowercase form `ı` instead of `i`.

Options
-------

All options can be set for the generator object itself `new SlugGenerator($options)`
or overwritten when calling `generate($text, $options)`.
Options can by passed as array or as `SlugOptions` object.

### `delimiter`, default `"-"`

The delimiter can be any string, it is used to separate words.
It gets stripped from the beginning and the end of the slug.

```php
$generator->generate('Hello World!');                         // Result: hello-world
$generator->generate('Hello World!', ['delimiter' => '_']);   // Result: hello_world
$generator->generate('Hello World!', ['delimiter' => '%20']); // Result: hello%20world
```

### `validChars`, default `"a-z0-9"`

Valid characters that are allowed in the slug.
The [range syntax][] is the same as in character classes of regular expressions.
For example `abc`, `a-z0-9äöüß` or `\p{Ll}\-_`.

```php
$generator->generate('Hello World!');                             // Result: hello-world
$generator->generate('Hello World!', ['validChars' => 'A-Z']);    // Result: HELLO-WORLD
$generator->generate('Hello World!', ['validChars' => 'A-Za-z']); // Result: Hello-World
```

### `ignoreChars`, default `"\p{Mn}\p{Lm}"`

Characters that should be completely removed and not replaced with a delimiter.
It uses the same syntax as the `validChars` option.

```php
$generator->generate("don't remove");                         // Result: don-t-remove
$generator->generate("don't remove", ['ignoreChars' => "'"]); // Result: dont-remove
```

### `locale`, default `""`

The locale that should be used for the Unicode transformations.

```php
$generator->generate('Hello Wörld!');                        // Result: hello-world
$generator->generate('Hello Wörld!', ['locale' => 'de']);    // Result: hello-woerld
$generator->generate('Hello Wörld!', ['locale' => 'en_US']); // Result: hello-world
```

### `transforms`, default `Upper, Lower, Latn, ASCII, Upper, Lower`

Internally the slug generator uses [Transform Rules][]
to convert invalid characters to valid ones.
These rules can be customized
by setting the `transforms`, `preTransforms` or `postTransforms` options.
Usually setting `preTransforms` is desired
as it applies the custom transforms
prior to the default ones.

How [Transform Rules][] (like `Lower` or `ASCII`)
and [rule sets][] (like `a > b; c > d;`) work
is documented on the ICU website:
<http://userguide.icu-project.org/transforms>

```php
$generator->generate('Damn 💩!!');                                           // Result: damn
$generator->generate('Damn 💩!!', ['preTransforms' => ['💩 > Ice-Cream']]);  // Result: damn-ice-cream

$generator->generate('©');                                          // Result: c
$generator->generate('©', ['preTransforms' => ['© > Copyright']]);  // Result: copyright
$generator->generate('©', ['preTransforms' => ['Hex']]);            // Result: u00a9
$generator->generate('©', ['preTransforms' => ['Name']]);           // Result: n-copyright-sign
```

[CLDR]: http://cldr.unicode.org/ "Unicode Common Locale Data Repository"
[Composer]: https://getcomposer.org/
[range syntax]: http://www.regular-expressions.info/charclass.html
[Transform Rules]: http://userguide.icu-project.org/transforms/general
[rule sets]: http://userguide.icu-project.org/transforms/general/rules
