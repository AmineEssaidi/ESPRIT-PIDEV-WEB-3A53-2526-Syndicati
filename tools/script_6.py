# Create the Symfony controller
controller_code = '''<?php

namespace App\\Controller;

use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;
use Symfony\\Component\\HttpFoundation\\Response;
use Symfony\\Component\\Routing\\Annotation\\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        // Sample transaction data - in a real application, this would come from a database
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
                'description' => 'Mac\\'D',
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

        return $this->render('admin/dashboard.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): Response
    {
        // This method will be intercepted by the logout key on your firewall
        throw new \\Exception('This method should not be reached directly.');
    }
}'''

# Write controller
with open('src/Controller/AdminController.php', 'w') as f:
    f.write(controller_code)
    
print("✓ Created src/Controller/AdminController.php")