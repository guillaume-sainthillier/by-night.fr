<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use App\Manager\MailerManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute(path: '/content-removal-request', name: 'content_removal_request')]
final class ContentRemovalRequestCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly MailerManager $mailerManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContentRemovalRequest::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande de suppression')
            ->setEntityLabelInPlural('Demandes de suppression')
            ->setSearchFields(['id', 'email', 'message'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(ContentRemovalRequestStatus::cases()))
            ->add(ChoiceFilter::new('type')->setChoices(ContentRemovalType::cases()))
            ->add(EntityFilter::new('events'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $removeImage = Action::new('removeImage', "Supprimer l'image", 'lucide:image-minus')
            ->linkToCrudAction('removeImage')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus() && ContentRemovalType::Image === $entity->getType() && $entity->getEvents()->count() > 0)
            ->addCssClass('btn btn-warning');

        $removeEvents = Action::new('removeEvents', 'Supprimer les événements', 'lucide:trash-2')
            ->linkToCrudAction('removeEvents')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus() && ContentRemovalType::Event === $entity->getType() && $entity->getEvents()->count() > 0)
            ->addCssClass('btn btn-danger');

        $markAsProcessed = Action::new('markAsProcessed', 'Marquer traité', 'lucide:check')
            ->linkToCrudAction('markAsProcessed')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus())
            ->addCssClass('btn btn-success');

        $reject = Action::new('reject', 'Rejeter', 'lucide:x')
            ->linkToCrudAction('reject')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus())
            ->addCssClass('btn btn-secondary');

        $batchMarkAsProcessed = Action::new('batchMarkAsProcessed', 'Marquer traité', 'lucide:check')
            ->linkToCrudAction('batchMarkAsProcessed')
            ->addCssClass('btn btn-success');

        $batchReject = Action::new('batchReject', 'Rejeter', 'lucide:x')
            ->linkToCrudAction('batchReject')
            ->addCssClass('btn btn-secondary');

        $batchRemoveImages = Action::new('batchRemoveImages', 'Supprimer les images', 'lucide:image-minus')
            ->linkToCrudAction('batchRemoveImages')
            ->addCssClass('btn btn-warning');

        $batchRemoveEvents = Action::new('batchRemoveEvents', 'Supprimer les événements', 'lucide:trash-2')
            ->linkToCrudAction('batchRemoveEvents')
            ->addCssClass('btn btn-danger');

        return parent::configureActions($actions)
            ->disable(Action::NEW)
            ->add(Crud::PAGE_DETAIL, $removeImage)
            ->add(Crud::PAGE_DETAIL, $removeEvents)
            ->add(Crud::PAGE_DETAIL, $markAsProcessed)
            ->add(Crud::PAGE_DETAIL, $reject)
            ->add(Crud::PAGE_INDEX, $removeImage)
            ->add(Crud::PAGE_INDEX, $removeEvents)
            ->add(Crud::PAGE_INDEX, $markAsProcessed)
            ->add(Crud::PAGE_INDEX, $reject)
            ->addBatchAction($batchMarkAsProcessed)
            ->addBatchAction($batchReject)
            ->addBatchAction($batchRemoveImages)
            ->addBatchAction($batchRemoveEvents);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addFieldset('Demande');
        $id = IdField::new('id', 'ID');
        $email = EmailField::new('email');
        $type = ChoiceField::new('type')
            ->setChoices(ContentRemovalType::cases())
            ->renderAsBadges([
                ContentRemovalType::Image->value => 'warning',
                ContentRemovalType::Event->value => 'danger',
            ]);
        $message = TextareaField::new('message');
        $eventUrls = ArrayField::new('eventUrls', 'URLs');
        $events = AssociationField::new('events')
            ->setCrudController(EventCrudController::class)
            ->autocomplete();
        $createdAt = DateTimeField::new('createdAt');

        $panel2 = FormField::addFieldset('Traitement');
        $status = ChoiceField::new('status')
            ->setChoices(ContentRemovalRequestStatus::cases())
            ->renderAsBadges([
                ContentRemovalRequestStatus::Pending->value => 'warning',
                ContentRemovalRequestStatus::Processed->value => 'success',
                ContentRemovalRequestStatus::Rejected->value => 'danger',
            ]);
        $processedAt = DateTimeField::new('processedAt');
        $processedBy = AssociationField::new('processedBy')
            ->setCrudController(UserCrudController::class)
            ->autocomplete();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $createdAt, $email, $type, $status, $events];
        }

        return [
            $panel1,
            $id->hideOnForm(),
            $createdAt->hideOnForm(),
            $email,
            $type,
            $message,
            $eventUrls,
            $events,

            $panel2,
            $status,
            $processedAt->hideOnForm(),
            $processedBy->hideOnForm(),
        ];
    }

    #[AdminRoute]
    public function removeImage(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        foreach ($request->getEvents() as $event) {
            $this->removeEventImage($event);
        }

        $this->markRequestAsProcessed($request);
        $this->mailerManager->sendContentRemovalProcessedEmail($request);

        $this->addFlash('success', 'Les images des événements ont été supprimées.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[AdminRoute]
    public function removeEvents(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        foreach ($request->getEvents() as $event) {
            $this->entityManager->remove($event);
        }

        // The requester is notified by ContentRemovalEventDeletionListener once the events are deleted.
        $this->markRequestAsProcessed($request);

        $this->addFlash('success', 'Les événements ont été supprimés.');

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    #[AdminRoute]
    public function markAsProcessed(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        $this->markRequestAsProcessed($request);
        $this->mailerManager->sendContentRemovalProcessedEmail($request);

        $this->addFlash('success', 'La demande a été marquée comme traitée.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[AdminRoute]
    public function reject(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        $this->markRequestAsRejected($request);
        $this->mailerManager->sendContentRemovalRejectedEmail($request);

        $this->addFlash('success', 'La demande a été rejetée.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[AdminRoute]
    public function batchMarkAsProcessed(BatchActionDto $batchActionDto): Response
    {
        /** @var ContentRemovalRequest[] $processed */
        $processed = [];
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request && ContentRemovalRequestStatus::Pending === $request->getStatus()) {
                $this->markRequestAsProcessed($request, false);
                $processed[] = $request;
            }
        }

        $this->entityManager->flush();

        foreach ($processed as $request) {
            $this->mailerManager->sendContentRemovalProcessedEmail($request);
        }

        $this->addFlash('success', \sprintf('%d demande(s) marquée(s) comme traitée(s).', \count($processed)));

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[AdminRoute]
    public function batchReject(BatchActionDto $batchActionDto): Response
    {
        /** @var ContentRemovalRequest[] $rejected */
        $rejected = [];
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request && ContentRemovalRequestStatus::Pending === $request->getStatus()) {
                $this->markRequestAsRejected($request, false);
                $rejected[] = $request;
            }
        }

        $this->entityManager->flush();

        foreach ($rejected as $request) {
            $this->mailerManager->sendContentRemovalRejectedEmail($request);
        }

        $this->addFlash('success', \sprintf('%d demande(s) rejetée(s).', \count($rejected)));

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[AdminRoute]
    public function batchRemoveImages(BatchActionDto $batchActionDto): Response
    {
        /** @var ContentRemovalRequest[] $processed */
        $processed = [];
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request
                && ContentRemovalRequestStatus::Pending === $request->getStatus()
                && ContentRemovalType::Image === $request->getType()
            ) {
                foreach ($request->getEvents() as $event) {
                    $this->removeEventImage($event);
                }

                $this->markRequestAsProcessed($request, false);
                $processed[] = $request;
            }
        }

        $this->entityManager->flush();

        foreach ($processed as $request) {
            $this->mailerManager->sendContentRemovalProcessedEmail($request);
        }

        $this->addFlash('success', \sprintf('%d image(s) supprimée(s).', \count($processed)));

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[AdminRoute]
    public function batchRemoveEvents(BatchActionDto $batchActionDto): Response
    {
        $count = 0;
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request
                && ContentRemovalRequestStatus::Pending === $request->getStatus()
                && ContentRemovalType::Event === $request->getType()
            ) {
                foreach ($request->getEvents() as $event) {
                    $this->entityManager->remove($event);
                }

                // The requester is notified by ContentRemovalEventDeletionListener once the events are deleted.
                $this->markRequestAsProcessed($request, false);
                ++$count;
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('%d événement(s) supprimé(s).', $count));

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    private function removeEventImage(Event $event): void
    {
        $event->setImageFile();
        $event->setImageHash(null);
        $event->setImageSystemFile();
        $event->setImageSystemHash(null);
    }

    private function markRequestAsProcessed(ContentRemovalRequest $request, bool $flush = true): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $request->setStatus(ContentRemovalRequestStatus::Processed);
        $request->setProcessedAt(new DateTimeImmutable());
        $request->setProcessedBy($user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function markRequestAsRejected(ContentRemovalRequest $request, bool $flush = true): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $request->setStatus(ContentRemovalRequestStatus::Rejected);
        $request->setProcessedAt(new DateTimeImmutable());
        $request->setProcessedBy($user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
