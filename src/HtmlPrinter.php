<?php

/**
 * This file is part of csoellinger/dog-html-printer
 *
 * csoellinger/dog-html-printer is open source software: you can distribute
 * it and/or modify it under the terms of the MIT License
 * (the "License"). You may not use this file except in
 * compliance with the License.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 * @see       https://github.com/CSoellinger/dog-html-printer
 *
 * @copyright Copyright (c) Christopher Söllinger <christopher.soellinger@gmail.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CSoellinger\DogHtmlPrinter;

use CSoellinger\DogHtmlPrinter\Twig\MarkdownRuntimeLoader;
use CSoellinger\DogHtmlPrinter\Twig\TwigUtil;
use Doctrine\Inflector\InflectorFactory;
use Klitsche\Dog\ConfigInterface;
use Klitsche\Dog\Elements\Class_;
use Klitsche\Dog\Elements\Constant;
use Klitsche\Dog\Elements\Interface_;
use Klitsche\Dog\Elements\Trait_;
use Klitsche\Dog\Events\ErrorEmitterTrait;
use Klitsche\Dog\Events\EventDispatcherAwareTrait;
use Klitsche\Dog\Events\ProgressEmitterTrait;
use Klitsche\Dog\Exceptions\PrinterException;
use Klitsche\Dog\PrinterInterface;
use Klitsche\Dog\ProjectInterface;
use Odan\Twig\TwigAssetsExtension;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TemplateWrapper;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WyriHaximus\HtmlCompress\Factory as FactoryHtmlCompress;
use voku\helper\HtmlMin;

use function array_filter;
use function array_merge;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_string;
use function ltrim;
use function md5;
use function preg_replace;
use function realpath;
use function sprintf;
use function strtolower;

use const DIRECTORY_SEPARATOR;

/**
 * HTML printer class for DOG documentation generator.
 */
class HtmlPrinter implements PrinterInterface
{
    use EventDispatcherAwareTrait;
    use ProgressEmitterTrait;
    use ErrorEmitterTrait;

    /**
     * Event dispatcher
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private EventDispatcherInterface $dispatcher;

    /**
     * Printer configuration.
     */
    private ConfigInterface $config;

    /**
     * Environment for rendering.
     */
    private Environment $twig;

    /**
     * Project structure of which the documentation will be generated.
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private ProjectInterface $project;

    /**
     * Symfony filesystem helper class.
     */
    private Filesystem $filesystem;

    private ?string $readme;

    /**
     * Create new printer instance with the given config, event dispatcher and render environment.
     *
     * @param ConfigInterface $config Printer configuration
     * @param ?EventDispatcherInterface $dispatcher Event dispatcher
     * @param Environment $twig Render environment
     */
    final public function __construct(ConfigInterface $config, ?EventDispatcherInterface $dispatcher, Environment $twig)
    {
        $this->config = $config;

        if ($dispatcher !== null) {
            $this->setEventDispatcher($dispatcher);
        }

        $this->twig = $twig;
        $this->filesystem = new Filesystem();
        $this->readme = null;
    }

    /**
     * Print function used by DOG the documentation generator.
     *
     * @param ProjectInterface $project the project structure of which we will generate the documentation
     */
    public function print(ProjectInterface $project): void
    {
        TwigUtil::$project = $project;
        TwigUtil::$config = $this->config;

        $this->project = $project;

        $this->initTwig();

        $this->emitProgressStart(PrinterInterface::PROGRESS_TOPIC, $this->countFilesToPrint());
        $this->render();
        $this->emitProgressFinish(PrinterInterface::PROGRESS_TOPIC);
    }

