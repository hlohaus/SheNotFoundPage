<?php declare(strict_types=1);

namespace She\NotFoundPage\Components;

use She\NotFoundPage\SheNotFoundPage;
use Shopware\Core\Framework\Seo\SeoResolverInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestListener implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var HttpKernelInterface
     */
    private $httpKernel;

    /**
     * @var SeoResolverInterface
     */
    private $seoResolver;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    public function __construct(SystemConfigService $configService, HttpKernelInterface $httpKernel, SeoResolverInterface $seoResolver, SalesChannelContextServiceInterface $contextService)
    {
        $this->configService = $configService;
        $this->httpKernel = $httpKernel;
        $this->seoResolver = $seoResolver;
        $this->contextService = $contextService;
    }

    public function showNotFoundPage(ExceptionEvent $event)
    {
        $request = $event->getRequest();

        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        /** @var HttpException $exception */
        $exception = $event->getException();

        if (!$exception instanceof HttpException || $exception->getStatusCode() !== 404) {
            return;
        }

        if (!$event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            //When no saleschannel context is resolved, we need to resolve it now.
            $this->setSalesChannelContext($event);
        }

        $channelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        /** @var string|null $url */
        $url = $this->configService->get(SheNotFoundPage::CONFIG_NAME, $channelId);

        if (empty($url)) {
            return;
        }

        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        $resolved = $this->seoResolver->resolveSeoPath($languageId, $channelId, $url);

        if (!empty($resolved['pathInfo'])) {
            $url = $resolved['pathInfo'];
        }

        $clone = $request->duplicate();
        $clone->server->set('REQUEST_URI', $url);
        $clone->attributes->set(RequestTransformer::SALES_CHANNEL_RESOLVED_URI, $url);
        $clone->headers->add($request->headers->all());

        try {
            $response = $this->httpKernel->handle($clone, HttpKernelInterface::SUB_REQUEST);
        } catch (\Exception $e) {
            return;
        }

        $response->setStatusCode($exception->getStatusCode());

        $event->setResponse($response);
    }

    /**
     * @see \Shopware\Storefront\Framework\Routing\StorefrontSubscriber::setSalesChannelContext
     */
    private function setSalesChannelContext(ExceptionEvent $event): void
    {
        $contextToken = $event->getRequest()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $context = $this->contextService->get(
            $salesChannelId,
            $contextToken,
            $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID)
        );
        $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => [
                ['showNotFoundPage'],
            ],
        ];
    }
}
