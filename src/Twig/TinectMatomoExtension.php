<?php declare(strict_types=1);

namespace Tinect\Matomo\Twig;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Tinect\Matomo\Tracking\VisitorIdResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TinectMatomoExtension extends AbstractExtension
{
    public function __construct(
        private readonly VisitorIdResolver $visitorIdResolver,
        private readonly RequestStack $requestStack
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tinect_matomo_visitor_id', [$this, 'getVisitorId']),
        ];
    }

    public function getVisitorId(): string
    {
        $request = $this->requestStack->getMainRequest();
        $context = $request?->attributes->get('sw-sales-channel-context');

        return $this->visitorIdResolver->resolve($context instanceof SalesChannelContext ? $context : null);
    }
}
