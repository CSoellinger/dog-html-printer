<?php

declare(strict_types=1);

namespace CSoellinger\Test\DogHtmlPrinter\Twig;

use CSoellinger\DogHtmlPrinter\Twig\TwigUtil;
use CSoellinger\Test\DogHtmlPrinter\CSoellingerTestCase;

/**
 * @internal
 */
class TwigUtilTest extends CSoellingerTestCase
{
    /**
     * Undocumented function
     *
     * @return array<string,array<int,int|array<int,array<string,string|int|null>>>>
     */
    public function provideTestMarkdownHeadingList(): array
    {
        return [
            'test level one to six' => [
                1,
                6,
                [
                    ['id' => 'Headline1', 'parent_id' => null, 'name' => 'Headline 1', 'lvl' => 1],
                    ['id' => 'Headline1.1', 'parent_id' => 'Headline1', 'name' => 'Headline 1.1', 'lvl' => 2],
                    ['id' => 'Headline1.1.1', 'parent_id' => 'Headline1.1', 'name' => 'Headline 1.1.1', 'lvl' => 3],
                    ['id' => 'Headline2', 'parent_id' => null, 'name' => 'Headline 2', 'lvl' => 1],
                ],
            ],
            'test level two to six' => [
                2,
                6,
                [
                    ['id' => 'Headline1.1', 'parent_id' => null, 'name' => 'Headline 1.1', 'lvl' => 1],
                    ['id' => 'Headline1.1.1', 'parent_id' => 'Headline1.1', 'name' => 'Headline 1.1.1', 'lvl' => 2],
                    ['id' => 'Headline1.2', 'parent_id' => null, 'name' => 'Headline 1.2', 'lvl' => 1],
                ],
            ],
            'test level one to two' => [
                1,
                2,
                [
                    ['id' => 'Headline1', 'parent_id' => null, 'name' => 'Headline 1', 'lvl' => 1],
                    ['id' => 'Headline1.1', 'parent_id' => 'Headline1', 'name' => 'Headline 1.1', 'lvl' => 2],
                    ['id' => 'Headline2', 'parent_id' => null, 'name' => 'Headline 2', 'lvl' => 1],
                ],
            ],
        ];
    }

     /**
      * Undocumented function
      *
      * @param array<int,array<string,string|int|null>> $result
      *
      * @dataProvider provideTestMarkdownHeadingList
      */
    public function testMarkdownHeadingList(int $minLevel, int $maxLevel, array $result): void
    {
        $markdown = "# Headline 1\n\n## Headline 1.1\n\n### Headline 1.1.1\n\n# Headline 2";
        $test = TwigUtil::getMarkdownToc($markdown, $minLevel, $maxLevel);

        $this->assertSame($test, $result);
    }

    /**
     * Undocumented function
     *
     * @return array<string,mixed>
     */
    public function provideTestArrayListToTree(): array
    {
        return [
            'default' => [
                'idKey' => 'id',
                'parentIdKey' => 'parent_id',
                'childrenKey' => 'children',
                'list' => [
                    ['id' => 'lvl1', 'parent_id' => null],
                    ['id' => 'lvl1.1', 'parent_id' => 'lvl1'],
                    ['id' => 'lvl1.1.1', 'parent_id' => 'lvl1.1'],
                    ['id' => 'lvl2', 'parent_id' => null],
                ],
                'result' => [
                    [
                        'id' => 'lvl1', 'parent_id' => null, 'children' => [
                            [
                                'id' => 'lvl1.1', 'parent_id' => 'lvl1', 'children' => [
                                    ['id' => 'lvl1.1.1', 'parent_id' => 'lvl1.1'],
                                ],
                            ],
                        ],
                    ],
                    ['id' => 'lvl2', 'parent_id' => null],
                ],
            ],
            'custom' => [
                'idKey' => 'custom_id',
                'parentIdKey' => 'custom_parent_id',
                'childrenKey' => 'custom_children',
                'list' => [
                    ['custom_id' => 'lvl1', 'custom_parent_id' => null],
                    ['custom_id' => 'lvl1.1', 'custom_parent_id' => 'lvl1'],
                    ['custom_id' => 'lvl1.1.1', 'custom_parent_id' => 'lvl1.1'],
                    ['custom_id' => 'lvl2', 'custom_parent_id' => null],
                ],
                'result' => [
                    [
                        'custom_id' => 'lvl1', 'custom_parent_id' => null, 'custom_children' => [
                            [
                                'custom_id' => 'lvl1.1', 'custom_parent_id' => 'lvl1', 'custom_children' => [
                                    ['custom_id' => 'lvl1.1.1', 'custom_parent_id' => 'lvl1.1'],
                                ],
                            ],
                        ],
                    ],
                    ['custom_id' => 'lvl2', 'custom_parent_id' => null],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideTestArrayListToTree
     */

    /**
     * Undocumented function
     *
     * @param array<int,array<string,string|null>> $list
     * @param array<int,mixed> $result
     */
    public function testArrayListToTree(
        string $idKey,
        string $parentIdKey,
        string $childrenKey,
        array $list,
        array $result
    ): void {
        $this->assertSame($result, TwigUtil::arrayListToTree($list, $idKey, $parentIdKey, $childrenKey));
    }
}
