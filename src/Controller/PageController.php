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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends BaseController
{
    #[Route(path: '/p/{slug<%patterns.slug%>}', name: 'app_page_show', methods: ['GET'])]
    public function show(Page $page, PagePicture $pagePicture): Response
    {
        return $this->render('page/show.html.twig', [
            'page' => $page,
            'ogImage' => $pagePicture->getOriginalPicture($page),
        ]);
    }
}
