<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBeeCloud\ReCaptcha;

use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Renderer\Event\RendererEvent;
use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Class ReCaptchaListener
 *
 * @package BackBeeCloud\ReCaptcha
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ReCaptchaListener
{
    /**
     * Listens to:
     *
     * - "basic.contact.render"
     * - "basic.newsletter.render"
     *
     *  To inject 'recaptcha_sitekey' to view if current host is authorized.
     *
     * @param RendererEvent $event
     */
    public static function onContentRequireReCaptchaRender(RendererEvent $event): void
    {
        $settings = $event->getApplication()->getConfig()->getSection('recaptcha');
        if (false === $settings) {
            return;
        }

        $host = $event->getApplication()->getRequest()->getHost();
        if (!self::isAuthorizedHost($host, $settings['authorized_hosts'])) {
            return;
        }

        $event->getRenderer()->assign('recaptcha_sitekey', $settings['site_key']);
    }

    /**
     * Listens to:
     *
     * - "backbeecloud.controller.contactcontroller.send.precall"
     * - "backbeecloud.controller.newslettercontroller.send.precall"
     *
     * To check that current user is not a bot by using Google reCAPTCHA.
     *
     * Note that nothing will be done if there is a valid BBUserToken.
     *
     * @param PreRequestEvent $event The event to use
     *
     * @throws RecaptchaFailedValidationException if reCAPTCHA failed to validate current user
     *                                            or if the host name is different between
     *                                            reCAPTCHA and $_SERVER['SERVER_NAME']
     */
    public static function onReCaptchaFormSubmissionPreCall(PreRequestEvent $event): void
    {
        $app = $event->getApplication();
        $request = $event->getRequest();

        if (
            null !== $app->getBBUserToken() ||
            false === ($settings = $app->getConfig()->getSection('recaptcha')) ||
            !self::isAuthorizedHost($request->getHost(), $settings['authorized_hosts'])
        ) {
            return;
        }

        $recaptcha = new ReCaptcha($settings['secret']);
        $response = $recaptcha->verify(
            $request->request->get('g-recaptcha-response'),
            $request->getClientIp()
        );

        if ($response->isSuccess() && $response->getHostName() === $request->getHost()) {
            return;
        }

        $errMsg = $response->isSuccess()
            ? 'not-matching-host-name'
            : implode(', ', $response->getErrorCodes());

        throw new RecaptchaFailedValidationException($errMsg);
    }

    /**
     * Listens to "kernel.exception" to catch and handle RecaptchaFailedValidationException
     * by transforming it into an instance of Response.
     *
     * @param GetResponseForExceptionEvent $event The event to use
     */
    public static function onRecaptchaFailedValidationException(GetResponseForExceptionEvent $event): void
    {
        $exception = $event->getException();
        if (!($exception instanceof RecaptchaFailedValidationException)) {
            return;
        }

        $event->setResponse(
            new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => str_replace('-', ' ', $event->getException()->getMessage()),
                ], Response::HTTP_BAD_REQUEST
            )
        );
    }

    /**
     * Checks if the provided host is authorized.
     *
     * @param string $host            The host to check
     * @param array  $authorizedHosts The list of authorized hosts
     *
     * @return bool true if the host is authorized, false otherwise
     */
    protected static function isAuthorizedHost(string $host, array $authorizedHosts): bool
    {
        foreach ($authorizedHosts as $authorized) {
            $pattern = str_replace('.', '\.', $authorized);
            if (1 === preg_match(sprintf('~%s$~', $pattern), $host)) {
                return true;
            }
        }

        return false;
    }
}
