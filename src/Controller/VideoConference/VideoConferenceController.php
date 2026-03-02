<?php

namespace App\Controller\VideoConference;

use App\Entity\VideoConference\VideoConference;
use App\Repository\VideoConference\VideoConferenceRepository;
use App\Repository\User\UserRepository;
use App\Service\VideoConference\LiveKitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/video-conference')]
class VideoConferenceController extends AbstractController
{
    private LiveKitService $liveKitService;

    public function __construct(LiveKitService $liveKitService)
    {
        $this->liveKitService = $liveKitService;
    }

    #[Route('/create', name: 'video_conf_create', methods: ['POST'])]
    public function create(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        try {
            $session = $request->getSession();
            if (!$session->get('is_logged_in') || !isset($session->get('user')['id'])) {
                return $this->json(['error' => 'Unauthorized - Please log in again'], Response::HTTP_UNAUTHORIZED);
            }

            $user = $userRepository->find((int) $session->get('user')['id']);
            if (!$user) {
                return $this->json(['error' => 'User not found in database'], Response::HTTP_NOT_FOUND);
            }

            $roomToken = Uuid::v4()->toRfc4122();
            $roomName = $request->request->get('roomName');

            $conference = new VideoConference();
            $conference->setRoomToken($roomToken);
            $conference->setRoomName($roomName);
            $conference->setUser($user);
            $conference->setCreatedAt(new \DateTime());
            $conference->setExpiresAt((new \DateTime())->modify('+2 hours'));
            $conference->setStatus('live');

            $em->persist($conference);
            $em->flush();

            $joinUrl = $this->generateUrl('video_conf_room', ['token' => $roomToken]);
            $joinUrl = $request->getSchemeAndHttpHost() . $joinUrl;

            return $this->json([
                'success' => true,
                'roomToken' => $roomToken,
                'joinUrl' => $joinUrl
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Database error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/join-code', name: 'video_conf_join_code', methods: ['POST'])]
    public function joinCode(Request $request, VideoConferenceRepository $repo): Response
    {
        $code = $request->request->get('roomCode');
        $conference = $repo->findOneBy(['roomToken' => $code]);

        if (!$conference || $conference->getStatus() === 'ended' || $conference->getExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Invalid or expired room code.');
            return $this->redirectToRoute('main_home');
        }

        return $this->redirectToRoute('video_conf_room', ['token' => $code]);
    }

    #[Route('/room/{token}', name: 'video_conf_room')]
    public function room(string $token, VideoConferenceRepository $repo, UserRepository $userRepository, Request $request): Response
    {
        $conference = $repo->findOneBy(['roomToken' => $token]);

        if (!$conference || $conference->getStatus() === 'ended' || $conference->getExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'This conference has expired or does not exist.');
            return $this->redirectToRoute('main_home');
        }

        $session = $request->getSession();
        $isHost = false;
        $userName = 'Guest';
        $identity = 'guest_' . bin2hex(random_bytes(4));

        if ($session->get('is_logged_in') && isset($session->get('user')['id'])) {
            $currUserId = (int) $session->get('user')['id'];
            $isHost = ($conference->getUser()->getIdUser() === $currUserId);

            $user = $userRepository->find($currUserId);
            if ($user) {
                $userName = $user->getFirstName() . ' ' . $user->getLastName();
            }
            $identity = 'u_' . $currUserId;
        }

        // Generate LiveKit token
        $lkToken = $this->liveKitService->generateToken($token, $identity, $userName);

        // Determine dynamic signaling URL
        $configUrl = $this->liveKitService->getLiveKitUrl(); // e.g. http://192.168.137.1:7880
        $parsedUrl = parse_url($configUrl);
        $lkPort = $parsedUrl['port'] ?? 7880;

        // If the user accessed via localhost, use localhost for signaling too.
        // This avoids LAN routing if browsing locally.
        $requestHost = $request->getHost();
        $signalingUrl = ($requestHost === 'localhost' || $requestHost === '127.0.0.1')
            ? "http://localhost:{$lkPort}"
            : $configUrl;

        return $this->render('frontend/video_conference/room.html.twig', [
            'conference' => $conference,
            'isHost' => $isHost,
            'hostIdentity' => 'u_' . $conference->getUser()->getIdUser(),
            'roomToken' => $token,
            'livekitToken' => $lkToken,
            'livekitUrl' => $signalingUrl
        ]);
    }

    #[Route('/info/{token}', name: 'video_conf_info', methods: ['GET'])]
    public function info(string $token, VideoConferenceRepository $repo, Request $request): Response
    {
        $conference = $repo->findOneBy(['roomToken' => $token]);

        if (!$conference || $conference->getStatus() === 'ended' || $conference->getExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'Conference not found or expired'], Response::HTTP_NOT_FOUND);
        }

        $session = $request->getSession();
        $isHost = false;
        if ($session->get('is_logged_in') && isset($session->get('user')['id'])) {
            $isHost = ($conference->getUser()->getIdUser() === (int) $session->get('user')['id']);
        }

        return $this->json([
            'success' => true,
            'roomToken' => $token,
            'hostName' => $conference->getUser()->getFirstName() . ' ' . $conference->getUser()->getLastName(),
            'isHost' => $isHost,
            'createdAt' => $conference->getCreatedAt()->format('H:i')
        ]);
    }
}
