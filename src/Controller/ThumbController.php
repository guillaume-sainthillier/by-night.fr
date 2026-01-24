<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

final class ThumbController extends Controller
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
        private readonly Packages $packages,
    ) {
    }

    #[Route(path: '/thumb/{path<%patterns.path%>}', name: 'thumb_s3_url', methods: ['GET'])]
    #[Cache(maxage: 31_536_000, smaxage: 31_536_000)]
    public function thumbS3(
        Request $request,
        #[Autowire(service: 'app.s3_thumb_server')]
        Server $s3ThumbServer,
        string $path,
    ): Response {
        return $this->serveFile(
            $s3ThumbServer,
            $request,
            $path,
            'aws'
        );
    }

    #[Route(path: '/thumb-asset/{path<%patterns.path%>}', name: 'thumb_asset_url', methods: ['GET'])]
    #[Cache(maxage: 31_536_000, smaxage: 31_536_000)]
    public function thumbAsset(
        Request $request,
        #[Autowire(service: 'app.asset_thumb_server')]
        Server $assetThumbServer,
        string $path,
    ): Response {
        return $this->serveFile(
            $assetThumbServer,
            $request,
            $path,
            'local'
        );
    }

    private function serveFile(Server $server, Request $request, string $path, string $packageName): Response
    {
        $parameters = $request->query->all();
        if (empty($parameters['h']) && empty($parameters['w']) && empty($parameters['p'])) {
            return new RedirectResponse($this->packages->getUrl($path, $packageName), Response::HTTP_MOVED_PERMANENTLY);
        }

        if ([] !== $parameters) {
            try {
                // No signature validation if no parameters
                // added to generate URL without parameters that not produce 404, useful especially for sitemap
                SignatureFactory::create($this->secret)->validateRequest($path, $parameters);
            } catch (SignatureException $signatureException) {
                throw $this->createNotFoundException($signatureException->getMessage(), $signatureException);
            }
        }

        $server->setResponseFactory(new SymfonyResponseFactory($request));
        try {
            $response = $server->getImageResponse($path, $parameters);
        } catch (InvalidArgumentException|FileNotFoundException $signatureException) {
            throw $this->createNotFoundException($signatureException->getMessage(), $signatureException);
        }

        return $response;
    }
}
