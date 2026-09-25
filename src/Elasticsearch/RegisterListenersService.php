<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch;

use Doctrine\Persistence\ObjectManager;
use FOS\ElasticaBundle\Doctrine\RegisterListenersService as BaseRegisterListenersService;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PostPersistEvent;
use FOS\ElasticaBundle\Provider\PagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Clears the object manager (and sleeps) after each page a pager persists, like FOSElastica's
 * service, then removes its listeners once that pager is done.
 *
 * FOSElastica adds them to the shared dispatcher for every pager and never removes them. In the
 * elastica worker every AsyncPersistPage message provides a new pager, so each listener kept its
 * pager, and the page of hydrated entities behind it, alive: ~80 MB per 5000 events until the
 * worker hit its memory limit. Its SQL logger switch is left out: DBAL 4 has no SQL logger.
 */
#[AsDecorator('fos_elastica.doctrine.register_listeners')]
final class RegisterListenersService extends BaseRegisterListenersService
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
        parent::__construct($dispatcher);
    }

    public function register(ObjectManager $manager, PagerInterface $pager, array $options): void
    {
        $options = array_replace(['clear_object_manager' => true, 'sleep' => 0], $options);

        $afterEachPage = [];
        if ($options['clear_object_manager']) {
            $afterEachPage[] = $manager->clear(...);
        }

        if ($options['sleep']) {
            $afterEachPage[] = static fn () => usleep((int) $options['sleep']);
        }

        if ([] === $afterEachPage) {
            return;
        }

        $onPostInsert = static function (PostInsertObjectsEvent $event) use ($pager, $afterEachPage): void {
            if ($event->getPager() !== $pager) {
                return;
            }

            foreach ($afterEachPage as $callback) {
                $callback();
            }
        };

        // Dispatched once the pager has persisted all its pages, even when one of them failed
        $onPostPersist = function (PostPersistEvent $event) use ($pager, $onPostInsert, &$onPostPersist): void {
            if ($event->getPager() !== $pager) {
                return;
            }

            $this->dispatcher->removeListener(PostInsertObjectsEvent::class, $onPostInsert);
            $this->dispatcher->removeListener(PostPersistEvent::class, $onPostPersist);
        };

        $this->dispatcher->addListener(PostInsertObjectsEvent::class, $onPostInsert);
        $this->dispatcher->addListener(PostPersistEvent::class, $onPostPersist);
    }
}
