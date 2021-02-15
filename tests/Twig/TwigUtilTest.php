<?php

declare(strict_types=1);

namespace CSoellinger\Test\DogHtmlPrinter\Twig;

use CSoellinger\DogHtmlPrinter\Twig\TwigUtil;
use CSoellinger\Test\DogHtmlPrinter\CSoellingerTestCase;
use Mockery\MockInterface;

class TwigUtilTest extends CSoellingerTestCase
{
    public function testMarkdownHeadingList(): void
    {
        $markdown = "# Headline 1\n\n## Headline 1.1\n\n";
        $markdown .= "### Headline 1.1.1\n\n";
        $markdown .= "## Headline 1.2\n\n";
        $markdown .= "## Headline 1.3\n\n";
        $markdown .= "# Headline 2";

        $result = [
            [ 'id' => 'Headline1', 'parent_id' => 0, 'name' => 'Headline 1', 'lvl' => 1 ],
            [ 'id' => 'Headline1.1', 'parent_id' => 'Headline1', 'name' => 'Headline 1.1', 'lvl' => 2 ],
            [ 'id' => 'Headline1.1.1', 'parent_id' => 'Headline1.1', 'name' => 'Headline 1.1.1', 'lvl' => 3 ],
            [ 'id' => 'Headline1.2', 'parent_id' => 'Headline1', 'name' => 'Headline 1.2', 'lvl' => 2 ],
            [ 'id' => 'Headline1.3', 'parent_id' => 'Headline1', 'name' => 'Headline 1.3', 'lvl' => 2 ],
            [ 'id' => 'Headline2', 'parent_id' => 0, 'name' => 'Headline 2', 'lvl' => 1 ],
        ];
        $test = TwigUtil::getMarkdownHeadingsList($markdown);

        $this->assertSame($test, $result);

        $result = [
            [ 'id' => 'Headline1.1', 'parent_id' => 0, 'name' => 'Headline 1.1', 'lvl' => 1 ],
            [ 'id' => 'Headline1.1.1', 'parent_id' => 'Headline1.1', 'name' => 'Headline 1.1.1', 'lvl' => 2 ],
            [ 'id' => 'Headline1.2', 'parent_id' => 0, 'name' => 'Headline 1.2', 'lvl' => 1 ],
            [ 'id' => 'Headline1.3', 'parent_id' => 0, 'name' => 'Headline 1.3', 'lvl' => 1 ],
        ];
        $test = TwigUtil::getMarkdownHeadingsList($markdown, 2);

        $this->assertSame($test, $result);

        $result = [
            [ 'id' => 'Headline1', 'parent_id' => 0, 'name' => 'Headline 1', 'lvl' => 1 ],
            [ 'id' => 'Headline1.1', 'parent_id' => 'Headline1', 'name' => 'Headline 1.1', 'lvl' => 2 ],
            [ 'id' => 'Headline1.2', 'parent_id' => 'Headline1', 'name' => 'Headline 1.2', 'lvl' => 2 ],
            [ 'id' => 'Headline1.3', 'parent_id' => 'Headline1', 'name' => 'Headline 1.3', 'lvl' => 2 ],
            [ 'id' => 'Headline2', 'parent_id' => 0, 'name' => 'Headline 2', 'lvl' => 1 ],
        ];
        $test = TwigUtil::getMarkdownHeadingsList($markdown, 1, 2);

        $this->assertSame($test, $result);
    }
}
