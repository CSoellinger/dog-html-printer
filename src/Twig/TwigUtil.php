<?php

/**
 * This file is part of csoellinger/dog-html-printer.
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

namespace CSoellinger\DogHtmlPrinter\Twig;

use Highlight\Highlighter as Hl;
use Klitsche\Dog\ConfigInterface;
use Klitsche\Dog\Elements\Class_;
use Klitsche\Dog\Elements\Constant;
use Klitsche\Dog\Elements\ElementInterface;
use Klitsche\Dog\Elements\Function_;
use Klitsche\Dog\Elements\Interface_;
use Klitsche\Dog\Elements\Trait_;
use Klitsche\Dog\ProjectInterface;
use ReflectionClass;
use phpDocumentor\Reflection\Fqsen;

use function array_column;
use function array_diff_key;
use function array_filter;
use function array_flip;
use function array_keys;
use function array_merge;
use function array_reverse;
use function array_search;
use function array_slice;
use function asort;
use function class_exists;
use function count;
use function end;
use function explode;
use function html_entity_decode;
use function implode;
use function in_array;
use function method_exists;
use function preg_grep;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function reset;
use function sprintf;
use function str_replace;
use function strcmp;
use function strlen;
use function strpos;
use function strtolower;
use function substr_count;
use function trim;
use function uksort;
use function usort;

use const PREG_SET_ORDER;

class TwigUtil
{
    /**
     * Project structure of which the documentation will be generated.
     */
    public static ProjectInterface $project;

    /**
     * Dog configuration.
     */
    public static ConfigInterface $config;

    /**
     * Helper variable.
     */
    public static ?string $currentFileName = null;

    /**
     * @var array<string,?ElementInterface> Fqsen index
     */
    private static array $fqsenIndex = [];

    /**
     * Get a Table-Of-Content(toc) array from markdown.
     *
     * @param string|null $markdown Text
     * @param int         $minLevel Min heading level
     * @param int         $maxLevel Max heading level
     *
     * @return array<int,array<string,?string>>
     *
     * @psalm-return list<array{id:?string,lvl:int,name:string,parent_id:?string}>
     */
    public static function getMarkdownToc(?string $markdown = null, int $minLevel = 1, int $maxLevel = 6)
    {
        if (!$markdown) {
            return [];
        }

        // Remove code blocks cause they can have heading symbols (#)
        $markdown = (string) preg_replace('/(```[a-z]*\n[\s\S]*?\n```)/', '', $markdown);
        $headings = preg_grep('/^\s*\#{' . $minLevel . ',' . $maxLevel . '}\ /', explode("\n", $markdown));

        if ((bool) $headings === false) {
            return [];
        }

        // Fetch all lines with headings
        /** @var array<int,string> $headings */
        $headings = $headings;
        $headings = array_filter($headings);
        $data = [];

        foreach ($headings as $heading) {
            $lvl = strlen(explode(' ', $heading)[0]) - $minLevel + 1;
            $parentId = '';

            if ($lvl > 1) {
                foreach (array_reverse($data) as $item) {
                    if ($item['lvl'] < $lvl) {
                        $parentId = (string) $item['id'];

                        break;
                    }
                }
            }

            $data[] = [
                'id' => preg_replace('/\s/', '', trim($heading, '#')),
                'parent_id' => $parentId ?: null,
                'name' => trim($heading, "# \t\n\r\0\x0B"),
                'lvl' => (int) $lvl,
            ];
        }

        return $data;
    }

    /**
     * Highlight source code, powered by highlight.php.
     *
     * @param string|null $source Source code
     * @param string      $lang   Source language. Value "auto" selects the language for you, but takes a lot of
     *                            performance
     */
    public static function highlightSource(?string $source, string $lang = 'auto'): ?string
    {
        if (!$source) {
            return $source;
        }

        $hl = new Hl();

        if ($lang === 'auto') {
            $highlighted = $hl->highlightAuto($source);
        } else {
            $highlighted = $hl->highlight($lang, $source);
        }

        // return "<div class=\"text-break hljs {$highlighted->language}\">" .
        //     trim((string) $highlighted->value) .
        //     '</div>';
        // return "<code class=\"hljs {$highlighted->language}\">" .
        //     trim((string) $highlighted->value) .
        //     '</code>';
        return "<code class=\"hljs {$highlighted->language}\">" .
            trim((string) $highlighted->value) .
            '</code>';
    }

    /**
     * Filter elements which are private or marked as (@)internal.
     *
     * @param Class_[]|ElementInterface[]|Interface_[]|Trait_[]|null $value
     *
     * @return Class_[]|ElementInterface[]|Interface_[]|Trait_[]|null
     */
    public static function filterVisibility(?array $value = null)
    {
        if (!$value) {
            return $value;
        }

        $filterInternal = (
            isset(self::$config->getPrinterConfig()['ignoreInternal']) &&
            (bool) self::$config->getPrinterConfig()['ignoreInternal'] === true
        );

        $filterPrivate = (
            isset(self::$config->getPrinterConfig()['ignorePrivate']) &&
            (bool) self::$config->getPrinterConfig()['ignorePrivate'] === true
        );

        if ($filterInternal === true) {
            // Filter elements which have an @internal doc block
            $value = array_filter(
                $value,
                /**
                 * @psalm-suppress MixedMethodCall
                 */
                fn (?object $el) => (
                    $el && method_exists($el, 'getDocBlock') === true && (
                        !$el->getDocBlock() || (
                            $el->getDocBlock() &&
                                $el->getDocBlock()->hasTag('internal') === false
                        )
                    )
                ) ||
                $el && method_exists($el, 'getDocBlock') === false,
            );
        }

        if ($filterPrivate === true) {
            // Filter elements which are declared with private visibility
            $value = array_filter(
                $value,
                /**
                 * @psalm-suppress MixedMethodCall
                 */
                fn (?object $el) => (
                    $el && method_exists($el, 'getVisibility') && $el->getVisibility()->__toString() !== 'private'
                ) ||
                $el && method_exists($el, 'getVisibility') === false,
            );
        }

        return $value;
    }

    /**
     * Get all elements as array list. Needed for javascript json data used by
     * typeahead search and tree view.
     *
     * @param array<string> $unsetFields
     *
     * @return array<int,array<string,bool|int|string|null>>
     */
    public static function getElementsList(array $unsetFields = []): array
    {
        $data = [];
        $namespaces = self::$project->getNamespaces();
        $globalNamespace = null;

        if (count($namespaces) <= 0) {
            return $data;
        }

        asort($namespaces);

        if (reset($namespaces)->__toString() === '\\') {
            /** @var Fqsen $globalNamespace */
            $globalNamespace = reset($namespaces);
            $namespaces = array_slice($namespaces, 1);
        }

        foreach ($namespaces as $namespace) {
            $link = self::getElementFilename($namespace);
            $record = [
                'id' => $namespace->__toString(),
                'name' => $namespace->getName(),
                'elementType' => 'namespace',
                'type' => 'folder',
                'open' => (self::$currentFileName === $link),
                'selected' => (self::$currentFileName === $link),
                'parent_id' => reset($namespaces) === $namespace ?
                    0 :
                    implode('\\', array_slice(explode('\\', $namespace->__toString()), 0, -1)),
                'link' => $link,
            ];
            $record = array_diff_key($record, array_flip($unsetFields));

            $data[] = $record;
            $data = array_merge($data, self::getElementsListByNamespace($namespace, $unsetFields));
        }

        if ($globalNamespace !== null) {
            $link = self::getElementFilename($globalNamespace);
            $record = [
                'id' => 'Global',
                'name' => '[ Global Namespace ]',
                'elementType' => 'namespace',
                'type' => 'folder',
                'open' => (self::$currentFileName === $link),
                'selected' => (self::$currentFileName === $link),
                'parent_id' => 0,
                'link' => $link,
            ];
            $record = array_diff_key($record, array_flip($unsetFields));

            $data[] = $record;
            $data = array_merge($data, self::getElementsListByNamespace($globalNamespace, $unsetFields));
        }

        // Check if one entry is selected
        $selectedIndex = array_search(true, array_column($data, 'selected'));

        if ($selectedIndex !== false) {
            // If so we will set all parents to open:true for our tree view
            /**
             * @var array $selected
             */
            $selected = $data[$selectedIndex];

            while ($selected) {
                $key = array_search($selected['parent_id'], array_column($data, 'id'), true);

                if ($key === false) {
                    $selected = null;

                    continue;
                }

                /** @var int $key */
                $key = $key;
                $data[$key]['open'] = true;

                /**
                 * @var array $selected
                 */
                $selected = $data[$key];
            }
        }

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        usort($data, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return $data;
    }

    /**
     * Shorten fqsen to element name.
     */
    public static function shortenFqsen(?string $value = null): ?string
    {
        if (!$value) {
            return $value;
        }

        $values = explode('|', $value);

        foreach ($values as $fqsen) {
            if (substr_count($fqsen, '\\') >= 2) {
                $fqsenParts = explode('\\', $fqsen);
                $name = end($fqsenParts);

                $value = preg_replace(
                    '/(' . preg_quote($fqsen) . ')/',
                    $name,
                    (string) $value,
                );
            }
        }

        return $value;
    }

    /**
     * Undocumented function.
     *
     * @return Fqsen[]
     */
    public static function getSubNamespaces(?Fqsen $value = null): array
    {
        if (!$value || $value->__toString() === '\\') {
            return [];
        }

        $subNamespaces = [];
        $namespaces = self::$project->getNamespaces();

        if (count($namespaces) <= 0) {
            return $subNamespaces;
        }

        asort($namespaces);

        if (reset($namespaces)->__toString() === '\\') {
            $namespaces = array_slice($namespaces, 1);
        }

        foreach ($namespaces as $namespace) {
            if (strlen($namespace->__toString()) <= strlen($value->__toString())) {
                continue;
            }

            if (strpos(trim(str_replace($value->__toString(), '', $namespace->__toString()), '\\()'), '\\') !== false) {
                continue;
            }

            $subNamespaces[] = $namespace;
        }

        return $subNamespaces;
    }

    public static function transformPrismHtml(?string $value = null): ?string
    {
        if (!$value) {
            return $value;
        }

        $re = '/(<pre>[\s]*<code.*class=".*language-([\w]*).*".*>)([^>]*)(<\/code><\/pre>)/m';
        $matches = [];
        preg_match_all($re, $value, $matches, PREG_SET_ORDER, 0);

        foreach ($matches as $match) {
            $source = trim(html_entity_decode($match[3]));
            $highlightedSource = self::highlightSource($source, $match[2]);
            $value = str_replace(trim($match[0]), (string) $highlightedSource, $value);
        }

        return $value;
    }

    /**
     * Get the first defined namespace as root namespace. Global namespace will be removed before.
     */
    public static function getRootNamespace(): ?Fqsen
    {
        $namespaces = self::$project->getNamespaces();

        // If there is only global stuff defined we will return null
        if (count($namespaces) <= 0) {
            return null;
        }

        asort($namespaces);

        // Remove global workspace if there is one
        if (reset($namespaces)->__toString() === '\\') {
            $namespaces = array_slice($namespaces, 1);
        }

        // If there is only global stuff defined we will return null
        if (count($namespaces) <= 0) {
            return null;
        }

        // Otherwise we return the first namespace element as root namespace
        return reset($namespaces);
    }

    /**
     * Try to convert fqsen to hyperlinks.
     */
    public static function linkFqsen(?string $value = null): ?string
    {
        if (!$value) {
            return $value;
        }

        $values = explode('|', $value);
        $fqsenKeys = array_keys(self::getFqsenIndex());

        foreach ($values as $fqsen) {
            $fqsen = trim($fqsen, '[]?');

            if (in_array($fqsen, $fqsenKeys) === true) {
                /** @var ElementInterface|Fqsen|null $element */
                $element = self::getFqsenIndex()[$fqsen];

                if ($element === null) {
                    continue;
                }

                $link = '';

                switch (true) {
                    case $element instanceof Function_:
                        /** @var Function_ $element */
                        $element = $element;
                        $link = sprintf(
                            '<a href="%s">%s</a>',
                            'functions.html#' . (string) $element->getName(),
                            (string) $element->getName(),
                        );

                        break;
                    case $element instanceof Constant:
                        $fileName = 'constants.html#' . (string) $element->getName();

                        /** @var Constant $element */
                        $element = $element;
                        if ($element->isClassConstant()) {
                            $fileName = self::getElementFilename($element->getOwner()) .
                                '#constant-' . (string) $element->getName();
                        }

                        $link = sprintf(
                            '<a href="%s" title="%s">%s</a>',
                            $fileName,
                            trim($element->getFqsen()->__toString(), '\\()'),
                            // '',
                            (string) $element->getName(),
                        );

                        break;
                    case $element instanceof Class_:
                    case $element instanceof Interface_:
                    case $element instanceof Trait_:
                        /** @var Class_|Interface_|Trait_ $element */
                        $element = $element;
                        /** @var string $name */
                        $name = $element->getName();
                        $link = sprintf(
                            '<a href="%s" title="%s">%s</a>',
                            self::getElementFilename($element),
                            trim($element->getFqsen()->__toString(), '\\()'),
                            $name,
                        );

                        break;
                    case $element instanceof Fqsen:
                        /** @var Fqsen $element */
                        $element = $element;
                        $link = sprintf(
                            '<a href="%s">%s</a>',
                            self::getElementFilename($element),
                            trim($element->__toString(), '\\()') ?: 'global',
                        );

                        break;
                    default:
                        $link = (string) $element->getName();
                }

                $value = preg_replace('/(' . preg_quote($fqsen) . ')/', $link, (string) $value);

                continue;
            }

            // Check for php classes
            if (class_exists($fqsen)) {
                $reflector = new ReflectionClass($fqsen);

                if (
                    !$reflector->getFileName() &&
                    !$reflector->getNamespaceName() &&
                    !$reflector->isUserDefined() &&
                    strtolower($reflector->getName()) !== 'stdclass'
                ) {
                    // Looks like an PHP class
                    $phpLink = 'https://www.php.net/class.' . strtolower($reflector->getName());
                    $value = preg_replace(
                        '/(' . preg_quote($fqsen) . ')/',
                        "<a href=\"{$phpLink}\" target=\"_blank\" data-php-link=\"true\">{$fqsen}</a>",
                        (string) $value,
                    );

                    continue;
                }
            }

            if (substr_count($fqsen, '\\') >= 2) {
                $fqsenParts = explode('\\', $fqsen);
                $name = end($fqsenParts);

                $value = preg_replace(
                    '/(' . preg_quote($fqsen) . ')/',
                    "<span title=\"{$fqsen}\">{$name}</span>",
                    (string) $value,
                );

                continue;
            }
        }

        // $value = str_replace('|', '&nbsp;|&nbsp;', $value);

        return $value;
    }

    /**
     * Turn an flat array with parents to a tree like array with childs.
     *
     * @param array<mixed> $flatList
     *
     * @return array<mixed>
     */
    public static function arrayListToTree(
        array $flatList,
        string $idKey = 'id',
        string $parentKey = 'parent_id',
        string $siblingKey = 'children'
    ): array {
        /** @var array<string,array> $grouped */
        $grouped = [];

        /** @var array<string,array> $node */
        foreach ($flatList as $node) {
            $key = $node[$parentKey] ?: 0;
            $grouped[$key][] = $node;
        }

        /** @var callable $fnBuilder */
        $fnBuilder = function (array $siblings) use (&$fnBuilder, $grouped, $idKey, $siblingKey): array {
            /** @var array<mixed> $sibling */
            foreach ($siblings as $k => $sibling) {
                $id = (string) $sibling[$idKey];

                if (isset($grouped[$id])) {
                    /** @psalm-suppress MixedFunctionCall */
                    $sibling[$siblingKey] = (array) $fnBuilder($grouped[$id]);
                }

                $siblings[$k] = $sibling;
            }

            return $siblings;
        };

        return (array) $fnBuilder($grouped[0]);
    }

    /**
     * Try to resolve {@link} from doc blocks. For the moment only extern urls
     * are working.
     */
    public static function resolveDocblockLinks(?string $value = null): ?string
    {
        if (!$value) {
            return $value;
        }

        $match = [];
        preg_match('/{@link\s(.*)}/', $value, $match);

        if (count($match) <= 0) {
            return $value;
        }

        $linkMatch = explode(' ', $match[1]);

        if (!isset($linkMatch[1])) {
            $linkMatch[1] = $linkMatch[0];
        }

        $urlRegex = '(((ftp|http|https):\/\/)|(\/)|(..\/))(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?';

        if (preg_match('/' . $urlRegex . '/', $linkMatch[0])) {
            $link = sprintf('<a href="%s" target="_blank">%s</a>', $linkMatch[0], $linkMatch[1]);

            return str_replace($match[0], $link, $value);
        }

        return $value;
    }

    /**
     * Get filename by an element.
     *
     * @param ElementInterface|Fqsen|null $element
     */
    public static function getElementFilename($element, string $extension = 'html'): string
    {
        $filename = '';

        if (!$element) {
            return $filename;
        }

        switch (true) {
            case $element instanceof Class_:
            case $element instanceof Interface_:
            case $element instanceof Trait_:
                /** @var Class_|Interface_|Trait_ $element */
                $element = $element;
                $filename = strtolower(
                    $element->getElementType() . '_' .
                    str_replace('\\', '_', trim((string) $element->getFqsen(), '\\()')),
                );

                break;
            case $element instanceof Function_:
                $filename = 'functions.html#function-' . (string) $element->getName();

                break;
            case $element instanceof Fqsen:
                $namespaceName = trim($element->__toString(), '\\()') ?: 'global';
                $filename = 'namespace_' .
                    strtolower(str_replace('\\', '_', $namespaceName));

                break;
            default:
                $filename = 'index';

                break;
        }

        return $filename . '.' . $extension;
    }

    /**
     * Undocumented function.
     *
     * @param string[] $unsetFields
     *
     * @return array<int,array<string,bool|string|null>>
     */
    private static function getElementsListByNamespace(Fqsen $ns, array $unsetFields = []): array
    {
        $data = [];
        $elements = self::filterVisibility(self::$project->getIndex()->getElementsByNamespace($ns));

        if (!$elements) {
            return $data;
        }

        foreach ($elements as $element) {
            if (in_array($element->getElementType(), [Class_::TYPE, Interface_::TYPE, Trait_::TYPE]) === false) {
                continue;
            }

            /** @var Class_|Interface_|Trait_ $element */
            $element = $element;
            $fqsen = $element->getFqsen();
            $id = $fqsen->__toString();

            $link = self::getElementFilename($element);
            $record = [
                'id' => $id,
                'name' => $element->getName(),
                'elementType' => strtolower($element->getElementType()),
                'type' => 'file',
                'open' => (self::$currentFileName === $link),
                'selected' => (self::$currentFileName === $link),
                'parent_id' => $ns->__toString() === '\\' ? 'Global' : $ns->__toString(),
                'link' => $link,
            ];
            $record = array_diff_key($record, array_flip($unsetFields));

            $data[] = $record;
        }

        return $data;
    }

    /**
     * Get an index of all fqsen.
     *
     * @return array<string,?ElementInterface>
     */
    private static function getFqsenIndex(): array
    {
        if (count(self::$fqsenIndex) <= 0) {
            $index = self::$project->getIndex();
            /** @var array<string,?ElementInterface> $fqsenIndex */
            $fqsenIndex = $index->getFqsenIndex();

            uksort(
                $fqsenIndex,
                fn (string $a, string $b) => strlen($b) - strlen($a) ?: strcmp($a, $b),
            );

            self::$fqsenIndex = $fqsenIndex;
        }

        return self::$fqsenIndex;
    }
}
