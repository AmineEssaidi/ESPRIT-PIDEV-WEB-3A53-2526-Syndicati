<?php

namespace App\Controller\Admin;

use App\Service\PageStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SystemController extends AbstractController
{
    #[Route('/admin/api/page-status', name: 'admin_page_status_update', methods: ['POST'])]
    public function updatePageStatus(Request $request, PageStatusService $pageStatusService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['pageId']) || !isset($data['status'])) {
            return new JsonResponse(['error' => 'Missing pageId or status'], 400);
        }

        $pageId = $data['pageId'];
        $status = $data['status'];

        // Validate status
        if (!in_array($status, ['online', 'offline'])) {
            return new JsonResponse(['error' => 'Invalid status'], 400);
        }

        // Update page status
        $pageStatusService->setPageStatus($pageId, $status);

        return new JsonResponse([
            'success' => true,
            'pageId' => $pageId,
            'status' => $status
        ]);
    }

    #[Route('/maintenance', name: 'maintenance')]
    #[Route('/maintenance/{pageId}', name: 'maintenance_with_page')]
    public function maintenance(Request $request, ?string $pageId = null): Response
    {
        // Get the referrer to determine which page was being accessed
        $referer = $request->headers->get('referer');

        // If we have a pageId parameter, use that instead of referrer
        if ($pageId) {
            $previousPage = $this->getPageNameFromId($pageId);
            // For the back button, try to determine a reasonable referrer
            if (!$referer || strpos($referer, '/maintenance') !== false) {
                $referer = $this->getDefaultReferrerForPage($pageId);
            }
        } else {
            // If the referrer is the maintenance page itself (refresh case), don't override
            if ($referer && strpos($referer, '/maintenance') !== false) {
                $referer = null; // Let JavaScript handle it with localStorage
                $previousPage = null;
            } else {
                $previousPage = $this->getPageNameFromUrl($referer);
            }
        }

        return $this->render('maintenance.html.twig', [
            'previousPage' => $previousPage,
            'referer' => $referer
        ]);
    }

    /**
     * Get page name from URL for display purposes
     */
    private function getPageNameFromUrl(?string $url): string
    {
        if (!$url) {
            return 'Dashboard';
        }

        // Map URLs to page names
        if (strpos($url, '/admin/super-dashboard') !== false) {
            return 'Super Dashboard';
        } elseif (strpos($url, '/admin/users') !== false) {
            return 'Users';
        } elseif (strpos($url, '/publication/admin') !== false) {
            return 'Forum Admin';
        } elseif (strpos($url, '/syndicat/reclamation/admin') !== false) {
            return 'Syndicat Admin';
        } elseif (strpos($url, '/evenement/admin') !== false) {
            return 'Events Admin';
        } elseif (strpos($url, '/admin') !== false) {
            return 'Dashboard';
        } elseif (strpos($url, '/forum') !== false) {
            return 'Forum';
        } elseif (strpos($url, '/syndicat') !== false) {
            return 'Syndicat';
        } elseif (strpos($url, '/evenement') !== false) {
            return 'Evenements';
        } elseif (strpos($url, '/residence') !== false) {
            return 'Residences';
        } elseif (strpos($url, '/profile') !== false) {
            return 'Profile';
        } elseif (strpos($url, '/') !== false) {
            return 'Main Home';
        }

        return 'Dashboard';
    }

    /**
     * Get page name from page ID for display purposes
     */
    private function getPageNameFromId(string $pageId): string
    {
        $pageNames = [
            'main_home' => 'Main Home',
            'profile' => 'Profile',
            'forum' => 'Forum',
            'syndicat' => 'Syndicat',
            'evenement' => 'Evenements',
            'residence' => 'Residences',
            'dashboard' => 'Dashboard',
            'super_dashboard' => 'Super Dashboard',
            'users' => 'Users',
            'admin_forum' => 'Forum Admin',
            'admin_syndicat' => 'Syndicat Admin',
            'admin_evenement' => 'Events Admin',
        ];

        return $pageNames[$pageId] ?? 'Page';
    }

    /**
     * Get default referrer URL for a page (where to go back to)
     */
    private function getDefaultReferrerForPage(string $pageId): string
    {
        $defaultReferrers = [
            'main_home' => '/',
            'profile' => '/',
            'forum' => '/forum',
            'syndicat' => '/syndicat',
            'evenement' => '/evenement',
            'residence' => '/residence',
            'dashboard' => '/admin',
            'super_dashboard' => '/admin/super-dashboard',
            'users' => '/admin/users',
            'admin_forum' => '/publication/admin',
            'admin_syndicat' => '/syndicat/reclamation/admin',
            'admin_evenement' => '/evenement/admin',
        ];

        return $defaultReferrers[$pageId] ?? '/admin/super-dashboard';
    }
}
