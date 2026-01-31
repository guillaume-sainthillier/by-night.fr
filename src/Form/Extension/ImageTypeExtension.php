<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Generator;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

final class ImageTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly UserProfilePicture $userProfilePicture,
        private readonly EventProfilePicture $eventProfilePicture,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $object = $form->getParent()->getData();
        $view->vars['image_thumb_params'] = [];
        $view->vars['has_uploaded_image'] = false;

        if (null !== $object) {
            if ($object instanceof Event || $object instanceof EventDto) {
                $pictureData = $this->eventProfilePicture->getPicturePathAndSource($object);
                $view->vars['download_uri'] = $this->eventProfilePicture->getOriginalPicture($object);
                $view->vars['has_uploaded_image'] = 'upload' === $pictureData['source'];
                $view->vars['image_thumb_params'] = array_merge([
                    'event' => $object,
                    'loader' => 'event',
                ], $options['thumb_params']);
            } elseif ($object instanceof User || $object instanceof UserDto) {
                $pictureData = $this->userProfilePicture->getPicturePathAndSource($object);
                $view->vars['download_uri'] = $this->userProfilePicture->getOriginalProfilePicture($object);
                $view->vars['has_uploaded_image'] = 'upload' === $pictureData['source'];
                $view->vars['image_thumb_params'] = array_merge([
                    'user' => $object,
                    'loader' => 'user',
                ], $options['thumb_params']);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
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
