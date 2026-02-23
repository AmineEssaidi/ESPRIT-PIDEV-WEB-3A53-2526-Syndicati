<?php

namespace App\Controller\VideoConference;

use App\Entity\VideoConference\VideoConference;
use App\Repository\VideoConference\VideoConferenceRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/video-conference')]
class VideoConferenceController extends AbstractController
{
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

            $roomToken = Uuid::v4()->toBase58();

            $conference = new VideoConference();
            $conference->setRoomToken($roomToken);
            $conference->setUser($user);
            $conference->setCreatedAt(new \DateTime());
            $conference->setExpiresAt((new \DateTime())->modify('+2 hours'));
            $conference->setStatus('live');

            $em->persist($conference);
            $em->flush();

            $hostHost = $request->getHttpHost();
            $joinUrl = $this->generateUrl('video_conf_room', ['token' => $roomToken]);
            if (str_starts_with($hostHost, 'localhost') || str_starts_with($hostHost, '127.0.0') || str_starts_with($hostHost, '::1') || str_starts_with($hostHost, '[')) {
                $ips = [];
                exec('ipconfig', $output);
                foreach ($output as $line) {
                    if (preg_match('/IPv4 Address.*: (192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+)/', $line, $matches)) {
                        $ip = trim($matches[1]);
                        if (!str_ends_with($ip, '.1')) {
                            array_unshift($ips, $ip);
                        } else {
                            $ips[] = $ip;
                        }
                    }
                }
                $lanIp = !empty($ips) ? $ips[0] : ($request->server->get('SERVER_ADDR') ?: '127.0.0.1');
                $port = $request->getPort();
                $portStr = ($port == 80 || $port == 443) ? '' : ':' . $port;
                $joinUrl = $request->getScheme() . '://' . $lanIp . $portStr . $joinUrl;
            } else {
                $joinUrl = $request->getSchemeAndHttpHost() . $joinUrl;
            }

