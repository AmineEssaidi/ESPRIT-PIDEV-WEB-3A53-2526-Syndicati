<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PageStatusService;

class AdminController extends AbstractController
{
    #[Route('/admin/users', name: 'admin_users')]
    public function users(PageStatusService $pageStatusService, Request $request): Response
    {
        return $this->render('admin/Users/index.html.twig');
    }
    #[Route('/admin/residence', name: 'admin_residence')]
    public function residence(PageStatusService $pageStatusService, Request $request): Response
    {
        return $this->render('admin/Residence/index.html.twig');
    }
    #[Route('/admin/residence', name: 'admin_appartement')]
    public function appartement(PageStatusService $pageStatusService, Request $request): Response
    {
        return $this->render('admin/Residence/indexAppartementBack.html.twig');
    }
    #[Route('/admin/forum', name: 'admin_forum')]
    public function forum(PageStatusService $pageStatusService, Request $request): Response
    {
        return $this->render('admin/Forum/index.html.twig');
    }

    #[Route('/admin/syndicat', name: 'admin_syndicat')]
    public function syndicat(PageStatusService $pageStatusService, Request $request): Response
    {
        return $this->render('admin/Syndicat/index.html.twig');
    }

    #[Route('/admin/evenement', name: 'admin_evenement')]
    public function evenement(PageStatusService $pageStatusService, Request $request): Response
    {
        return $this->render('admin/Evenement/index.html.twig');
    }
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(PageStatusService $pageStatusService, Request $request): Response
    {
        // Check if dashboard is offline
        if (!$pageStatusService->isPageOnline('dashboard')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'dashboard']);
        }
        
        // Sample transaction data - in a real application, this would come from a database
        $transactions = [
            [
                'type' => 'PayPal',
                'description' => 'Envoyer de l\'argent',
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
                'description' => 'Remboursement',
                'amount' => '+$637.91',
                'currency' => 'USD',
                'icon' => 'transfer'
            ],
            [
                'type' => 'Credit Card',
                'description' => 'Commande de nourriture',
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
                'description' => 'Commande de nourriture',
                'amount' => '-$92.45',
                'currency' => 'USD',
                'icon' => 'mastercard'
            ]
        ];

        return $this->render('admin/dashboard.html.twig', [
            'transactions' => $transactions,
        ]);
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
        
        // Frontend Pages
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
            ]
        ];
        
        // Backend Pages
        $backendPages = [
            [
                'id' => 'dashboard',
                'name' => 'Dashboard',
                'url' => '/admin',
                'status' => $pageStatuses['dashboard'] ?? 'online',
                'icon' => '🏠',
                'description' => 'Main admin dashboard'
            ],
            [
                'id' => 'super_dashboard',
                'name' => 'Super Dashboard',
                'url' => '/admin/super-dashboard',
                'status' => $pageStatuses['super_dashboard'] ?? 'online',
                'icon' => '⚡',
                'description' => 'Super admin control panel'
            ],
            [
                'id' => 'users',
                'name' => 'Users',
                'url' => '/admin/users',
                'status' => $pageStatuses['users'] ?? 'online',
                'icon' => '👥',
                'description' => 'User management page'
            ],
            [
                'id' => 'placeholder_1',
                'name' => 'Placeholder 1',
                'url' => '/admin/placeholder-1',
                'status' => $pageStatuses['placeholder_1'] ?? 'offline',
                'icon' => '📄',
                'description' => 'Admin placeholder page 1'
            ],
            [
                'id' => 'placeholder_2',
                'name' => 'Placeholder 2',
                'url' => '/admin/placeholder-2',
                'status' => $pageStatuses['placeholder_2'] ?? 'online',
                'icon' => '📋',
                'description' => 'Admin placeholder page 2'
            ]
        ];

        return $this->render('admin/super_dashboard.html.twig', [
            'transactions' => $transactions,
            'frontendPages' => $frontendPages,
            'backendPages' => $backendPages,
        ]);
    }

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
        if (strpos($url, '/admin/placeholder-1') !== false) {
            return 'Placeholder 1';
        } elseif (strpos($url, '/admin/placeholder-2') !== false) {
            return 'Placeholder 2';
        } elseif (strpos($url, '/admin/users') !== false) {
            return 'Users';
        } elseif (strpos($url, '/admin/super-dashboard') !== false) {
            return 'Super Dashboard';
        } elseif (strpos($url, '/admin') !== false) {
            return 'Dashboard';
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
            'dashboard' => 'Dashboard',
            'super_dashboard' => 'Super Dashboard',
            'users' => 'Users',
            'placeholder_1' => 'Placeholder 1',
            'placeholder_2' => 'Placeholder 2'
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
            'dashboard' => '/admin/super-dashboard',
            'super_dashboard' => '/admin',
            'users' => '/admin/super-dashboard',
            'placeholder_1' => '/admin/super-dashboard',
            'placeholder_2' => '/admin/super-dashboard'
        ];
        
        return $defaultReferrers[$pageId] ?? '/admin/super-dashboard';
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): Response
    {
        // This method will be intercepted by the logout key on your firewall
        throw new \Exception('This method should not be reached directly.');
    }
}