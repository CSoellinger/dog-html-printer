# csoellinger/dog-html-printer

**HTML printer for [Dog](https://github.com/klitsche/dog), a simple code documentation generator.**

[![Source Code](https://img.shields.io/badge/source-csoellinger/dog--html--printer-blue.svg?style=flat-square)](https://github.com/csoellinger/dog-html-printer)
[![Download Package](https://img.shields.io/packagist/v/csoellinger/dog-html-printer.svg?style=flat-square&label=release)](https://packagist.org/packages/csoellinger/dog-html-printer)
[![PHP Programming Language](https://img.shields.io/packagist/php-v/csoellinger/dog-html-printer.svg?style=flat-square&colorB=%238892BF)](https://php.net)
[![Read License](https://img.shields.io/packagist/l/csoellinger/dog-html-printer.svg?style=flat-square&colorB=darkcyan)](https://github.com/csoellinger/dog-html-printer/blob/master/LICENSE)
[![Package downloads on Packagist](https://img.shields.io/packagist/dt/csoellinger/dog-html-printer.svg?style=flat-square&colorB=darkmagenta)](https://packagist.org/packages/csoellinger/dog-html-printer/stats)
<!-- [![Build Status](https://travis-ci.com/csoellinger/dog-html-printer.svg?branch=main)](https://travis-ci.com/csoellinger/dog-html-printer) -->
<!-- [![Build Status](https://img.shields.io/github/workflow/status/csoellinger/dog-html-printer/CI?label=CI&logo=github&style=flat-square)](https://github.com/csoellinger/dog-html-printer/actions?query=workflow%3ACI) -->
<!-- [![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/csoellinger/dog-html-printer?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/csoellinger/dog-html-printer) -->
<!-- [![Psalm Type Coverage](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fshepherd.dev%2Fgithub%2Fcsoellinger%2Fdog-html-printer%2Fcoverage)](https://shepherd.dev/github/csoellinger/dog-html-printer) -->
<!-- [![Chat with the maintainers](https://img.shields.io/badge/phpc.chat-%23csoellinger-darkslateblue?style=flat-square)](https://phpc.chat/channel/csoellinger) -->

- [About](#about)
- [Installation](#installation)
- [Usage](#usage)
- [Documentation](#documentation)
- [Contributing](#contributing)
  - [Known Issues](#known-issues)
  - [ToDo](#todo)
- [Copyright and License](#copyright-and-license)

## About

A HTML printer for [Dog PHP Documentation Generator](https://github.com/klitsche/dog). It is heavily inspired by Doctum, phpDox and other good projects. The printer is Work-In-Progress at the moment but it is already very configurable and has a nice look with many features like: Tree Navigation, Source Code implementation, PhpLoc overview, readme renderer and some more are planned. Take a look at the todo section.

## Installation

First you need to install [Dog PHP Documentation Generator](https://github.com/klitsche/dog). After it install this package as a dependency using [Composer](https://getcomposer.org).

```bash
composer require --dev csoellinger/dog-html-printer
```

## Usage

After you have installed [Dog](https://github.com/klitsche/dog) and [Dog HTML printer](https://github.com/csoellinger/dog-html-printer) you can customize your *.dog.yml* to use the new printer class.

```yaml
# Most configurations are by the dog package itself.

# Title
title: 'Dog HTML printer'

# Relative or absolute paths to source files - plus patterns to include or exclude path pr files
srcPaths:
  'src':
    '/.*\.php$/': true

# Configure enrichers to add extra data to project or element items
# phploc is supported by DogHtmlPrinter
enrichers:
    phploc:
        class: Klitsche\Dog\Enrichers\PHPLOC\PHPLOCEnricher
        file: phploc.json

# Set the printer class to DogHtmlPrinter
printerClass: 'CSoellinger\DogHtmlPrinter\HtmlPrinter'

# Printer configuration
printerConfig:
    # Set a custom template path. If you want do this, best way would be to copy the resources/templates dir
    # from this package to you project dir and then customize the templates.
    # templatesPath: 'resources/templates'

    # If you want add a readme file
    readme: 'README.md'

    # Include source code
    includeSource: true

    # Ignore code marked as @internal
    ignoreInternal: true

    # Ignore private things (methods, properties,...)
    ignorePrivate: true

    # Minify CSS and JS at output
    minifyCssAndJs: true

    # Minify HTML at output. Slows down the build!!
    minifyHtml: false

# Relative or absolute path to output directory
outputDir: 'docs/html'

# Optional cache dir
cacheDir: 'build/cache'

# Enable or disable debug mode - helps when tweaking templates
debug: false
```

Right after customizing the configuration you can start building your documentation.

```bash
vendor/bin/dog
```

## Documentation

[https://csoellinger.github.io/dog-html-printer](https://csoellinger.github.io/dog-html-printer)

## Contributing

Contributions are welcome! To contribute, please familiarize yourself with
[CONTRIBUTING.md](CONTRIBUTING.md).

If you found a bug or have an idea feel free to post it here on github.

### Known Issues
- [ ] Linking inside readme to other markdown files causes problems
- [ ] Collapse tree navigation also selects item

### ToDo

- [ ] Generate local static search
- [ ] Include coverage data to printer output
- [ ] Include testing data to printer output
- [ ] Project testing
- [ ] Mobile optimization
- [ ] Replace or customize bootstrap with only needed stuff
- [ ] Sub-Dir layout config instead of flat dir output.
- [ ] Optimize
  - Strip bootstrap down to only needed components
  - Some code parts are a bit "hacky"
- [ ] Theming
  - Append custom css via printer config
  - Theming system to use a new theme without writing a new printer class (like "custom templates path")

## Copyright and License

The csoellinger/dog-html-printer library is copyright © [Christopher Söllinger](https://github.com/CSoellinger)
and licensed for use under the terms of the
MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
