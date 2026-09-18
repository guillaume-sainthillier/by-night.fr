<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Admin\Field\Configurator;

use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Gedmo\Sluggable\SluggableListener;

/**
 * Keeps an emptied slug field NULL so Gedmo regenerates it from its source fields.
 *
 * Every slug in this app is a NOT NULL column filled by Gedmo\Slug, and clearing the input to get
 * a fresh slug is a supported back-office action. EasyAdmin 5.6.0 broke it: its EmptyDataConfigurator
 * sets 'empty_data' to '' on text-based fields backed by a non-nullable column, so an emptied slug
 * now reaches the entity as '' instead of NULL. Gedmo only regenerates when the value is NULL
 * (Gedmo\Sluggable\SluggableListener::generateSlug), so '' is stored as-is and the row ends up with
 * an empty public URL -- and, since most of these columns are unique, the second one fails to insert.
 *
 * Restoring NULL is enough: the data mapper then writes nothing (the property already defaults to
 * NULL on a new entity) and Gedmo fills it in on flush. That is also the pre-5.6.0 behaviour, so
 * this configurator is a no-op on EasyAdmin 5.5.x.
 */
final readonly class SluggableEmptyDataConfigurator implements FieldConfiguratorInterface
{
    public function __construct(
        private SluggableListener $sluggableListener,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        $objectManager = $this->managerRegistry->getManagerForClass($entityDto->getFqcn());
        if (null === $objectManager) {
            return false;
        }

        // Ask Gedmo itself rather than reading the attribute: this stays correct for slugs declared
        // through any mapping driver and for those inherited from a parent class or a trait.
        $config = $this->sluggableListener->getConfiguration($objectManager, $entityDto->getFqcn());

        return isset($config['slugs'][$field->getProperty()]);
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $field->setFormTypeOption('empty_data', null);
    }
}
