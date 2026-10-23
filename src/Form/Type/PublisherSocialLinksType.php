<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Enum\PublisherNetwork;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A publisher's links to its pages on the social networks (Publisher::$socialLinks): one field per network, keyed by
 * its value, each refusing a link to somewhere else.
 */
final class PublisherSocialLinksType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (PublisherNetwork::cases() as $network) {
            $builder->add($network->value, UrlType::class, [
                'required' => false,
                'label' => $network->getLabel(),
                'default_protocol' => 'https',
                'attr' => ['placeholder' => $network->getPlaceholder()],
                'icon-prepend' => $network->getIcon(),
                'constraints' => [
                    new Length(max: 255),
                    new Url(requireTld: true),
                    new Callback(callback: static function (?string $url, ExecutionContextInterface $context) use ($network): void {
                        if (null !== $url && '' !== $url && !$network->owns($url)) {
                            $context->addViolation(\sprintf('Ce lien ne mène pas à %s.', $network->getLabel()));
                        }
                    }),
                ],
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
        ]);
    }
}
