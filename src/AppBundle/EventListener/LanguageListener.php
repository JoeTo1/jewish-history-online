<?php

/*
 * Show 404 in domain-dependant localization
 *
 * see http://donna-oberes.blogspot.de/2014/01/symfony-internalizationlocalization-and.html
 *
 * register the listener in services.yml
 * services:
 *   # ...
 *
 *  # language-specific layout in 404
 *  app.language.kernel_request_listener:
 *      class: AppBundle\EventListener\LanguageListener
 *      tags:
 *         - { name: kernel.event_listener, event: kernel.exception, method: setLocale }
 *
 */
namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;


class LanguageListener
{
    public function setLocale(GetResponseEvent $event)
    {
        if (strstr($_SERVER['HTTP_HOST'], 'juedische-geschichte')
            || strstr($_SERVER['HTTP_HOST'], 'localhost'))
        {
            $request = $event->getRequest();
            $request->setLocale('de');
        }
    }
}
