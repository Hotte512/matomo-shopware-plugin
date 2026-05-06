<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class SiteSearchSubscriber implements EventSubscriberInterface
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
            SearchPageLoadedEvent::class => 'onSearch',
        ];
    }

    public function onSearch(SearchPageLoadedEvent $event): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $page = $event->getPage();
        $request = $event->getRequest();
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        $keyword = (string) $page->getSearchTerm();
        if ($keyword === '') {
            return;
        }

        $payload = $this->payloadBuilder->siteSearch(
            $url,
            $this->visitorIdResolver->resolve($event->getSalesChannelContext()),
            $keyword,
            'searchPage',
            $page->getListing()->getTotal()
        );

        $this->tracker->track($payload);
    }
}
