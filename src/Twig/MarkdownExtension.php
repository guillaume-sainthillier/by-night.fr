<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Twig\Attribute\AsTwigFilter;

/**
 * The HTML of the back office's editor as Markdown, for the Markdown versions of the pages. The html_to_markdown
 * filter of twig/markdown-extra, with the tables the editor writes: its converter leaves them out.
 */
final class MarkdownExtension
{
    private ?HtmlConverter $converter = null;

    #[AsTwigFilter(name: 'html_to_markdown')]
    public function htmlToMarkdown(string $html): string
    {
        if (null === $this->converter) {
            // The options of twig/markdown-extra, with "## " headings: what Markdown cannot write is dropped, not kept as HTML
            $this->converter = new HtmlConverter(['header_style' => 'atx', 'hard_break' => true, 'strip_tags' => true, 'remove_nodes' => 'head style script']);
            $this->converter->getEnvironment()->addConverter(new TableConverter());
        }

        return $this->converter->convert($html);
    }
}
