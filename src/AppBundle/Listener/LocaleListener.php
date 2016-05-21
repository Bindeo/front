<?php

namespace AppBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LocaleListener implements EventSubscriberInterface
{
    private $defaultLocale;

    public function __construct($defaultLocale = 'en_US')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        // Try to see if the locale has been set as a _locale routing parameter or locale url param
        $locale = $request->attributes->get('_locale');
        if (!$locale) {
            $locale = $request->get('locale');
        }
        if ($locale) {
            $request->setLocale($locale);

            return;
        }

        if (!$request->hasPreviousSession()) {
            // For a fresh start we look for the locale value in a cookie
            if ($request->cookies->has('LOCALE')) {
                $locale = $request->cookies->get('LOCALE');
            } else {
                $locale = $request->getPreferredLanguage(['es', 'en']) == 'es' ? 'es_ES' : 'en_US';
            }

            $request->getSession()->set('_locale', $locale);
            $request->setLocale($locale);
        } else {
            if ($request->getSession()->has('_locale')) {
                $locale = $request->getSession()->get('_locale');
            } elseif ($request->cookies->has('LOCALE')) {
                $locale = $request->cookies->get('LOCALE');
                $request->getSession()->set('_locale', $locale);
            } else {
                $locale = $request->getPreferredLanguage(['es', 'en']) == 'es' ? 'es_ES' : 'en_US';
            }

            // If no explicit locale has been set on this request, use one from the session or the cookie
            $request->setLocale($locale);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // Must be registered before the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 17]],
        ];
    }
}