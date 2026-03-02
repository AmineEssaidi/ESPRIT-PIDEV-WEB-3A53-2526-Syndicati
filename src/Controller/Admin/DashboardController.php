<?php

namespace App\Controller\Admin;

use App\Service\Admin\AdminDashboardService;
use App\Service\PageStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(
        PageStatusService $pageStatusService,
        AdminDashboardService $dashboardService,
        Request $request
    ): Response {
        // Check if dashboard is offline
        if (!$pageStatusService->isPageOnline('dashboard')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'dashboard']);
        }

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $dashboardService->getHeartbeatStats(),
            'trends' => $dashboardService->getInteractionTrends(),
            'topPages' => $dashboardService->getTopPages(),
            'topClicks' => $dashboardService->getTopClicks(),
            'recentActivity' => $dashboardService->getRecentActivity(),
            'community' => $dashboardService->getCommunityStats(),
            'deviceStats' => $dashboardService->getDeviceBreakdown(),
            'topUsers' => $dashboardService->getTopUsers(),
            'recentSignups' => $dashboardService->getRecentSignups(),
            'usersStats' => $dashboardService->getModuleStats('users'),
            'forumStats' => $dashboardService->getModuleStats('forum'),
            'syndicatStats' => $dashboardService->getModuleStats('syndicat'),
            'residenceStats' => $dashboardService->getModuleStats('residence'),
            'evenementStats' => $dashboardService->getModuleStats('evenement'),
        ]);
    }

    #[Route('/admin/dashboard/live', name: 'admin_dashboard_live', methods: ['GET'])]
    public function dashboardLive(Request $request, AdminDashboardService $dashboardService): Response
    {
        $module = $request->query->get('module', 'general');
        $data = ['html' => '', 'stats' => []];

        if ($module === 'general') {
            $data['stats'] = $dashboardService->getHeartbeatStats();
        } else {
            $stats = $dashboardService->getModuleStats($module);
            $data['html'] = $this->renderView('admin/dashboard/_tabs.html.twig', [
                'module' => $module,
                'stats' => $stats
            ]);
            $data['stats'] = $stats;
        }

        return $this->json($data);
    }

    #[Route('/admin/super-dashboard', name: 'admin_super_dashboard')]
    public function superDashboard(PageStatusService $pageStatusService, Request $request): Response
    {
        // Sample transaction data - same as regular dashboard
        $transactions = [
            [
                'type' => 'PayPal',
                'description' => 'Send money',
                'amount' => '+$82.6',
                'currency' => 'USD',
                'icon' => 'paypal'
            ],
            [
                'type' => 'Wallet',
                'description' => 'Mac\'D',
                'amount' => '+$270.69',
                'currency' => 'USD',
                'icon' => 'wallet'
            ],
            [
                'type' => 'Transfer',
                'description' => 'Refund',
                'amount' => '+$637.91',
                'currency' => 'USD',
                'icon' => 'transfer'
            ],
            [
                'type' => 'Credit Card',
                'description' => 'Ordered Food',
                'amount' => '-$838.71',
                'currency' => 'USD',
                'icon' => 'credit-card'
            ],
            [
                'type' => 'Wallet',
                'description' => 'Starbucks',
                'amount' => '+$203.33',
                'currency' => 'USD',
                'icon' => 'wallet'
            ],
            [
                'type' => 'Mastercard',
                'description' => 'Ordered Food',
                'amount' => '-$92.45',
                'currency' => 'USD',
                'icon' => 'mastercard'
            ]
        ];

        // Get page management data from service
        $pageStatuses = $pageStatusService->getAllPageStatuses();

        // Frontend Pages (Actual mapped routes)
        $frontendPages = [
            [
                'id' => 'main_home',
                'name' => 'Main Home',
                'url' => '/',
                'status' => $pageStatuses['main_home'] ?? 'online',
                'icon' => '🏡',
                'description' => 'Landing page for visitors'
            ],
            [
                'id' => 'profile',
                'name' => 'Profile',
                'url' => '/profile',
                'status' => $pageStatuses['profile'] ?? 'online',
                'icon' => '👤',
                'description' => 'User profile page'
            ],
            [
                'id' => 'forum',
                'name' => 'Forum',
                'url' => '/forum',
                'status' => $pageStatuses['forum'] ?? 'online',
                'icon' => '💬',
                'description' => 'Community discussion board'
            ],
            [
                'id' => 'syndicat',
                'name' => 'Syndicat',
                'url' => '/syndicat',
                'status' => $pageStatuses['syndicat'] ?? 'online',
                'icon' => '⚖️',
                'description' => 'Reclamations and Syndic portal'
            ],
            [
                'id' => 'evenement',
                'name' => 'Evenements',
                'url' => '/evenement',
                'status' => $pageStatuses['evenement'] ?? 'online',
                'icon' => '🎭',
                'description' => 'Community events and participations'
            ],
            [
                'id' => 'residence',
                'name' => 'Residences',
                'url' => '/residence',
                'status' => $pageStatuses['residence'] ?? 'online',
                'icon' => '🏢',
                'description' => 'Residence and Apartment management'
            ]
        ];

        // Backend Pages (Actual mapped routes)
        $backendPages = [
            [
                'id' => 'dashboard',
                'name' => 'Admin Dashboard',
                'url' => '/admin',
                'status' => $pageStatuses['dashboard'] ?? 'online',
                'icon' => '🏠',
                'description' => 'Main administration overview'
            ],
            [
                'id' => 'users',
                'name' => 'User Management',
                'url' => '/admin/users',
                'status' => $pageStatuses['users'] ?? 'online',
                'icon' => '👥',
                'description' => 'Staff and Resident accounts'
            ],
            [
                'id' => 'admin_forum',
                'name' => 'Forum Admin',
                'url' => '/publication/admin',
                'status' => $pageStatuses['admin_forum'] ?? 'online',
                'icon' => '🛠️',
                'description' => 'Moderate posts and comments'
            ],
            [
                'id' => 'admin_syndicat',
                'name' => 'Syndicat Admin',
                'url' => '/syndicat/reclamation/admin',
                'status' => $pageStatuses['admin_syndicat'] ?? 'online',
                'icon' => '📑',
                'description' => 'Manage active reclamations'
            ],
            [
                'id' => 'admin_evenement',
                'name' => 'Events Admin',
                'url' => '/evenement/admin',
                'status' => $pageStatuses['admin_evenement'] ?? 'online',
                'icon' => '📅',
                'description' => 'Organize and oversee events'
            ]
        ];

        return $this->render('admin/super_dashboard.html.twig', [
            'transactions' => $transactions,
            'frontendPages' => $frontendPages,
            'backendPages' => $backendPages,
        ]);
    }

    #[Route('/admin/placeholder-1', name: 'admin_placeholder_1')]
    public function placeholder1(PageStatusService $pageStatusService, Request $request): Response
    {
        // Check if placeholder 1 is offline
        if (!$pageStatusService->isPageOnline('placeholder_1')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'placeholder_1']);
        }

        return $this->render('admin/placeholder1.html.twig');
    }

    #[Route('/admin/placeholder-2', name: 'admin_placeholder_2')]
    public function placeholder2(PageStatusService $pageStatusService, Request $request): Response
    {
        // Check if placeholder 2 is offline
        if (!$pageStatusService->isPageOnline('placeholder_2')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'placeholder_2']);
        }

        return $this->render('admin/placeholder2.html.twig');
    }

    #[Route('/admin/super-dashboard-check', name: 'admin_super_dashboard_check')]
    public function superDashboardCheck(PageStatusService $pageStatusService, Request $request): Response
    {
        // Check if super dashboard is offline
        if (!$pageStatusService->isPageOnline('super_dashboard')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'super_dashboard']);
        }

        // Redirect to the actual super dashboard
        return $this->redirectToRoute('admin_super_dashboard');
    }
}