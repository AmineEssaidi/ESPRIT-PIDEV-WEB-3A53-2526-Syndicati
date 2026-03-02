<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class PageStatusService
{
    private RequestStack $requestStack;
    private const SESSION_KEY = 'page_status';

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Get the status of a specific page
     */
    public function getPageStatus(string $pageId): string
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::SESSION_KEY . '_' . $pageId, 'online');
    }

    /**
     * Set the status of a specific page
     */
    public function setPageStatus(string $pageId, string $status): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY . '_' . $pageId, $status);
    }

    /**
     * Get all page statuses
     */
    public function getAllPageStatuses(): array
    {
        $session = $this->requestStack->getSession();
        return [
            // Frontend
            'main_home' => $session->get(self::SESSION_KEY . '_main_home', 'online'),
            'profile' => $session->get(self::SESSION_KEY . '_profile', 'online'),
            'forum' => $session->get(self::SESSION_KEY . '_forum', 'online'),
            'syndicat' => $session->get(self::SESSION_KEY . '_syndicat', 'online'),
            'evenement' => $session->get(self::SESSION_KEY . '_evenement', 'online'),
            'residence' => $session->get(self::SESSION_KEY . '_residence', 'online'),
            // Backend
            'dashboard' => $session->get(self::SESSION_KEY . '_dashboard', 'online'),
            'super_dashboard' => $session->get(self::SESSION_KEY . '_super_dashboard', 'online'),
            'users' => $session->get(self::SESSION_KEY . '_users', 'online'),
            'admin_forum' => $session->get(self::SESSION_KEY . '_admin_forum', 'online'),
            'admin_syndicat' => $session->get(self::SESSION_KEY . '_admin_syndicat', 'online'),
            'admin_evenement' => $session->get(self::SESSION_KEY . '_admin_evenement', 'online'),
        ];
    }

    /**
     * Check if a page is online
     */
    public function isPageOnline(string $pageId): bool
    {
        return $this->getPageStatus($pageId) === 'online';
    }

}
