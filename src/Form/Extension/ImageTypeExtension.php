<?php

namespace App\Form\Extension;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Vich\UploaderBundle\Storage\StorageInterface;

class ImageTypeExtension extends AbstractTypeExtension
{
    /** @var CacheManager */
    private $cacheManager;

    /** @var StorageInterface */
    private $storage;

    public function __construct(StorageInterface $storage, CacheManager $cacheManager)
    {
        $this->storage = $storage;
        $this->cacheManager = $cacheManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $object = $form->getParent()->getData();
        $view->vars['image_filter_uri'] = null;

        if (null !== $object) {
            $path = $this->storage->resolveUri($object, $form->getName(), null);
            if (null !== $path) {
                $view->vars['image_filter_uri'] = $this->cacheManager->getBrowserPath($path, $options['image_filter']);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['image_filter']);
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes()
    {
        return [VichImageType::class];
    }
}
