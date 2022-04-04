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

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Security\GroupType\CannotAddOrRemoveUserToClosedGroupTypeException;
use BackBeeCloud\Security\GroupType\CannotDeleteGroupTypeWithUsersException;
use BackBeeCloud\Security\GroupType\CannotDeleteReadOnlyException;
use BackBeeCloud\Security\GroupType\CannotUpdateReadOnlyGroupTypeException;
use BackBeeCloud\Security\GroupType\GroupTypeManager;
use BackBeeCloud\Security\GroupType\NameAlreadyUsedException;
use BackBeeCloud\Security\User\UserDataFormatter;
use BackBeeCloud\Security\UserRightConstants;
use BackBeeCloud\User\UserManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 */
class GroupTypeController extends AbstractController
{
    /**
     * @var GroupTypeManager
     */
    protected $groupTypeManager;

    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * Constructor.
     *
     * @param \BackBee\BBApplication                            $app
     * @param \BackBeeCloud\Security\GroupType\GroupTypeManager $groupTypeManager
     * @param \BackBeeCloud\User\UserManager                    $userManager
     */
    public function __construct(
        BBApplication $app,
        GroupTypeManager $groupTypeManager,
        UserManager $userManager
    ) {
        parent::__construct($app);

        $this->groupTypeManager = $groupTypeManager;
        $this->userManager = $userManager;
    }

    /**
     * Get collection.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getCollection(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        $term = $request->query->get('term', '');
        $result = $term
            ? $this->groupTypeManager->searchByTerm($term)
            : $this->groupTypeManager->getAllGroupTypes();

        return new JsonResponse($result, Response::HTTP_OK);
    }

    /**
     * Create.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|void
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        if ($response = $this->getBadRequestResponseOnInvalidData($request)) {
            return $response;
        }

        try {
            $groupType = $this->groupTypeManager->create(
                $request->request->get('name'),
                $request->request->get('description'),
                true,  // isOpen
                false, // readOnly
                $request->request->get('features_rights', []),
                $request->request->get('pages_rights', [])
            );
        } catch (NameAlreadyUsedException $exception) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'group_type_name_already_used',
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse($groupType, Response::HTTP_CREATED);
    }

    /**
     * Update.
     *
     * @param                                           $id
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response|void
     * @throws \Exception
     */
    public function update($id, Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        if ($response = $this->getBadRequestResponseOnInvalidData($request)) {
            return $response;
        }

        if (null === $groupType = $this->groupTypeManager->getById($id)) {
            return $this->getNotFoundResponseOnInvalidId();
        }

        try {
            $this->groupTypeManager->update(
                $groupType,
                $request->request->get('name'),
                $request->request->get('description'),
                $request->request->get('features_rights', []),
                $request->request->get('pages_rights', [])
            );
        } catch (NameAlreadyUsedException $exception) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'group_type_name_already_used',
                Response::HTTP_BAD_REQUEST
            );
        } catch (CannotUpdateReadOnlyGroupTypeException $exception) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'cannot_update_read_only_group_type',
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete.
     *
     * @param $id
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function delete($id)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        if (null === $groupType = $this->groupTypeManager->getById($id)) {
            return $this->getNotFoundResponseOnInvalidId();
        }

        try {
            $this->groupTypeManager->delete($groupType);
        } catch (CannotDeleteGroupTypeWithUsersException $exception) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'cannot_delete_group_type_with_users',
                Response::HTTP_BAD_REQUEST
            );
        } catch (CannotDeleteReadOnlyException $exception) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'cannot_delete_read_only_group_type',
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Get group type users collection.
     *
     * @param $groupTypeId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getGroupTypeUsersCollection($groupTypeId): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        if (null === $groupType = $this->groupTypeManager->getById($groupTypeId)) {
            return $this->getNotFoundResponseOnInvalidId();
        }

        $bbUser = $this->securityContext->getToken() ? $this->securityContext->getToken()->getUser() : null;

        return new JsonResponse(
            array_map(static function ($user) use ($bbUser) {
                return UserDataFormatter::format($user, $bbUser);
            }, $groupType->getUsers())
        );
    }

    /**
     * Associate group id with user id.
     *
     * @param $groupTypeId
     * @param $userId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function linkUser($groupTypeId, $userId)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        if (null === $groupType = $this->groupTypeManager->getById($groupTypeId)) {
            return $this->getNotFoundResponseOnInvalidId();
        }

        if (null === $user = $this->userManager->getById($userId)) {
            return $this->getBadRequestResponseOnInvalidUserId();
        }

        try {
            $this->groupTypeManager->addUserToGroupType($groupType, $user);
        } catch (CannotAddOrRemoveUserToClosedGroupTypeException $exception) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'cannot_add_user_to_closed_group_type',
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete user.
     *
     * @param $groupTypeId
     * @param $userId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function deleteUser($groupTypeId, $userId)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::USER_RIGHT_FEATURE
        );

        if (null === $groupType = $this->groupTypeManager->getById($groupTypeId)) {
            return $this->getNotFoundResponseOnInvalidId();
        }

        if (null === $user = $this->userManager->getById($userId)) {
            return $this->getBadRequestResponseOnInvalidUserId();
        }

        try {
            $this->groupTypeManager->removeUserFromGroupType($groupType, $user);
        } catch (CannotAddOrRemoveUserToClosedGroupTypeException $execption) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'cannot_remove_user_from_closed_group_type',
                Response::HTTP_BAD_REQUEST
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Return json response with invalid user id error.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function getBadRequestResponseOnInvalidUserId(): JsonResponse
    {
        return $this->createErrorJsonResponse(
            'bad_request',
            'user_id_not_found',
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Return json response with invalid data error.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|void
     */
    private function getBadRequestResponseOnInvalidData(Request $request)
    {
        if (false === $request->request->get('name')) {
            return $this->createErrorJsonResponse(
                'bad_request',
                'name_is_required',
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Return json response with group type id not found error.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function getNotFoundResponseOnInvalidId(): JsonResponse
    {
        return $this->createErrorJsonResponse(
            'not_found',
            'group_type_id_not_found',
            Response::HTTP_NOT_FOUND
        );
    }
}
