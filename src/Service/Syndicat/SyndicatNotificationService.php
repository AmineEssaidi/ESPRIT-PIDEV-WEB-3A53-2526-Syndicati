<?php
namespace App\Service\Syndicat;

use App\Entity\Syndicat\Reclamation;
use App\Entity\Syndicat\Reponse;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\Media\ImagePathResolver;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SyndicatNotificationService
{
    private $mailer;
    private $router;
    private $twig;
    private $gmailOAuthMailer;
    private $fromEmail;
    private $fromName;
    private $mailerDsn;

    public function __construct(
        MailerInterface $mailer,
        UrlGeneratorInterface $router,
        \Twig\Environment $twig,
        \App\Service\OAuth\GmailOAuthMailer $gmailOAuthMailer,
        private readonly UserRepository $userRepository,
        private readonly ImagePathResolver $imagePathResolver,
        string $fromEmail,
        string $fromName,
        string $mailerDsn
    ) {
        $this->mailer = $mailer;
        $this->router = $router;
        $this->twig = $twig;
        $this->gmailOAuthMailer = $gmailOAuthMailer;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->mailerDsn = $mailerDsn;
    }

    public function notifyReclamationConfirmation(Reclamation $reclamation): void
    {
        $user = $reclamation->getUser();
        if (!$user || !$user->getEmailUser()) {
            return;
        }

        $imageUrls = [];
        $images = json_decode($reclamation->getImagereclamation() ?? '[]', true) ?: [];
        // Single image stored as string in 'new' method, handle both cases
        if (is_string($reclamation->getImagereclamation()) && !empty($reclamation->getImagereclamation()) && !str_contains($reclamation->getImagereclamation(), '[')) {
            $images = [$reclamation->getImagereclamation()];
        }

        foreach ($images as $img) {
            $imageUrls[] = $this->imagePathResolver->publicUrl((string) $img, 'reclamation_images', null, true);
        }

        $message = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($user->getEmailUser())
            ->subject('Reclamation Logged: ' . $reclamation->getTitrereclamations())
            ->html($this->twig->render('emails/reclamation_confirmation.html.twig', [
                'reclamation' => $reclamation,
                'imageUrls' => $imageUrls,
                'user' => $user
            ]));

        $this->sendEmail($message);
    }

    public function notifyReclamationBroadcastToStaff(Reclamation $reclamation): void
    {
        $author = $reclamation->getUser();
        $authorName = $author ? trim($author->getFirstName() . ' ' . $author->getLastName()) : 'A resident';

        foreach ($this->userRepository->findAll() as $recipient) {
            if (!$recipient instanceof User || !$recipient->getEmailUser()) {
                continue;
            }

            if ($author && $recipient->getIdUser() === $author->getIdUser()) {
                continue;
            }

            if (!in_array($recipient->getRoleUser(), ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'], true)) {
                continue;
            }

            $message = (new \Symfony\Component\Mime\Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($recipient->getEmailUser())
                ->subject('New Support Request: ' . $reclamation->getTitrereclamations())
                ->html($this->twig->render('emails/reclamation_staff_notification.html.twig', [
                    'recipient' => $recipient,
                    'reclamation' => $reclamation,
                    'authorName' => $authorName,
                ]));

            $this->sendEmail($message);
        }
    }

    public function notifyReclamationStatusChanged(Reclamation $reclamation, ?User $performer = null, ?string $previousStatus = null): void
    {
        $author = $reclamation->getUser();
        if ($author && $author->getEmailUser()) {
            $message = (new \Symfony\Component\Mime\Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($author->getEmailUser())
                ->subject('Update on your reclamation: ' . $reclamation->getTitrereclamations())
                ->html($this->twig->render('emails/reclamation_status_changed.html.twig', [
                    'recipientName' => $author->getFirstName(),
                    'reclamation' => $reclamation,
                    'previousStatus' => $previousStatus,
                    'newStatus' => $reclamation->getStatutreclamation(),
                    'performerName' => $performer ? trim($performer->getFirstName() . ' ' . $performer->getLastName()) : null,
                    'isPerformerCopy' => false,
                ]));

            $this->sendEmail($message);
        }

        if ($performer && $performer->getEmailUser() && (!$author || $performer->getIdUser() !== $author->getIdUser())) {
            $message = (new \Symfony\Component\Mime\Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($performer->getEmailUser())
                ->subject('Confirmation: Status updated for ' . $reclamation->getTitrereclamations())
                ->html($this->twig->render('emails/reclamation_status_changed.html.twig', [
                    'recipientName' => $performer->getFirstName(),
                    'reclamation' => $reclamation,
                    'previousStatus' => $previousStatus,
                    'newStatus' => $reclamation->getStatutreclamation(),
                    'performerName' => trim($performer->getFirstName() . ' ' . $performer->getLastName()),
                    'isPerformerCopy' => true,
                ]));

            $this->sendEmail($message);
        }
    }

    public function notifyReclamationReply(Reponse $reponse): void
    {
        $reclamation = $reponse->getReclamation();
        $author = $reclamation->getUser();

        if (!$author || !$author->getEmailUser()) {
            return;
        }

        $imageUrls = [];
        $images = json_decode($reponse->getImagereponse() ?? '[]', true) ?: [];
        foreach ($images as $img) {
            $imageUrls[] = $this->imagePathResolver->publicUrl((string) $img, 'reponse_images', null, true);
        }

        $senderName = $reponse->getUser()->getFirstName() . ' ' . $reponse->getUser()->getLastName();
        $isAuthorReplying = ($author === $reponse->getUser());

        // Determine Recipient
        if ($isAuthorReplying) {
            // Author replied -> Send to Admin (Syndicate)
            $recipientEmail = $this->fromEmail;
            $recipientName = 'Syndic Admin';
            $subjectPrefix = 'Client Reply: ';
        } else {
            // Admin/Agent replied -> Send to Author
            $recipientEmail = $author->getEmailUser();
            $recipientName = $author->getFirstName();
            $subjectPrefix = 'New Response: ';
        }

        $message = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($recipientEmail)
            ->subject($subjectPrefix . $reclamation->getTitrereclamations())
            ->html($this->twig->render('emails/reclamation_reply.html.twig', [
                'reponse' => $reponse,
                'reclamation' => $reclamation,
                'imageUrls' => $imageUrls,
                'senderName' => $senderName,
                'recipientName' => $recipientName,
                'user' => $author
            ]));

        $this->sendEmail($message);
    }

    private function sendEmail(\Symfony\Component\Mime\Email $email): void
    {
        $smtpConfigured = $this->mailerDsn !== '' && !str_starts_with($this->mailerDsn, 'null://');

        try {
            if ($smtpConfigured) {
                $this->mailer->send($email);
            } elseif ($this->gmailOAuthMailer->isAvailable()) {
                $this->gmailOAuthMailer->send($email);
            }
        } catch (\Exception $e) {
            // Fail silently
        }
    }
}
