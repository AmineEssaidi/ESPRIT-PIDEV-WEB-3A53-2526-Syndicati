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
            'dashboard' => $session->get(self::SESSION_KEY . '_dashboard', 'online'),
            'main_home' => $session->get(self::SESSION_KEY . '_main_home', 'online'),
            'users' => $session->get(self::SESSION_KEY . '_users', 'online'),
            'placeholder_1' => $session->get(self::SESSION_KEY . '_placeholder_1', 'offline'),
            'placeholder_2' => $session->get(self::SESSION_KEY . '_placeholder_2', 'online')
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
