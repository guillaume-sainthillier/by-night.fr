<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Controller\AbstractController as BaseController;
use App\Entity\Page;
use App\Picture\PagePicture;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends BaseController
{
    /**
     * The page, or its Markdown version at /p/{slug}.md and to an Accept header preferring text/markdown
     * (silarhi/llms-txt-bundle, "markdown_versions"). The slug takes no dot: it would swallow the ".md".
     */
    #[Route(path: '/p/{slug:page<[^/.]+>}.{_format}', name: 'app_page_show', format: 'html', requirements: ['_format' => 'html|md'], methods: ['GET'])]
    public function show(Request $request, Page $page, PagePicture $pagePicture): Response
    {
        if ('md' === $request->getRequestFormat()) {
            return $this->render('page/show.md.twig', ['page' => $page]);
        }

        return $this->render('page/show.html.twig', [
            'page' => $page,
            'ogImage' => $pagePicture->getOriginalPicture($page),
        ]);
    }
}
