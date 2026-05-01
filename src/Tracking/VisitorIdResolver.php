<?php declare(strict_types=1);

namespace Tinect\Matomo\Tracking;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class VisitorIdResolver
{
    public function __construct(
        private readonly string $appSecret,
        private readonly RequestStack $requestStack
    ) {
    }

    public function resolve(?SalesChannelContext $context = null): string
    {
        $customerId = $context?->getCustomer()?->getId();

        if ($customerId !== null) {
            return $this->hash('customer:' . $customerId);
        }

        $sessionId = $this->getSessionId();
        if ($sessionId !== null) {
            return $this->hash('session:' . $sessionId);
        }

        return $this->hash('anon:' . bin2hex(random_bytes(8)));
    }

    public function resolveForCustomerId(string $customerId): string
    {
        return $this->hash('customer:' . $customerId);
    }

    private function getSessionId(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            return null;
        }

        $id = $session->getId();

        return $id !== '' ? $id : null;
    }

    private function hash(string $input): string
    {
        return substr(hash('sha256', $this->appSecret . '|' . $input), 0, 16);
    }
}
