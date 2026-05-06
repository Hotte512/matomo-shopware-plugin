<?php declare(strict_types=1);

namespace Tinect\Matomo\Storefront\Controller;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Tinect\Matomo\MessageQueue\TrackMessage;
use Tinect\Matomo\Service\StaticHelper;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ProxyController extends AbstractController
{
    private int $cacheTime = 86400;

    /**
     * Parameters that the server itself authoritatively supplies, or that
     * Matomo only honours when accompanied by a privileged token_auth.
     * Stripping them from incoming proxy payloads prevents a remote caller
     * from forging visitor IPs, timestamps, geolocation or visitor-id
     * forcing through our admin-token-attached forwarder.
     */
    private const AUTH_REQUIRED_PARAMS = [
        'token_auth',
        'cip', 'cdt', 'cdo',
        'country', 'region', 'city', 'lat', 'long',
        'cid',
        'ua', 'lang',
    ];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * Inspired by https://arnowelzel.de/samples/piwik-tracker-proxy.txt
     */
    #[Route(path: '/mtmtrpr', name: 'frontend.matomo.proxy', defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function matomoProxy(Request $request): Response
    {
        $response = new Response();
        $response->headers->set('x-robots-tag', 'noindex,follow');

        $matomoServer = StaticHelper::getMatomoUrl($this->systemConfigService);

        if ($matomoServer === null) {
            return $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $expectedSiteId = \trim($this->systemConfigService->getString('TinectMatomo.config.matomosite'));

        if ($expectedSiteId === '') {
            return $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = $this->buildSanitizedPayload($request, $expectedSiteId);

        if ($payload === null) {
            return $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }

        $this->messageBus->dispatch(new TrackMessage(
            $request->getClientIp(),
            $request->server->getString('HTTP_USER_AGENT'),
            $request->server->getString('HTTP_ACCEPT_LANGUAGE'),
            time(),
            $payload
        ));

        return $response;
    }

    #[Route(path: '/mtmtrpr.js', name: 'frontend.matomo.js', methods: ['GET'])]
    public function matomoProxyJS(): Response
    {
        $response = new Response();

        $matomoJsUrl = StaticHelper::getMatomoJsEndpoint($this->systemConfigService);
        if ($matomoJsUrl === null) {
            return $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $modifiedSince = 0;
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $modifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
            // strip any trailing data appended to header
            if (false !== ($semicolon = strpos($modifiedSince, ';'))) {
                $modifiedSince = substr($modifiedSince, 0, $semicolon);
            }

            $modifiedSince = strtotime($modifiedSince);
        }

        $response->headers->set('Vary', 'Accept-Encoding');

        if ($modifiedSince && $modifiedSince > (time() - $this->cacheTime)) {
            return $response->setStatusCode(Response::HTTP_NOT_MODIFIED);
        }

        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $this->cacheTime) . ' GMT');
        $response->headers->set('Pragma', 'cache');
        $response->headers->set('Cache-Control', 'max-age=' . $this->cacheTime);
        $response->headers->set('Content-Type', 'application/javascript; charset=UTF-8');
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');

        $cacheValue = $this->cache->get('tinectmatomojs', function (ItemInterface $cacheItem) use ($matomoJsUrl) {
            $cacheItem->expiresAfter(new \DateInterval('PT' . $this->cacheTime . 'S'));

            return CacheValueCompressor::compress(file_get_contents($matomoJsUrl));
        });

        $matomoJs = CacheValueCompressor::uncompress($cacheValue);

        if (\is_string($matomoJs)) {
            // $matomoJs = str_replace(['"action_name="', 'idsite='], ['"aname="', 'ids='], $matomoJs);
            $response->setContent($matomoJs);
        } else {
            $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $response;
    }

    /**
     * Returns a sanitized payload ready for {@see TrackMessage}, or null
     * if the request is malformed or carries an idsite that does not match
     * the configured Matomo site.
     */
    private function buildSanitizedPayload(Request $request, string $expectedSiteId): array|string|null
    {
        $query = $request->query->all();
        if ($query !== []) {
            return $this->sanitizeArrayParams($query, $expectedSiteId);
        }

        $body = $request->getContent();
        if (!\is_string($body) || $body === '') {
            return null;
        }

        $trimmed = \ltrim($body);
        if ($trimmed !== '' && $trimmed[0] === '{') {
            return $this->sanitizeBulkJson($body, $expectedSiteId);
        }

        \parse_str($body, $parsed);
        if ($parsed === []) {
            return null;
        }

        return $this->sanitizeArrayParams($parsed, $expectedSiteId);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>|null
     */
    private function sanitizeArrayParams(array $params, string $expectedSiteId): ?array
    {
        $idsite = $params['idsite'] ?? null;
        if (!\is_scalar($idsite) || (string) $idsite !== $expectedSiteId) {
            return null;
        }

        foreach (self::AUTH_REQUIRED_PARAMS as $key) {
            unset($params[$key]);
        }

        return $params;
    }

    private function sanitizeBulkJson(string $body, string $expectedSiteId): ?string
    {
        try {
            $decoded = \json_decode($body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded) || !isset($decoded['requests']) || !\is_array($decoded['requests'])) {
            return null;
        }

        $sanitizedRequests = [];
        foreach ($decoded['requests'] as $req) {
            if (!\is_string($req)) {
                return null;
            }

            \parse_str(\ltrim($req, '?'), $parsed);
            $clean = $this->sanitizeArrayParams($parsed, $expectedSiteId);
            if ($clean === null) {
                return null;
            }

            $sanitizedRequests[] = '?' . \http_build_query($clean);
        }

        $decoded['requests'] = $sanitizedRequests;

        try {
            return \json_encode($decoded, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
