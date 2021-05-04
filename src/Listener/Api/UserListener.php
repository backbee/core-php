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

namespace BackBeeCloud\Listener\Api;

use BackBeeCloud\Security\GroupType\CannotAddOrRemoveUserToClosedGroupTypeException;
use BackBeeCloud\Security\GroupType\GroupTypeManager;
use BackBeeCloud\User\UserManager;
use BackBee\BBApplication;
use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Controller\Event\PreRequestEvent;
use BackBee\Event\Event;
use BackBee\Security\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserListener
{
    const LOGIN_ALREADY_USED_MESSAGE_REGEX = '/^User with that login already exists: (.*)$/';
    const CANNOT_DELETE_CURRENT_USER_MESSAGE_REGEX = '/^You can remove the user of your current session\.$/';

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var GroupTypeManager
     */
    private $groupTypeManager;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var \BackBee\Security\Token\BBUserToken
     */
    private $bbtoken;

    public function __construct(
        UserManager $userManager,
        GroupTypeManager $groupTypeManager,
        EntityManager $entityManager,
        BBApplication $application
    ) {
        $this->userManager = $userManager;
        $this->groupTypeManager = $groupTypeManager;
        $this->entityManager = $entityManager;
        $this->bbtoken = $application->getBBUserToken();
    }

    public function onRestUserPostActionPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (!$response->isSuccessful()) {
            $this->entityManager->rollback();

            return;
        }

        $request = $event->getRequest();
        if (false == $groupTypes = $request->request->get('group_types', [])) {
            return;
        }

        $this->entityManager->beginTransaction();

        $rawData = json_decode($response->getContent(), true);
        try {
            $this->runAssignUserToGroupTypes(
                $this->userManager->getById($rawData['id']),
                $groupTypes
            );
        } catch (\Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->entityManager->commit();

        $response->setContent(
            json_encode(
                $this->formatRawUserData($rawData)
            )
        );
    }

    public function onRestUserPutActionPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (!$response->isSuccessful()) {
            $this->entityManager->rollback();

            return;
        }

        $this->entityManager->beginTransaction();

        $request = $event->getRequest();
        $user = $this->userManager->getById($request->attributes->get('id'));
        foreach ($user->getGroups() as $group) {
            $groupType = $this->groupTypeManager->getByGroup($group);
            $groupType->removeUser($user);
        }

        $this->entityManager->flush();
        $this->entityManager->refresh($user);

        try {
            $this->runAssignUserToGroupTypes(
                $user,
                $request->request->get('group_types', [])
            );
        } catch (\Exception $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->entityManager->commit();
    }

    /**
     * Occurs on "rest.user.creation" to enable the user.
     */
    public function onRestUserCreationEvent(Event $event)
    {
        $user = $event->getTarget();
        $user->setActivated(true);
        $user->setApiKeyEnabled(true);

        $this->entityManager->flush($user);
    }

    public function onGetCollectionPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (
            Response::HTTP_OK !== $response->getStatusCode()
            && 'application/json' !== $response->headers->get('content-type')
        ) {
            return;
        }

        $currentUser = $this->bbtoken ? $this->bbtoken->getUser() : null;
        $data = json_decode($response->getContent(), true);
        foreach ($data as &$row) {
            $row = $this->formatRawUserData($row, $currentUser);
        }

        $response->setContent(json_encode($data));
    }

    public function onGetPostCall(PostResponseEvent $event)
    {
        $response = $event->getResponse();
        if (
            Response::HTTP_OK !== $response->getStatusCode()
            && 'application/json' !== $response->headers->get('content-type')
        ) {
            return;
        }

        $currentUser = $this->bbtoken ? $this->bbtoken->getUser() : null;
        $data = json_decode($response->getContent(), true);
        $data = $this->formatRawUserData($data, $currentUser);

        $response->setContent(json_encode($data));
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if ($exception instanceof GroupTypeNotFoundException) {
            $event->setResponse(new JsonResponse([
                'error' => 'not_found',
                'reason' => 'group_type_not_found',
            ], Response::HTTP_NOT_FOUND));

            return;
        }

        if ($exception instanceof CannotAddOrRemoveUserToClosedGroupTypeException) {
            $event->setResponse(new JsonResponse([
                'error' => 'bad_request',
                'reason' => 'user_cannot_add_or_remove_from_closed_group_type',
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        if (1 === preg_match(self::LOGIN_ALREADY_USED_MESSAGE_REGEX, $exception->getMessage())) {
            $event->setResponse(new JsonResponse([
                'error' => 'bad_request',
                'reason' => 'login_already_used',
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        if (1 === preg_match(self::CANNOT_DELETE_CURRENT_USER_MESSAGE_REGEX, $exception->getMessage())) {
            $event->setResponse(new JsonResponse([
                'error' => 'bad_request',
                'reason' => 'cannot_delete_current_user',
            ], Response::HTTP_BAD_REQUEST));

            return;
        }
    }

    private function runAssignUserToGroupTypes(User $user, array $groupTypeIds)
    {
        foreach ($groupTypeIds as $groupTypeId) {
            if (null === $groupType = $this->groupTypeManager->getById($groupTypeId)) {
                throw GroupTypeNotFoundException::create($groupTypeId);
            }

            $groupType->addUser($user);
        }

        $this->entityManager->flush();
        $this->entityManager->refresh($user);
    }

    private function formatRawUserData(array $rawData, User $currentUser = null)
    {
        $isRemovable = true;
        if ($user = $this->userManager->getById($rawData['id'])) {
            if (
                $this->userManager->isMainUser($user)
                || ($currentUser && $currentUser->getId() === $user->getId())
            ) {
                $isRemovable = false;
            }

            $rawData['created'] = $user->getCreated()->format(DATE_ATOM);
            $rawData['modified'] = $user->getModified()->format(DATE_ATOM);

            $rawData['group_types'] = [];
            foreach ($user->getGroups() as $group) {
                if ($groupType = $this->groupTypeManager->getByGroup($group)) {
                    $rawData['group_types'][] = $groupType->getId();
                }
            }
        }

        $rawData['is_removable'] = $isRemovable;

        unset(
            $rawData['activated'],
            $rawData['api_key_enabled'],
            $rawData['api_key_public'],
            $rawData['groups'],
            $rawData['state']
        );

        return $rawData;
    }
}
