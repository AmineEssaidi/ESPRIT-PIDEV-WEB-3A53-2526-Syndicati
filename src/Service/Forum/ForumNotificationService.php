<?php
namespace App\Service\Forum;

use App\Entity\Forum\Publication;
use App\Entity\Forum\Commentaire;
use App\Repository\User\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForumNotificationService
{
    private $mailer;
    private $userRepository;
    private $router;
    private $twig;
    private $gmailOAuthMailer;
    private $fromEmail;
    private $fromName;
    private $mailerDsn;

    public function __construct(
        MailerInterface $mailer,
        UserRepository $userRepository,
        UrlGeneratorInterface $router,
        \Twig\Environment $twig,
        \App\Service\OAuth\GmailOAuthMailer $gmailOAuthMailer,
        string $fromEmail,
        string $fromName,
        string $mailerDsn
    ) {
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
        $this->router = $router;
        $this->twig = $twig;
        $this->gmailOAuthMailer = $gmailOAuthMailer;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->mailerDsn = $mailerDsn;
    }

    public function notifyNewAnnouncement(Publication $publication): void
    {
        $users = $this->userRepository->findAll();
        $forumUrl = $this->router->generate('frontend_forum', [], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($users as $user) {
            if (!$user->getEmailUser()) {
                continue;
            }

            $message = (new \Symfony\Component\Mime\Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($user->getEmailUser())
                ->subject('New Announcement: ' . $publication->getTitrePub())
                ->html($this->twig->render('emails/forum_announcement.html.twig', [
                    'publication' => $publication,
                    'user' => $user,
                    'forumUrl' => $forumUrl
                ]));

            $this->sendEmail($message);
        }
    }

    public function notifyNewComment(Commentaire $commentaire): void
    {
        $publication = $commentaire->getPublication();
        $author = $publication->getUser();

        // Don't notify if author info is missing or author is the one commenting
        if (!$author || !$author->getEmailUser() || $author === $commentaire->getUser()) {
            return;
        }

        $forumUrl = $this->router->generate('frontend_forum', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Handle Anonymity
        $senderName = $commentaire->isVisibility()
            ? $commentaire->getUser()->getFirstName() . ' ' . $commentaire->getUser()->getLastName()
            : 'Anonymous';

        $message = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($author->getEmailUser())
            ->subject('New Reply to your post: ' . $publication->getTitrePub())
            ->html($this->twig->render('emails/forum_reply.html.twig', [
                'comment' => $commentaire,
                'author' => $author,
                'senderName' => $senderName,
                'forumUrl' => $forumUrl
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
        }
    }

    public function notifyReportConfirmation($target, \App\Entity\User\User $reporter, string $reason): void
    {
        if (!$reporter->getEmailUser()) {
            return;
        }

        $type = ($target instanceof \App\Entity\Forum\Publication) ? 'Publication' : 'Comment';
        $content = '';
        $imageUrl = null;

        if ($target instanceof \App\Entity\Forum\Publication) {
            $title = $target->getTitrePub();
            $content = $target->getDescriptionPub();
            if ($target->getImagePub()) {
                $imageUrl = $this->router->getContext()->getScheme() . '://' . $this->router->getContext()->getHost() . '/forum_images/' . $target->getImagePub();
            }
        } else {
            $pubTitle = $target->getPublication() ? $target->getPublication()->getTitrePub() : 'Deleted Post';
            $title = 'Comment on "' . $pubTitle . '"';
            $content = $target->getDescriptionCommentaire();
            if ($target->getImageCommentaire()) {
                $imageUrl = $this->router->getContext()->getScheme() . '://' . $this->router->getContext()->getHost() . '/commentaire_images/' . $target->getImageCommentaire();
            }
        }

        $message = (new \Symfony\Component\Mime\Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($reporter->getEmailUser())
            ->subject('Report Received: ' . $title)
            ->html($this->twig->render('emails/report_confirmation.html.twig', [
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'imageUrl' => $imageUrl,
                'reason' => $reason,
                'user' => $reporter
            ]));

        $this->sendEmail($message);
    }
}