            return $this->json([
                'success' => true,
                'roomToken' => $roomToken,
                'joinUrl' => $joinUrl
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Database error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/room/{token}', name: 'video_conf_room')]
    public function room(string $token, VideoConferenceRepository $repo, Request $request): Response
    {
        $conference = $repo->findOneBy(['roomToken' => $token]);

        if (!$conference || $conference->getStatus() === 'ended' || $conference->getExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'This conference has expired or does not exist.');
            return $this->redirectToRoute('main_home');
        }

        $session = $request->getSession();
        $isHost = false;
        if ($session->get('is_logged_in') && isset($session->get('user')['id'])) {
            $isHost = ($conference->getUser()->getIdUser() === (int) $session->get('user')['id']);
        }

        $hostHost = $request->getHttpHost();
        $joinUrl = $this->generateUrl('video_conf_room', ['token' => $token]);
        if (str_starts_with($hostHost, 'localhost') || str_starts_with($hostHost, '127.0.0') || str_starts_with($hostHost, '::1') || str_starts_with($hostHost, '[')) {
            $ips = [];
            exec('ipconfig', $output);
            foreach ($output as $line) {
                if (preg_match('/IPv4 Address.*: (192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+)/', $line, $matches)) {
                    $ip = trim($matches[1]);
                    if (!str_ends_with($ip, '.1')) {
                        array_unshift($ips, $ip);
                    } else {
                        $ips[] = $ip;
                    }
                }
            }
            $lanIp = !empty($ips) ? $ips[0] : ($request->server->get('SERVER_ADDR') ?: '127.0.0.1');
            $port = $request->getPort();
            $portStr = ($port == 80 || $port == 443) ? '' : ':' . $port;
            $joinUrl = $request->getScheme() . '://' . $lanIp . $portStr . $joinUrl;
        } else {
            $joinUrl = $request->getSchemeAndHttpHost() . $joinUrl;
        }

        return $this->render('frontend/video_conference/room.html.twig', [
            'conference' => $conference,
            'isHost' => $isHost,
            'joinUrl' => $joinUrl,
            'roomToken' => $token
        ]);
    }

    #[Route('/heartbeat/{token}', name: 'video_conf_heartbeat', methods: ['POST'])]
    public function heartbeat(string $token, Request $request, VideoConferenceRepository $repo, EntityManagerInterface $em): Response
    {
        $conference = $repo->findOneBy(['roomToken' => $token]);
        if (!$conference) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        $session = $request->getSession();

        $content = $request->getContent();
        $data = [];
        if (!empty($content)) {
            $data = json_decode($content, true) ?? [];
        }
        if (empty($data)) {
            $data = $request->request->all();
        }

        $peerId = $data['peerId'] ?? null;
        if (!$peerId) {
            if (!$session->isStarted())
                $session->start();
            $peerId = $session->getId();
        }

        $user = null;
        $guestName = $data['guestName'] ?? 'Guest';

        if ($session->get('is_logged_in') && isset($session->get('user')['id'])) {
            $user = $em->getRepository(\App\Entity\User\User::class)->find((int) $session->get('user')['id']);
            if ($user) {
                $guestName = $user->getFirstName() . ' ' . $user->getLastName();
            }
        }

        $participantRepo = $em->getRepository(\App\Entity\VideoConference\VideoConferenceParticipant::class);
        $participant = $participantRepo->findOneBy([
            'conference' => $conference,
            'sessionId' => $peerId
        ]);

        if (!$participant) {
            $participant = new \App\Entity\VideoConference\VideoConferenceParticipant();
            $participant->setConference($conference);
            $participant->setSessionId($peerId);
            $participant->setUser($user);
            $participant->setGuestName($guestName);
            // Ensure signaling data is cleared for new registration
            $participant->setSignalingData(null);
        }

        $participant->setLastActive(new \DateTime());
        $em->persist($participant);
        $em->flush();

        return $this->json(['success' => true, 'sessionId' => $peerId]);
    }

    #[Route('/participants/{token}', name: 'video_conf_participants', methods: ['GET'])]
    public function participants(string $token, VideoConferenceRepository $repo, EntityManagerInterface $em): Response
    {
        $conference = $repo->findOneBy(['roomToken' => $token]);
        if (!$conference) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        // Lenient 60-second window to handle minor network jitter
        $activeLimit = (new \DateTime())->modify('-60 seconds');

        $participants = $em->getRepository(\App\Entity\VideoConference\VideoConferenceParticipant::class)
            ->createQueryBuilder('p')
            ->where('p.conference = :conf')
            ->setParameter('conf', $conference)
            ->orderBy('p.lastActive', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($participants as $p) {
            $data[] = [
                'name' => $p->getGuestName(),
                'isHost' => ($conference->getUser() && $p->getUser() && $p->getUser()->getIdUser() === $conference->getUser()->getIdUser()),
                'isRoomOwner' => ($conference->getUser() && $p->getUser() && $p->getUser()->getIdUser() === $conference->getUser()->getIdUser()),
                'id' => $p->getId(),
                'sessionId' => $p->getSessionId(),
                'signal' => $p->getSignalingData()
            ];
        }

        return $this->json([
            'participants' => $data,
            'serverTime' => (new \DateTime())->format('H:i:s'),
            'timestamp' => time()
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

    #[Route('/signal/{token}', name: 'video_conf_signal', methods: ['POST'])]
    public function signal(string $token, Request $request, VideoConferenceRepository $repo, EntityManagerInterface $em): Response
    {
        $conference = $repo->findOneBy(['roomToken' => $token]);
        if (!$conference) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        $content = $request->getContent();

        // Try parsing JSON first
        $data = [];
        if (!empty($content)) {
            $data = json_decode($content, true) ?? [];
        }

        // Fallback to standard POST data if JSON parsing failed or was empty
        if (empty($data)) {
            $data = $request->request->all();
        }

        $peerId = $data['peerId'] ?? null;
        if (!$peerId) {
            error_log("VideoConf Signal Error: Missing peerId. Content length: " . strlen($content));
            return $this->json(['error' => 'Peer ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $signalData = $data['signal'] ?? null;

        $participant = $em->getRepository(\App\Entity\VideoConference\VideoConferenceParticipant::class)->findOneBy([
            'conference' => $conference,
            'sessionId' => $peerId
        ]);

        if ($participant) {
            $participant->setSignalingData($signalData);
            $em->persist($participant);
            $em->flush();
            return $this->json(['success' => true]);
        }

        return $this->json(['error' => 'Participant not found'], Response::HTTP_NOT_FOUND);
    }
}
