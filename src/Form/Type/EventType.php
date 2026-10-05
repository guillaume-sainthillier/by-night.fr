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
use App\Dto\EventTimesheetDto;
use App\Enum\EventStatus;
use App\Form\DataTransformer\TagDtoArrayTransformer;
use App\Form\DataTransformer\TagDtoTransformer;
use App\Handler\DoctrineEventHandler;
use App\Utils\HoursLabel;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
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
            ])
            ->add('name', TextType::class, [
                'label' => "Titre de l'événement",
                'attr' => [
                    'placeholder' => 'Choisissez un titre accrocheur…',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'wysiwyg',
                    'placeholder' => 'Décrivez votre événement…',
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Affiche / Flyer',
                'required' => false,
                'thumb_params' => ['h' => 200, 'w' => 400, 'thumb' => 1],
            ])
            // The default slot of the dates: the dates without times of their own take it (onSubmit)
            ->add('startTime', TimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('hours', TextType::class, [
                'label' => 'Précisions',
                'required' => false,
                'attr' => [
                    'placeholder' => 'À 20h, de 21h à minuit',
                ],
            ])
            ->add('timesheets', CollectionType::class, [
                'entry_type' => EventTimesheetType::class,
                'required' => false,
                // A row added then left empty comes back as null, which the Firewall can't read
                'delete_empty' => true,
                'add_entry_label' => 'Ajouter une date',
                'label' => false,
                'entry_options' => [
                    'label' => false,
                ],
            ])
            ->add('prices', TextType::class, [
                'label' => 'Tarifs',
                'required' => false,
                'attr' => [
                    'placeholder' => "17\u{a0}€ avec préventes, 20\u{a0}€ sur place",
                ],
            ])
            ->add('status', EnumType::class, [
                'label' => 'Statut',
                'class' => EventStatus::class,
                'choice_label' => static fn (EventStatus $status) => $status->getLabel(),
                'required' => false,
                'placeholder' => 'Programmé',
            ])
            ->add('statusMessage', TextType::class, [
                'label' => 'Message de statut',
                'required' => false,
                'attr' => [
                    'placeholder' => "Précisez le statut (ex.\u{a0}: reporté au 15 mars)",
                ],
                'help' => 'Message personnalisé affiché aux visiteurs',
            ])
            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Concert, Spectacle…',
                    'class' => 'js-category-input',
                    'data-autocomplete-url' => $this->urlGenerator->generate('api_tags', ['q' => '__QUERY__']),
                ],
            ])
            ->add('themes', TextType::class, [
                'label' => 'Thèmes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Humour, Tragédie, Jazz, Rock, Rap…',
                    'class' => 'js-tags-input',
                    'data-tags-url' => $this->urlGenerator->generate('api_tags', ['q' => '__QUERY__']),
                    'data-tags-allow-new' => 'true',
                ],
            ])
            ->add('address', TextType::class, [
                'required' => false,
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Rechercher une adresse…',
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
                'add_entry_label' => 'Ajouter un e-mail',
                'label' => 'E-mails de contact',
                'layout' => 'simple',
                'entry_options' => [
                    'label' => false,
                    'icon-prepend' => 'lucide:mail',
                    'attr' => [
                        'placeholder' => 'vousêtes@incroyable.fr',
                    ],
                ],
            ])
            // Saves the event hidden from the site (a draft); the form's other submit button publishes it
            ->add('saveDraft', SubmitType::class, [
                'label' => 'Enregistrer en brouillon',
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, $this->onPreSetData(...))
            ->addEventListener(FormEvents::SUBMIT, $this->onSubmit(...));

        $builder->get('category')->addModelTransformer(new TagDtoTransformer());
        $builder->get('themes')->addModelTransformer(new TagDtoArrayTransformer());

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
     * The event's times are the span of its dates (first start, last end): the form shows instead the slot every
     * date shares as the default, and the dates without precisions as following it, or no default when they differ.
     */
    public function onPreSetData(FormEvent $event): void
    {
        $data = $event->getData();
        if (!$data instanceof EventDto || [] === $data->timesheets) {
            return;
        }

        $slots = array_unique(array_map(self::slot(...), $data->timesheets));
        $shared = 1 === \count($slots) && '|' !== reset($slots) ? $data->timesheets[0] : null;
        $data->startTime = $shared?->startTime;
        $data->endTime = $shared?->endTime;
        if (null !== $shared) {
            foreach ($data->timesheets as $timesheet) {
                // A date with precisions keeps its times: the default only fills back the dates without any
                // (applyDefaultSlot), saving the form as is would lose them
                if (null !== $timesheet->hours) {
                    continue;
                }

                $timesheet->startTime = null;
                $timesheet->endTime = null;
            }
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

        $this->applyDefaultSlot($data);

        if (null !== $data->place?->country && null !== $data->place->city) {
            $data->place->city->country = $data->place->country;
        }

        // The message details a status: the form hides it while the event is "Programmé" (no status), and the event
        // page would still show one saved without a status
        if (null === $data->status) {
            $data->statusMessage = null;
        }

        // Only judged here, for the validation to show the verdict: the controller saves the
        // event once the whole form, its CSRF token included, is valid. Saved here, it was
        // written (image upload included) whatever the validation found.
        $this->doctrineEventHandler->judge($data);
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

    /**
     * The dates with neither times nor precisions of their own take the default slot; the default precisions are
     * shown for them as they are (SessionHours). A default typed as text ("20h", "de 20h à 23h") is a slot.
     */
    private function applyDefaultSlot(EventDto $data): void
    {
        if ([] === $data->timesheets) {
            // A single session: the event's times are its own
            return;
        }

        $typed = null === $data->startTime && null === $data->endTime ? HoursLabel::parse($data->hours) : null;
        if (null !== $typed) {
            [$data->startTime, $data->endTime] = $typed;
            $data->hours = null;
        }

        foreach ($data->timesheets as $timesheet) {
            if (null === $timesheet->startTime && null === $timesheet->endTime && null === $timesheet->hours) {
                $timesheet->startTime = $data->startTime;
                $timesheet->endTime = $data->endTime;
            }
        }
    }

    private static function slot(EventTimesheetDto $timesheet): string
    {
        return \sprintf('%s|%s', $timesheet->startTime?->format('H:i'), $timesheet->endTime?->format('H:i'));
    }
}
