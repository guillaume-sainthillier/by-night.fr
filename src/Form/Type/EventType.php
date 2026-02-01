<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Dto\EventDto;
use App\Handler\DoctrineEventHandler;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

final class EventType extends AbstractType
{
    public function __construct(
        private readonly DoctrineEventHandler $doctrineEventHandler,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateRange', DateRangeType::class, [
                'from_field' => 'startDate',
                'to_field' => 'endDate',
                'label' => 'Dates',
                'ranges' => [],
            ])
            ->add('name', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Choisissez un titre accrocheur...',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'wysiwyg',
                    'placeholder' => 'Décrivez votre événement...',
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Affiche / Flyer',
                'required' => false,
                'thumb_params' => ['h' => 200, 'w' => 400, 'thumb' => 1],
                'help' => 'Pour un meilleur rendu, préférez une image au format 16:9 (ex: 1920x1080)',
            ])
            ->add('hours', TextType::class, [
                'label' => 'Horaires affichés',
                'required' => false,
                'attr' => [
                    'placeholder' => 'A 20h, de 21h à minuit',
                ],
            ])
            ->add('timesheets', CollectionType::class, [
                'entry_type' => EventTimesheetType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter une date',
                'label' => false,
                'entry_options' => [
                    'label' => false,
                ],
            ])
            ->add('prices', TextType::class, [
                'label' => 'Tarif',
                'required' => false,
                'attr' => [
                    'placeholder' => '17€ avec préventes, 20€ sur place',
                ],
            ])
            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Concert, Spectacle, ...',
                    'class' => 'js-category-input',
                    'data-autocomplete-url' => $this->urlGenerator->generate('api_tags', ['q' => '__QUERY__']),
                ],
            ])
            ->add('theme', TextType::class, [
                'label' => 'Thèmes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Humour, Tragédie, Jazz, Rock, Rap, ...',
                    'class' => 'js-tags-input',
                    'data-tags-url' => $this->urlGenerator->generate('api_tags', ['q' => '__QUERY__']),
                    'data-tags-allow-new' => 'true',
                ],
            ])
            ->add('address', TextType::class, [
                'required' => false,
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Tapez votre adresse ici pour remplir les champs ci-dessous',
                ],
            ])
            ->add('place', PlaceType::class, [
                'required' => true,
                'label' => false,
            ])
            ->add('websiteContacts', CollectionType::class, [
                'entry_type' => UrlType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter un site',
                'label' => 'Sites de réservation',
                'layout' => 'simple',
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'lucide:globe',
                    'attr' => [
                        'placeholder' => 'https://monsupersite.fr',
                    ],
                ],
            ])
            ->add('phoneContacts', CollectionType::class, [
                'entry_type' => TextType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter un numéro',
                'label' => 'Numéros de téléphone',
                'layout' => 'simple',
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'lucide:phone',
                    'attr' => [
                        'placeholder' => '06 01 02 03 04',
                    ],
                ],
            ])
            ->add('emailContacts', CollectionType::class, [
                'entry_type' => EmailType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter un email',
                'label' => 'Emails de contact',
                'layout' => 'simple',
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'lucide:mail',
                    'attr' => [
                        'placeholder' => 'vousêtes@incroyable.fr',
                    ],
                ],
            ])
            ->addEventListener(FormEvents::SUBMIT, $this->onSubmit(...));

        if (null !== $options['data'] && null === $options['data']->entityId) {
            $builder
                ->add('comment', TextareaType::class, [
                    'label' => 'Commentaire',
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'rows' => 5,
                        'placeholder' => 'Laisser un commentaire qui sera visible par les internautes',
                    ],
                ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function onSubmit(FormEvent $event): void
    {
        $data = $event->getData();

        if (!$data instanceof EventDto) {
            return;
        }

        if (null !== $data->place?->country && null !== $data->place->city) {
            $data->place->city->country = $data->place->country;
        }

        $this->doctrineEventHandler->handleOne($data);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventDto::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'app_event';
    }
}