    /**
     * Initialize twig with all extensions, filters and functions.
     */
    private function initTwig(): void
    {
        // If debug is enabled we will include the twig debug extensions
        if ($this->config->isDebugEnabled()) {
            $this->twig->enableDebug();
            $this->twig->enableAutoReload();
            $this->twig->addExtension(new DebugExtension());
        }

        // Create public assets dir before assets extension initialization
        $this->filesystem->mkdir($this->config->getOutputDir() . DIRECTORY_SEPARATOR . 'assets', 0750);

        // Assets extension
        $this->twig->addExtension(new TwigAssetsExtension($this->twig, [
            'path' => $this->config->getOutputDir() . DIRECTORY_SEPARATOR . 'assets',
            'url_base_path' => 'assets/',
            'cache_path' => $this->config->getCacheDir() . '/dog-' . md5($this->config->getOutputDir()),
            'cache_name' => 'assets-cache',
            'minify' => isset($this->config->getPrinterConfig()['minifyCssAndJs']) === false ||
                (
                    isset($this->config->getPrinterConfig()['minifyCssAndJs']) === true &&
                    (bool) $this->config->getPrinterConfig()['minifyCssAndJs'] === true
                ) ? 1 : 0,
        ]));

        // Markdown extension for MD styled docs
        $this->twig->addExtension(new MarkdownExtension());
        $this->twig->addRuntimeLoader(new MarkdownRuntimeLoader());

        // Custom filter
        $this->twig->addFilter(new TwigFilter('elementsByNamespace', [
            $this->project->getIndex(),
            'getElementsByNamespace',
        ]));
        $this->twig->addFilter(new TwigFilter('elementByFqsen', [
            $this->project->getIndex(),
            'getElementByFqsen',
        ]));
        $this->twig->addFilter(new TwigFilter('fqsenIndex', [
            $this->project->getIndex(),
            'getFqsenIndex',
        ]));
        $this->twig->addFilter(new TwigFilter('pluralize', [(InflectorFactory::create()->build()), 'pluralize']));
        $this->twig->addFilter(new TwigFilter('urlize', [(InflectorFactory::create()->build()), 'urlize']));
        $this->twig->addFilter(new TwigFilter('filterVisibility', [TwigUtil::class, 'filterVisibility']));
        $this->twig->addFilter(new TwigFilter('shortenFqsen', [TwigUtil::class, 'shortenFqsen']));
        $this->twig->addFilter(new TwigFilter('linkFqsen', [TwigUtil::class, 'linkFqsen']));
        $this->twig->addFilter(new TwigFilter('elementFilename', [TwigUtil::class, 'getElementFilename']));
        $this->twig->addFilter(new TwigFilter('arrayListToTree', [TwigUtil::class, 'arrayListToTree']));
        $this->twig->addFilter(new TwigFilter('subNamespaces', [TwigUtil::class, 'getSubNamespaces']));
        $this->twig->addFilter(new TwigFilter('resolveDocblockLinks', [TwigUtil::class, 'resolveDocblockLinks']));
        $this->twig->addFilter(new TwigFilter(
            'linkify',
            fn (string $value) => preg_replace(
                '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@',
                '<a href="$1" target="_blank">$1</a>',
                $value,
            ),
        ));
        $this->twig->addFilter(new TwigFilter('highlightSource', [TwigUtil::class, 'highlightSource']));
        $this->twig->addFilter(new TwigFilter('transformPrismHtml', [TwigUtil::class, 'transformPrismHtml']));

        // Custom functions
        $this->twig->addFunction(new TwigFunction('elementsList', [TwigUtil::class, 'getElementsList']));
        $this->twig->addFunction(new TwigFunction('rootNamespace', [TwigUtil::class, 'getRootNamespace']));
        $this->twig->addFunction(new TwigFunction('markdownToc', [TwigUtil::class, 'getMarkdownToc']));
    }

    /**
     * For progress we will count all number of files to render.
     */
    private function countFilesToPrint(): int
    {
        // index
        $filesToPrint = 1;

        // Readme (if set)
        $filesToPrint += isset($this->config->getPrinterConfig()['readme']) === true ? 1 : 0;

        // namespace
        $filesToPrint += count($this->project->getNamespaces()) ? count($this->project->getNamespaces()) + 1 : 0;
        // constant
        $filesToPrint += count($this->getGlobalConstants()) ? 1 : 0;
        // function
        $filesToPrint += count($this->project->getFunctions()) ? 1 : 0;

        // classes (one overview file and for every object two more files for detail and source)
        $filesToPrint += $this->countElementFiles($this->project->getClasses());

        // interfaces (one overview file and for every object two more files for detail and source)
        $filesToPrint += $this->countElementFiles($this->project->getInterfaces());

        // traits (one overview file and for every object two more files for detail and source)
        $filesToPrint += $this->countElementFiles($this->project->getTraits());

        return $filesToPrint;
    }

