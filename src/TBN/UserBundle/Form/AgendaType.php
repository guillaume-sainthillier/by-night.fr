<?php

namespace TBN\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AgendaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom','text',[
                "label" => "Titre",
                "attr" => [
                    "placeholder" => "Choisissez un titre accrocheur..."
                ]
            ])
            ->add('descriptif','genemu_tinymce',[
                "label" => "Description",
                "attr" => [
                    "placeholder" => "Décrivez votre événement...",
                ]
            ])
//            ->add('name',null,array(
//                "required" => false
//            ))
            ->add('file','file', [
                "label" => "Affiche / Flyer",
                "required" => false
            ])
            ->add('dateDebut','genemu_jquerydate', [
                "label" => "A partir du ",
                'widget' => 'single_text',
                "required" => true,
                "attr" => [
                    "placeholder" => "Le / Du..."
                ]
            ])
            ->add('dateFin','genemu_jquerydate', [
                "label" => "Jusqu'au",
                'widget' => 'single_text',
                "required" => false,
                "attr" => [
                    "placeholder" => "Au..."
                ]
            ])
            ->add('share_facebook','checkbox', [
                "label" => "Poster mon événement sur Facebook",
                "required" => false,
                "mapped" => false,
                "data" => true,
            ])
            ->add('share_twitter','checkbox', [
                "label" => "Poster mon événement sur Twitter",
                "required" => false,
                "mapped" => false,
                "data" => true,
            ])
            ->add('share_google','checkbox', [
                "label" => "Poster mon événement sur Google",
                "required" => false,
                "mapped" => false,
                "data" => true,
            ])
            ->add('tarif','text', [
                "label" => "Tarif",
                "required" => false,
                "attr" => [
                    "placeholder" => "17€ avec préventes, 20€ sur place"
                ]
            ])
//            ->add('typeManifestation','text', array(
//                "label" => "Type",
//                "required" => false,
//                "attr" => array(
//                    "placeholder" => "Musique, Visite, ..."
//                )
//            ))
            ->add('categorieManifestation','text', [
                "label" => "Catégorie",
                "required" => false,
                "attr" => [
                    "placeholder" => "Concert, Evénement, ..."
                ]
            ])
            ->add('themeManifestation','text', [
                "label" => "Thème",
                "required" => false,
                "attr" => [
                    "placeholder" => "Musique, jazz, rap, rock, ..."
                ]
            ])
            ->add('lieuNom', 'text', [
                "required" => false,
                "label" => "Où ça ?",
            ])
            ->add('address', 'text', [
                "required" => false,
		"mapped" => false,
                "label" => "Adresse",
            ])
            ->add('latitude','hidden', [
                "required" => false,
            ])
            ->add('rue','hidden', [
                "required" => false,
            ])
            ->add('longitude','hidden', [
                "required" => false,
            ])
            ->add('codePostal','hidden', [
                "required" => false,
            ])
            ->add('ville','hidden', [
                "required" => false,
            ])
            ->add('reservationInternet','text', [
                "label" => "Réservation par internet",
                "required" => false,
                "attr" => [
                    "placeholder" => "L'URL où trouver un billet"
                ]
            ])
            ->add('reservationTelephone','text', [
                "label" => "Réservation téléphonique",
                "required" => false,
                "attr" => [
                    "placeholder" => "Le numéro à appeler pour acheter un billet"
                ]
            ])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Entity\Agenda'
        ]);
    }

    public function getName()
    {
        return 'tbn_agenda';
    }
}
