<?php

namespace AppBundle\Listener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class CookiesListener
{
    public function onKernelResponse(FilterResponseEvent $event)
    {
        // Check for cookies
        if (!$event->getRequest()->cookies->has('ACCEPT_COOKIES')) {
            $event->getResponse()->headers->setCookie(new Cookie('ACCEPT_COOKIES', true));
        }
    }
}