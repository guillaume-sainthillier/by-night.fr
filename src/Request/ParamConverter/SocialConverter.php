<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 19:13.
 */

namespace App\Request\ParamConverter;

use App\Social\Social;
use App\Social\SocialProvider;
use function array_merge;
use InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

class SocialConverter implements ParamConverterInterface
{
    /**
     * @var SocialProvider
     */
    private $socialProvider;

    public function __construct(SocialProvider $socialProvider)
    {
        $this->socialProvider = $socialProvider;
    }

    public function apply(Request $request, ParamConverter $configuration)
    {
        $service = $request->attributes->get('service');

        if (null === $service && !$configuration->isOptional()) {
            throw new InvalidArgumentException('Route attribute is missing');
        }

        $options = array_merge([
            'default_facebook_name' => SocialProvider::FACEBOOK,
        ], $configuration->getOptions());

        $entity = $this->socialProvider->getSocial($service, $options['default_facebook_name']);
        $request->attributes->set($configuration->getName(), $entity);

        $configuration->setClass(null);
    }

    public function supports(ParamConverter $configuration)
    {
        return Social::class === $configuration->getClass();
    }
}
