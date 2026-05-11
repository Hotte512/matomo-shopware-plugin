<?php declare(strict_types=1);

namespace Tinect\Matomo\Tracking;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Tinect\Matomo\MessageQueue\TrackMessage;
use Tinect\Matomo\Service\ConditionalLogger;
use Tinect\Matomo\Service\StaticHelper;

class ServerSideTracker
{
    public const MODE_CLIENT = 'client';
    public const MODE_PROXY = 'proxy';
    public const MODE_HYBRID = 'hybrid';
    public const MODE_SERVER = 'server';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack,
        private readonly MessageBusInterface $messageBus,
        private readonly ConditionalLogger $logger
    ) {
    }

    public function isServerMode(): bool
    {
        return $this->getMode() === self::MODE_SERVER;
    }

    /**
     * Returns true for both pure server-side mode and hybrid mode. Used by
     * subscribers for events that should always be tracked from PHP
     * (e.g. orders, customer register/login) even when JavaScript tracking
     * is also active.
     */
    public function isServerOrHybridMode(): bool
    {
        $mode = $this->getMode();

        return $mode === self::MODE_SERVER || $mode === self::MODE_HYBRID;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function track(array $payload): void
    {
        if (!$this->isServerOrHybridMode()) {
            return;
        }

        if ($this->systemConfigService->getString('TinectMatomo.config.matomoserver') === '') {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        $clientIp = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent', '') ?? '';
        $acceptLanguage = $request?->headers->get('Accept-Language', '') ?? '';
        $referer = $request?->headers->get('Referer');

        if ($referer !== null && !isset($payload['urlref'])) {
            $payload['urlref'] = $referer;
        }

        if ($request !== null && !isset($payload['url'])) {
            $payload['url'] = StaticHelper::buildStorefrontUrl($request);
        }

        try {
            $this->messageBus->dispatch(new TrackMessage(
                $clientIp,
                $userAgent,
                $acceptLanguage,
                time(),
                $payload
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to dispatch Matomo server-side tracking message.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getMode(): string
    {
        $mode = $this->systemConfigService->getString('TinectMatomo.config.trackingMode');

        return match ($mode) {
            self::MODE_PROXY, self::MODE_HYBRID, self::MODE_SERVER => $mode,
            default => self::MODE_CLIENT,
        };
    }

}
