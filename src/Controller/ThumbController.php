<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Legacy thumb controller – redirects old /thumb/ and /thumb-asset/ URLs
 * to original images. Image transformation is now handled by picasso-bundle.
 */
final class ThumbController extends Controller
{
    #[Route(path: '/thumb/{path<%patterns.path%>}', name: 'thumb_s3_url', methods: ['GET'])]
    public function thumbS3(Packages $packages, string $path): Response
    {
        return new RedirectResponse(
            $packages->getUrl($path, 'aws'),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    #[Route(path: '/thumb-asset/{path<%patterns.path%>}', name: 'thumb_asset_url', methods: ['GET'])]
    public function thumbAsset(Packages $packages, string $path): Response
    {
        return new RedirectResponse(
            $packages->getUrl($path, 'local'),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
