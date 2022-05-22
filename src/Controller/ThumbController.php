<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use InvalidArgumentException;
use League\Glide\Filesystem\FileNotFoundException;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\Server;
use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ThumbController extends Controller
{
    #[Route(path: '/thumb/{path<%patterns.path%>}', name: 'thumb_url', methods: ['GET'])]
    #[Cache(maxage: 31_536_000, smaxage: 31_536_000)]
    public function thumb(Request $request, Server $glide, string $path, string $secret, Packages $packages): Response
    {
        $parameters = $request->query->all();
        if (empty($parameters['h']) && empty($parameters['w']) && empty($parameters['p'])) {
            return new RedirectResponse($packages->getUrl($path, 'aws'), Response::HTTP_MOVED_PERMANENTLY);
        }

        if (\count($parameters) > 0) {
            try {
                // No signature validation if no parameters
                // added to generate URL without parameters that not produce 404, useful especially for sitemap
                SignatureFactory::create($secret)->validateRequest($path, $parameters);
            } catch (SignatureException $signatureException) {
                throw $this->createNotFoundException($signatureException->getMessage(), $signatureException);
            }
        }

        $glide->setResponseFactory(new SymfonyResponseFactory($request));
        try {
            $response = $glide->getImageResponse($path, $parameters);
        } catch (InvalidArgumentException|FileNotFoundException $signatureException) {
            throw $signatureException;
            throw $this->createNotFoundException($signatureException->getMessage(), $signatureException);
        }

        return $response;
    }

    #[Route(path: '/thumb-asset/{path<%patterns.path%>}', name: 'thumb_asset_url', methods: ['GET'])]
    #[Cache(maxage: 31_536_000, smaxage: 31_536_000)]
    public function thumbAsset(Request $request, Server $assetThumb, Packages $packages, string $path, string $secret): Response
    {
        $parameters = $request->query->all();
        if (empty($parameters['h']) && empty($parameters['w']) && empty($parameters['p'])) {
            return new RedirectResponse($packages->getUrl($path), Response::HTTP_MOVED_PERMANENTLY);
        }

        if (\count($parameters) > 0) {
            try {
                // No signature validation if no parameters
                // added to generate URL without parameters that not produce 404, useful especially for sitemap
                SignatureFactory::create($secret)->validateRequest($path, $parameters);
            } catch (SignatureException $signatureException) {
                throw $this->createNotFoundException($signatureException->getMessage(), $signatureException);
            }
        }

        $assetThumb->setResponseFactory(new SymfonyResponseFactory($request));
        try {
            $response = $assetThumb->getImageResponse($path, $parameters);
        } catch (InvalidArgumentException|FileNotFoundException $signatureException) {
            throw $this->createNotFoundException($signatureException->getMessage(), $signatureException);
        }

        return $response;
    }
}
