<?php

namespace TBN\AgendaBundle\Form;

use TBN\MainBundle\Entity\Site;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SearchType extends AbstractType
{

    protected $types_manifesation;
    protected $communes;
    protected $themes;

    public function __construct($types_manifesation, $communes, $themes)
    {
        $this->types_manifesation = $types_manifesation;
        $this->communes = $communes;
        $this->themes = $themes;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("du", "date", [
                "required" => false,
                "widget" => "single_text",
                "format" => "d/M/y",
                "attr" => ["class" => "datepicker", "data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("au", "date", [
                "required" => false,
                "widget" => "single_text",
                "format" => "d/M/y",
                "attr" => ["class" => "datepicker", "data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("type_manifestation","choice",[
                "choices" => $this->types_manifesation,
                "multiple" => true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Type de manifestation", "data-style" => "btn-warning", "data-live-search" => true]])
             ->add("commune","choice",[
                "choices" => $this->communes,
                "multiple" =>  true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Commune", "data-style" => "btn-warning", "data-live-search" => true]])
            ->add("theme","choice",[
                "choices" => $this->themes,
                "multiple" =>  true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Thème", "data-style" => "btn-warning", "data-live-search" => true]])
            ->add('term', 'text', [
                "required" => false,
                "attr" => ["class" => "form-control","placeholder" => "Recherchez un événement"]])
            ->add('chercher', 'submit', ["label" => "Go!", "attr" => ["class" => "btn btn-info btn-normal"]])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Search\SearchAgenda'
        ]);
    }

    public function getName()
    {
        return 'tbn_search_agenda';
    }
}
