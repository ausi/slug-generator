Slug Generator Library
======================

This library provides methods to generate slugs
for URLs, filenames or any other target that has a limited character set.
It’s based on PHPs Transliterator class which uses the data of the [CLDR][]
to transform characters between different scripts (e.g. Cyrillic to Latin)
or types (e.g. upper- to lower-case or from special characters to ASCII).

Usage
-----

```php
$generator = new SlugGenerator;

$generator->generate('Hello Wörld!');  // Output: hello-world
$generator->generate('Καλημέρα');      // Output: kalemera
$generator->generate('фильм');         // Output: film
$generator->generate('富士山');         // Output: fu-shi-shan
$generator->generate('國語');           // Output: guo-yu

// Different valid character set, a specified locale and a delimiter
$generator = new SlugGenerator((new SlugOptions)
    ->setValid('a-zA-Z0-9')
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
like “Ö-Äpfel” to “OE-Aepfel” for German or “İNATÇI” to “inatçı” for turkish.

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

### `valid`, default `"a-z0-9"`

Valid characters that are allowed in the slug.
The [range syntax][] is the same as in character classes of regular expressions.
For example `abc`, `a-z0-9äöüß` or `\p{Ll}\-_`.

```php
$generator->generate('Hello World!');                        // Result: hello-world
$generator->generate('Hello World!', ['valid' => 'A-Z']);    // Result: HELLO-WORLD
$generator->generate('Hello World!', ['valid' => 'A-Za-z']); // Result: Hello-World
```

### `ignore`, default `"\p{Mn}\p{Lm}"`

Characters that should be completely removed and not replaced with a delimiter.
It uses the same syntax as the `valid` option.

```php
$generator->generate("don't remove");                    // Result: don-t-remove
$generator->generate("don't remove", ["ignore' => "'"]); // Result: dont-remove
```

### `locale`, default `""`

The locale that should be used for the Unicode transformations.

```php
$generator->generate('Hello Wörld!');                        // Result: hello-world
$generator->generate('Hello Wörld!', ['locale' => 'de']);    // Result: hello-woerld
$generator->generate('Hello Wörld!', ['locale' => 'en_US']); // Result: hello-world
```

### `transforms`, default `Upper, Lower, Latn, ASCII, Upper, Lower`

Transform rules or rule sets that are used by the `Transliterator`
to convert invalid characters to valid ones.
[Rules][] are for example `Lower` or `ASCII`,
[rule sets][] look like `a > b; c > d;`.

```php
$generator->generate('Damn 💩!!');                                            // Result: damn
$generator->generate('Damn 💩!!', ['transforms' => ['💩 > Ice-Cream']]);      // Result: amn-ce-ream
$generator->generate('Damn 💩!!', ['postTransforms' => ['💩 > Ice\ Cream']]); // Result: damn-ce-ream
$generator->generate('Damn 💩!!', ['preTransforms' => ['💩 > Ice\ Cream']]);  // Result: damn-ice-cream
```

[CLDR]: http://cldr.unicode.org/ "Unicode Common Locale Data Repository"
[Composer]: https://getcomposer.org/
[range syntax]: http://www.regular-expressions.info/charclass.html
[Rules]: http://userguide.icu-project.org/transforms/general
[rule sets]: http://userguide.icu-project.org/transforms/general/rules
