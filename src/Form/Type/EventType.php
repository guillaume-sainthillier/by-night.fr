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
use App\Form\Builder\DateRangeBuilder;
use App\Handler\DoctrineEventHandler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class EventType extends AbstractType
{
    public function __construct(
        private DoctrineEventHandler $doctrineEventHandler,
        private DateRangeBuilder $dateRangeBuilder
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $this->dateRangeBuilder->finishView($view, $form);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->dateRangeBuilder->addDateFields($builder, 'startDate', 'endDate');
        $builder
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
            ])
            ->add('hours', TextType::class, [
                'label' => 'Horaires',
                'required' => false,
                'attr' => [
                    'placeholder' => 'A 20h, de 21h à minuit',
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
                ],
            ])
            ->add('theme', TextType::class, [
                'label' => 'Thèmes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Humour, Tragédie, Jazz, Rock, Rap, ...',
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
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'globe',
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
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'phone',
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
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'envelope',
                    'attr' => [
                        'placeholder' => 'vousêtes@incroyable.fr',
                    ],
                ],
            ])
            ->addEventListener(FormEvents::SUBMIT, [$this, 'onSubmit']);

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
     *
     * @return void
     */
    public function onSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (!$data instanceof EventDto) {
            return;
        }

        if (null !== $data->place?->country && null !== $data?->place->city) {
            $data->place->city->country = $data->place?->country;
        }

        $this->doctrineEventHandler->handleOne($data, false);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EventDto::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_event';
    }
}
