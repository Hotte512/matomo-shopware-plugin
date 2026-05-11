<?php declare(strict_types=1);

namespace Tinect\Matomo\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

class StaticHelper
{
    public static function getMatomoUrl(SystemConfigService $systemConfigService): ?string
    {
        $matomoServer = \trim(
            \rtrim($systemConfigService->getString('TinectMatomo.config.matomoserver'), '/')
        );

        if ($matomoServer === '') {
            return null;
        }

        if (\str_ends_with($matomoServer, 'matomo.php') || \str_ends_with($matomoServer, 'matomo.js')) {
            $matomoServer = \preg_replace('/\/(matomo\.php|matomo\.js)$/', '', $matomoServer);
        }

        return \rtrim($matomoServer, '/') . '/';
    }

    public static function getMatomoPhpEndpoint(SystemConfigService $systemConfigService): ?string
    {
        $phpTrackingPath = $systemConfigService->getString('TinectMatomo.config.phpTrackingPath');
        if ($phpTrackingPath === '') {
            $phpTrackingPath = 'matomo.php';
        }

        return self::getMatomoUrl($systemConfigService) . $phpTrackingPath;
    }

    public static function getMatomoJsEndpoint(SystemConfigService $systemConfigService): ?string
    {
        $jsTrackingPath = $systemConfigService->getString('TinectMatomo.config.jsTrackingPath');
        if ($jsTrackingPath === '') {
            $jsTrackingPath = 'matomo.js';
        }

        return self::getMatomoUrl($systemConfigService) . $jsTrackingPath;
    }

    public static function buildStorefrontUrl(Request $request): string
    {
        $baseUrl = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL);
        if (!\is_string($baseUrl) || $baseUrl === '') {
            $baseUrl = $request->getSchemeAndHttpHost();
        }

        $uri = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);
        if (!\is_string($uri) || $uri === '') {
            $uri = $request->getRequestUri();
        }

        return $baseUrl . $uri;
    }
}
