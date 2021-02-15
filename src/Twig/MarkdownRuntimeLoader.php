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
 * @copyright Copyright (c) Christopher Söllinger <christopher.soellinger@gmail.com>
 * @license https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CSoellinger\DogHtmlPrinter\Twig;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment as CommonMarkEnvironment;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Normalizer\SlugNormalizer;
use Twig\Extra\Markdown\LeagueMarkdown;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class MarkdownRuntimeLoader implements RuntimeLoaderInterface
{
    public function load(string $class): ?MarkdownRuntime
    {
        if ($class === MarkdownRuntime::class) {
            $environment = CommonMarkEnvironment::createCommonMarkEnvironment();
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $environment->addExtension(new HeadingPermalinkExtension());

            $config = [
                // 'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 25,
                'heading_permalink' => [
                    'html_class' => 'heading-permalink',
                    'id_prefix' => 'user-content',
                    'insert' => 'after',
                    'title' => 'Permalink',
                    // 'symbol' => HeadingPermalinkRenderer::DEFAULT_SYMBOL,
                    'symbol' => '¶',
                    'slug_normalizer' => new SlugNormalizer(),
                ],
            ];

            $converter = new CommonMarkConverter($config, $environment);

            return new MarkdownRuntime(new LeagueMarkdown($converter));
        }

        return null;
    }
}