    /**
     * Count how much files will be generated for elements list.
     *
     * @param Class_[]|Interface_[]|Trait_[] $elements Elements list
     */
    private function countElementFiles(array $elements): int
    {
        $countElements = count((array) TwigUtil::filterVisibility($elements)) ?: 0;

        if ($countElements > 0) {
            if (
                isset($this->config->getPrinterConfig()['includeSource']) &&
                (bool) $this->config->getPrinterConfig()['includeSource'] === true
            ) {
                $countElements = $countElements * 2;
            }

            ++$countElements;
        }

        return $countElements;
    }

    /**
     * Render all the stuff to files.
     */
    private function render(): void
    {
        $this
            // Index page
            ->renderFile('index.html.twig', 'index.html', ['readme' => $this->getReadme()])
            // Readme
            ->renderReadme()
            // Namespaces
            ->renderNamespaces()
            // Classes
            ->renderObjects($this->project->getClasses())
            // Interfaces
            ->renderObjects($this->project->getInterfaces())
            // Traits
            ->renderObjects($this->project->getTraits())
            // Constants
            ->renderConstants()
            // Functions
            ->renderFunctions();
    }

    /**
     * Render readme file if it is available.
     */
    private function renderReadme(): self
    {
        $readme = $this->getReadme();

        if ($readme === null) {
            return $this;
        }

        return $this->renderFile('readme.html.twig', 'readme.html');
    }

    /**
     * Render namespaces overview and detail page.
     */
    private function renderNamespaces(): self
    {
        $namespaces = $this->project->getNamespaces();

        if (count($namespaces) <= 0) {
            return $this;
        }

        foreach ($namespaces as $namespace) {
            $filename = TwigUtil::getElementFilename($namespace);

            $this->renderFile('namespace.html.twig', $filename, ['namespace' => $namespace]);
        }

        return $this->renderFile('namespaces.html.twig', 'namespaces.html');
    }

    /**
     * Render class, interface and trait objects overview, detail and source page.
     *
     * @param Class_[]|Interface_[]|Trait_[] $objects objects to render
     */
    private function renderObjects(array $objects): self
    {
        $objects = (array) TwigUtil::filterVisibility($objects);

        if (count($objects) <= 0) {
            return $this;
        }

        $elementType = $objects[0]->getElementType();

        foreach ($objects as $object) {
            $filename = TwigUtil::getElementFilename($object);
            $this->renderFile('object.html.twig', $filename, ['object' => $object]);

            if (
                isset($this->config->getPrinterConfig()['includeSource']) &&
                (bool) $this->config->getPrinterConfig()['includeSource'] === true
            ) {
                $filename = 'source' . ltrim($filename, strtolower($elementType));
                $this->renderFile('source.html.twig', $filename, ['object' => $object]);
            }
        }

        return $this->renderFile(
            'objects.html.twig',
            strtolower((string) (InflectorFactory::create()->build())->pluralize($elementType)) . '.html',
            [
                'objects' => $objects,
            ],
        );
    }

    /**
     * Render global defined constants.
     */
    private function renderConstants(): self
    {
        $constants = $this->getGlobalConstants();

        if (count($constants) <= 0) {
            return $this;
        }

        return $this->renderFile('constants.html.twig', 'constants.html');
    }

    /**
     * Render all global functions.
     */
    private function renderFunctions(): self
    {
        $functions = $this->project->getFunctions();

        if (count($functions) <= 0) {
            return $this;
        }

        return $this->renderFile('functions.html.twig', 'functions.html');
    }

