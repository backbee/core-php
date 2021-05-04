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

namespace BackBeeCloud\Listener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class RequestListener
{
    const COLLECTION_MAX_ITEM = 50;

    /**
     * Executed on dispatch of `kernel.request` event.
     *
     * @param  GetResponseEvent $event
     */
    public static function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        self::runConvertJsonContent($event, $request);
        self::runConvertRange($event, $request);
    }

    protected static function runConvertJsonContent(GetResponseEvent $event, Request $request)
    {
        if (false == $request->getContent()) {
            return;
        }

        if ('json' !== $request->getContentType()) {
            if (1 === preg_match('#^/api/#', $request->getPathInfo())) {
                $event->setResponse(new JsonResponse([
                    'error'  => 'not_acceptable',
                    'reason' => 'Expected json as request content type',
                ], Response::HTTP_NOT_ACCEPTABLE));
            }

            return;
        }

        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $event->setResponse(new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "Invalid json provided: " . json_last_error_msg(),
            ], Response::HTTP_BAD_REQUEST));
        }

        $request->request = new ParameterBag((array) $data);
    }

    protected static function runConvertRange(GetResponseEvent $event, Request $request)
    {
        if (1 !== preg_match('#^/api/#', $request->getPathInfo())) {
            return;
        }

        if (!$request->query->has('range')) {
            return;
        }

        $range = $request->query->get('range');
        $request->query->remove('range');
        if (1 !== preg_match('#^([0-9]+)-([0-9]+)$#', $range)) {
            $event->setResponse(new JsonResponse([
                'error'  => 'bad_request',
                'reason' => 'Invalid range format provided, it must respect the following pattern: N-N',
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        list($start, $end) = sscanf($range, '%d-%d');
        if ($start > $end) {
            $event->setResponse(new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "Range's start cannot be greater than range's end: $range",
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        $limit = $end - $start + 1;
        $maxItem = self::COLLECTION_MAX_ITEM;
        if ($maxItem < $limit) {
            $event->setResponse(new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "You try to get {$limit} items but you cannot get more than {$maxItem} per request",
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        $request->attributes->set('start', $start);
        $request->attributes->set('limit', $limit);
    }
}
