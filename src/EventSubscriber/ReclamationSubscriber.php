<?php

namespace App\EventSubscriber;

use App\Entity\Reclamations;
use App\Entity\Syndicat\Reclamation;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReclamationSubscriber implements EventSubscriberInterface
{
    private $mailer;
    private $recipientEmail = 'medbahahamdi2002@gmail.com';

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Reclamation && !$entity instanceof Reclamations) {
            return;
        }

        $this->sendEmail($entity);
    }

    private function sendEmail($reclamation): void
    {
        $title = $reclamation->getTitrereclamations();
        $description = $reclamation->getDescreclamation();
        
        // Handle different entity types if needed, though they seem to have same getters
        $id = $reclamation->getId();

        $email = (new Email())
            ->from('no-reply@horizon.com')
            ->to($this->recipientEmail)
            ->subject('New Reclamation Created: ' . $title)
            ->text(sprintf(
                "A new reclamation has been created.\n\nID: %s\nTitle: %s\nDescription: %s\n\nPlease check the dashboard for details.",
                $id,
                $title,
                $description
            ))
            ->html(sprintf(
                "<h1>New Reclamation Created</h1><p><strong>ID:</strong> %s</p><p><strong>Title:</strong> %s</p><p><strong>Description:</strong> %s</p><p>Please check the dashboard for details.</p>",
                $id,
                $title,
                $description
            ));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error or handle it silently to avoid breaking the application flow
            // In a real app, you might use a logger here
        }
    }
}