    /**
     * Render a twig template and optionally save it(Default to YES). Return the
     * output if we need it otherwise too.
     *
     * @param string|TemplateWrapper $template
     * @param array<string,mixed> $context
     *
     * @throws PrinterException|Throwable
     */
    private function renderFile($template, string $fileName, array $context = []): self
    {
        $context = array_merge(
            [
                'context' => 'project',
                'project' => $this->project,
                'config' => $this->config,
                'currentFile' => $fileName,
                'readme' => $this->getReadme(),
            ],
            $context,
        );
        $output = '';

        TwigUtil::$currentFileName = $fileName;

        $this->emitProgress(PrinterInterface::PROGRESS_TOPIC, 1, $fileName);

        try {
            $template = $this->twig->load($template);
            $output = $template->render($context);
        } catch (Throwable $exception) {
            $this->emitError(
                new PrinterException(
                    sprintf(
                        'Failed to print template %s for file %s. Reason: %s',
                        is_string($template) ? $template : $template->getTemplateName(),
                        $fileName,
                        $exception->getMessage(),
                    ),
                    10,
                    $exception,
                ),
                [
                    'template' => $template,
                    'filename' => $fileName,
                ],
            );
        }

        if (
            isset($this->config->getPrinterConfig()['minifyHtml']) &&
            (bool) $this->config->getPrinterConfig()['minifyHtml'] === true
        ) {
            $htmlMin = new HtmlMin();
            $htmlMin
                ->doRemoveHttpPrefixFromAttributes()
                ->doOptimizeAttributes()
                ->doRemoveComments()
                ->doRemoveDeprecatedTypeFromScriptTag();

            $output = (FactoryHtmlCompress::constructSmallest()->withHtmlMin($htmlMin))->compress($output);
        }

        try {
            $this->saveFile($fileName, $output);
        } catch (Throwable $exception) {
            $this->emitError(
                new PrinterException(
                    sprintf('Failed to save file %s. Reason: %s', $fileName, $exception->getMessage()),
                    15,
                    $exception,
                ),
                [
                    'filename' => $fileName,
                ],
            );
        }

        TwigUtil::$currentFileName = null;

        return $this;
    }

    /**
     * Save a new file inside the given output dir.
     *
     * @param string $fileName Name of the file, including extension
     * @param string $content File content to save
     */
    private function saveFile(string $fileName, string $content): self
    {
        $this->filesystem->mkdir(dirname($this->config->getOutputDir() . DIRECTORY_SEPARATOR . $fileName));
        $this->filesystem->appendToFile($this->config->getOutputDir() . DIRECTORY_SEPARATOR . $fileName, $content);

        return $this;
    }

    /**
     * Get all global constants.
     *
     * @return Constant[]
     */
    private function getGlobalConstants()
    {
        return array_filter(
            $this->project->getConstants(),
            fn (Constant $constant): bool => $constant->isClassConstant() === false,
        );
    }

    private function getReadme(): ?string
    {
        if (!isset($this->readme) && isset($this->config->getPrinterConfig()['readme']) === true) {
            $readmePath =
                $this->config->getWorkingDir() .
                DIRECTORY_SEPARATOR .
                (string) $this->config->getPrinterConfig()['readme'];

            if (file_exists($readmePath) === true) {
                $this->readme = file_get_contents($readmePath) ?: null;
            }
        }

        return $this->readme;
    }

    public static function getPrinterRootDir(): string
    {
        return (string) realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    }

    /**
     * Create new instance for HtmlPrinter and return it. Initialize loader for
     * templates and twig environment with given config (cache,..).
     *
     * @param ConfigInterface $config Loaded configs from the user yaml file like cache dir
     * @param EventDispatcherInterface $dispatcher dispatcher for events
     */
    public static function create(ConfigInterface $config, EventDispatcherInterface $dispatcher): self
    {
        $templatesPath = self::getPrinterRootDir() . DIRECTORY_SEPARATOR .
            'resources' . DIRECTORY_SEPARATOR .
            'templates';

        if (isset($config->getPrinterConfig()['templatesPath'])) {
            $configTemplatesPath = (string) $config->getPrinterConfig()['templatesPath'];

            if ($configTemplatesPath[0] === '/') {
                $templatesPath = $configTemplatesPath;
            } else {
                $configTemplatesPath = $config->getWorkingDir() . DIRECTORY_SEPARATOR . $configTemplatesPath;
            }
        }

        $loader = new FilesystemLoader($templatesPath);
        $loader->addPath($config->getWorkingDir(), 'workingDir');
        $loader->addPath(self::getPrinterRootDir(), 'printerDir');

        $twig = new Environment(
            $loader,
            [
                'cache' => $config->getCacheDir() . '/dog-' . md5($config->getOutputDir()),
                'autoescape' => false,
            ],
        );

        return new static($config, $dispatcher, $twig);
    }
}
