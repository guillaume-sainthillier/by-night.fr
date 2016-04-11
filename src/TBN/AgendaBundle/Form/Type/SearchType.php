<?php

namespace TBN\AgendaBundle\Form\Type;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvents;

class SearchType extends AbstractType
{

    protected $types_manifesation;
    protected $lieux;
    protected $commune;

    public function __construct($types_manifesation, $lieux, $commune)
    {
        $this->types_manifesation = $types_manifesation;
        $this->lieux = $lieux;
        $this->commune = $commune;

    }

    public function onPreSubmit(FormEvent $event) {
        $data = $event->getData();

        if(isset($data['du']) && !$data['du']) {
            $data['du'] = date('d/m/Y');
        }

        $event->setData($data);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('page', 'hidden')
            ->add("du", DateType::class, [
                "required" => true,
                "label" => "Du",
                'label_attr' => array('class' => 'col-sm-6 control-label'),
                "widget" => "single_text",
                "format" => "dd/MM/yyyy",
                "attr" => ["data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("au", DateType::class, [
                "required" => false,
                "label" => "Au",
                'label_attr' => array('class' => 'col-sm-3 control-label'),
                "widget" => "single_text",
                "format" => "dd/MM/yyyy",
                "attr" => ["data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("type_manifestation", ChoiceType::class, [
                "choices" => $this->types_manifesation,
                "label" => 'Quoi ?',
                'label_attr' => array('class' => 'col-sm-3 control-label'),
                "multiple" => true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Tous", "class" => "form-control", "data-style" => "btn-primary btn-flat", "data-live-search" => true]])
            ->add("lieux", ChoiceType::class, [
                "choices" => $this->lieux,
                "label" => "Lieux",
                'label_attr' => array('class' => 'col-sm-3 control-label'),
                "multiple" => true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Tous", "class" => "form-control", "data-style" => "btn-primary btn-flat", "data-live-search" => true]])
            ->add("commune", ChoiceType::class, [
                "choices" => $this->commune,
                "label" => "Villes",
                'label_attr' => array('class' => 'col-sm-3 control-label'),
                "multiple" => true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Toutes", "class" => "form-control", "data-style" => "btn-primary btn-flat", "data-live-search" => true]])
            ->add('term', TextType::class, [
                "required" => false,
                "label" => "Mot-clés",
                'label_attr' => array('class' => 'col-sm-3 control-label'),
                "attr" => ["class" => "form-control", "placeholder" => "Quel événement cherchez-vous ?"]])
            ->add('chercher', SubmitType::class, [
                "label" => "Go !",
                "attr" => [
                    "class" => "btn btn-raised btn-lg btn-primary btn-block"
                ]
            ])
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                array($this, 'onPreSubmit')
            );;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Search\SearchAgenda',
            'csrf_protection' => false
        ]);
    }

    public function getName()
    {
        return 'tbn_search_agenda';
    }
}
