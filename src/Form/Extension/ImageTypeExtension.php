<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use App\Dto\EventDto;
use App\Dto\UserDto;
use App\Entity\Event;
use App\Entity\User;
use App\Picture\EventProfilePicture;
use App\Picture\UserProfilePicture;
use App\Twig\AssetExtension;
use Generator;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Vich\UploaderBundle\Storage\StorageInterface;

class ImageTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private StorageInterface $storage,
        private AssetExtension $assetExtension,
        private UserProfilePicture $userProfilePicture,
        private EventProfilePicture $eventProfilePicture
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $object = $form->getParent()->getData();
        $view->vars['image_thumb_params'] = [];

        if (null !== $object) {
            if ($object instanceof Event || $object instanceof EventDto) {
                $view->vars['download_uri'] = $this->eventProfilePicture->getOriginalPicture($object);
                $view->vars['image_thumb_params'] = array_merge([
                    'event' => $object,
                    'loader' => 'event',
                ], $options['thumb_params']);
            } elseif ($object instanceof User || $object instanceof UserDto) {
                $view->vars['download_uri'] = $this->userProfilePicture->getOriginalProfilePicture($object);
                $view->vars['image_thumb_params'] = array_merge([
                    'user' => $object,
                    'loader' => 'user',
                ], $options['thumb_params']);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
        $resolver->setRequired(['thumb_params']);
        $resolver->setAllowedTypes('thumb_params', 'array');
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-return Generator<int, VichImageType::class, mixed, void>
     */
    public static function getExtendedTypes(): iterable
    {
        yield VichImageType::class;
    }
}
