<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use App\Entity\Tag;
use App\SearchRepository\TagElasticaRepository;
use Pagerfanta\PagerfantaInterface;

/**
 * @extends AbstractElasticaAutocompleteProvider<Tag>
 */
final readonly class TagAutocompleteProvider extends AbstractElasticaAutocompleteProvider
{
    protected function search(string $term): PagerfantaInterface
    {
        /** @var TagElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(Tag::class);

        return $repo->findWithSearch($term);
    }
}
