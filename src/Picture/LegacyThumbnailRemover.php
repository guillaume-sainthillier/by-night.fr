<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Picture;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Page;
use App\Entity\User;
use App\Message\PurgeCdnCachePrefix;
use Doctrine\ORM\EntityManagerInterface;
use Silarhi\PicassoBundle\Exception\PurgeException;
use Silarhi\PicassoBundle\Service\ImagePipeline;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Deletes the thumbnails Picasso 1.x rendered for the uploads that are not event images.
 *
 * 1.x served every VichUploader mapping through a single "vich" loader, so every thumbnail sits under
 * glide/vich/<path>/ in the thumbs storage. Since 2.0 each mapping renders under its own loader; event_image keeps
 * "vich" as its URL alias, so only the other mappings left thumbnails behind there, which removing their image no
 * longer deletes (RemoveImageThumbnailsHandler only knows the current layout).
 *
 * One-shot, run by app:images:remove-legacy-thumbnails after the Picasso 2 release: delete both once it has run in
 * production.
 */
final readonly class LegacyThumbnailRemover
{
    private const string TRANSFORMER = 'glide';

    /**
     * The 1.x loader. Not a loader name any more, so purge() uses it as the cache directory as is.
     */
    private const string LEGACY_LOADER = 'vich';

    /**
     * Upload fields of every mapping but event_image: entity => upload field => embedded file holding its name.
     *
     * @var array<class-string, array<string, string>>
     */
    private const array UPLOAD_FIELDS = [
        User::class => ['imageFile' => 'image', 'imageSystemFile' => 'imageSystem'],
        Page::class => ['imageFile' => 'image'],
        City::class => ['heroImageFile' => 'heroImage'],
        Country::class => ['heroImageFile' => 'heroImage'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StorageInterface $storage,
        private ImagePipeline $imagePipeline,
        private UrlGeneratorInterface $urlGenerator,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * The uploads whose 1.x thumbnails may be left: paths relative to their storage, as in thumbnail URLs.
     *
     * Read from the database, not by listing glide/vich/ (millions of event thumbnails): an image removed since
     * then had its thumbnails deleted by the 1.x handler.
     *
     * @return iterable<string>
     */
    public function findPaths(): iterable
    {
        foreach (self::UPLOAD_FIELDS as $class => $fields) {
            foreach ($fields as $field => $file) {
                $query = $this->entityManager->createQueryBuilder()
                    ->select('e')
                    ->from($class, 'e')
                    ->where(\sprintf('e.%s.name IS NOT NULL', $file))
                    ->getQuery();

                foreach ($query->toIterable() as $entity) {
                    $path = $this->storage->resolvePath($entity, $field, null, true);
                    if (null !== $path && '' !== $path) {
                        yield ltrim($path, '/');
                    }
                }
            }
        }
    }

    /**
     * Deletes the 1.x thumbnails of an upload from the thumbs storage, then queues their purge from Cloudflare,
     * which keeps them a year.
     *
     * @throws PurgeException When the thumbs storage cannot delete them
     */
    public function remove(string $path): void
    {
        $this->imagePipeline->purge($path, self::LEGACY_LOADER, self::TRANSFORMER);

        // "//by-night.fr/p/image/glide/vich/<path>/": Cloudflare prefixes carry no scheme
        $url = $this->urlGenerator->generate('picasso_image', [
            'transformer' => self::TRANSFORMER,
            'loader' => self::LEGACY_LOADER,
            'path' => trim($path, '/') . '/',
        ], UrlGeneratorInterface::NETWORK_PATH);

        $this->messageBus->dispatch(new PurgeCdnCachePrefix(ltrim($url, '/')));
    }
}
