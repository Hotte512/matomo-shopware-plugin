<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class PageViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ServerSideTracker $tracker,
        private readonly TrackingPayloadBuilder $payloadBuilder,
        private readonly VisitorIdResolver $visitorIdResolver
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onPage',
            AccountLoginPageLoadedEvent::class => 'onPage',
            AccountOverviewPageLoadedEvent::class => 'onPage',
        ];
    }

    public function onPage(PageLoadedEvent $event): void
    {
        if (!$this->tracker->isServerMode()) {
            return;
        }

        $request = $event->getRequest();
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        $route = $request->attributes->get('_route');
        $actionName = $this->resolveActionName($event, \is_string($route) ? $route : null);

        $payload = $this->payloadBuilder->pageView(
            $url,
            $actionName,
            $this->visitorIdResolver->resolve($event->getSalesChannelContext())
        );

        $this->tracker->track($payload);
    }

    private function resolveActionName(PageLoadedEvent $event, ?string $route): string
    {
        if ($event instanceof NavigationPageLoadedEvent) {
            $category = $event->getSalesChannelContext()->getSalesChannel()->getNavigationCategory();
            if ($category !== null) {
                $name = $category->getTranslation('name') ?? $category->getName();
                if (\is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        return $route ?? 'page';
    }
}
