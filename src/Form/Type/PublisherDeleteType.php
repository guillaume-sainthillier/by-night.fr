<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Entity\Publisher;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The deletion of a publisher (App\Manager\PublisherRemover): where its events go, when it has any (to another
 * publisher of its owner, or deleted with it), and its name typed again.
 */
final class PublisherDeleteType extends AbstractType
{
    /** The choice of deleting the events with the publisher */
    public const string DELETE_EVENTS = 'delete';

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Publisher $publisher */
        $publisher = $options['publisher'];
        /** @var list<Publisher> $heirs */
        $heirs = $options['heirs'];
        /** @var int $events */
        $events = $options['events'];

        if (0 < $events) {
            $choices = [];
            foreach ($heirs as $heir) {
                $choices[\sprintf('Les déplacer vers «%s%s%s»', "\u{a0}", $heir->getName(), "\u{a0}")] = (string) $heir->getId();
            }
            $choices[1 === $events ? 'Le supprimer' : \sprintf('Les supprimer (%d événements)', $events)] = self::DELETE_EVENTS;

            $builder->add('heir', ChoiceType::class, [
                'label' => 1 === $events ? 'Son événement' : \sprintf('Ses %d événements', $events),
                'choices' => $choices,
                'expanded' => true,
                'constraints' => [new NotBlank(message: 'Choisissez ce que deviennent ses événements.')],
            ]);
        }

        $builder->add('name', TextType::class, [
            'label' => 'Saisissez le nom de la page pour confirmer',
            'attr' => ['placeholder' => $publisher->getName(), 'autocomplete' => 'off'],
            'constraints' => [new EqualTo(value: $publisher->getName(), message: 'Le nom saisi n’est pas celui de la page.')],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['publisher', 'heirs', 'events'])
            ->setAllowedTypes('publisher', Publisher::class)
            ->setAllowedTypes('heirs', 'array')
            ->setAllowedTypes('events', 'int');
    }
}
