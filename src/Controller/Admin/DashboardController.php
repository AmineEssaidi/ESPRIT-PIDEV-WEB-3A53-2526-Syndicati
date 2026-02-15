<?php

namespace App\Controller\Admin;

use App\Service\PageStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
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
