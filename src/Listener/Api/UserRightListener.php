<?php

/*
 * Copyright (c) 2022 Obione
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

use BackBee\Controller\Event\PostResponseEvent;
use BackBee\Rest\Controller\ClassContentController;
use BackBee\Rest\Controller\UserController;
use BackBee\Security\SecurityContext;
use BackBeeCloud\Entity\GlobalContent;
use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeManager;
use BackBeeCloud\Security\Authentication\UserRightUnauthorizedException;
use BackBeeCloud\Security\Authorization\UserRightAccessDeniedException;
use BackBeeCloud\Security\Authorization\Voter\UserRightPageAttribute;
use BackBeeCloud\Security\UserRightConstants;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_object;

/**
 * User right listener.
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightListener
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var SecurityContext
     */
    private $securityContext;

    /**
     * @var PageManager
     */
    private $pageManager;

    /**
     * @var TypeManager
     */
    private $pageTypeManager;

    /**
     * @var PageCategoryManager
     */
    private $pageCategoryManager;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface           $entityManager
     * @param \BackBee\Security\SecurityContext              $securityContext
     * @param \BackBeeCloud\Entity\PageManager               $pageManager
     * @param \BackBeeCloud\PageType\TypeManager             $pageTypeManager
     * @param \BackBeeCloud\PageCategory\PageCategoryManager $pageCategoryManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SecurityContext $securityContext,
        PageManager $pageManager,
        TypeManager $pageTypeManager,
        PageCategoryManager $pageCategoryManager
    ) {
        $this->entityManager = $entityManager;
        $this->securityContext = $securityContext;
        $this->pageManager = $pageManager;
        $this->pageTypeManager = $pageTypeManager;
        $this->pageCategoryManager = $pageCategoryManager;
    }

    /**
     * On kernel exception.
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        if (
            $event->getException() instanceof UserRightUnauthorizedException ||
            $event->getException() instanceof AuthenticationCredentialsNotFoundException
        ) {
            $event->setResponse(
                new JsonResponse([
                    'error' => 'unauthorized',
                    'reason' => 'request_aborted_because_not_authenticated',
                ], Response::HTTP_UNAUTHORIZED)
            );

            return;
        }

        if ($event->getException() instanceof UserRightAccessDeniedException) {
            $event->setResponse(
                new JsonResponse([
                    'error' => 'forbidden',
                    'reason' => 'access_denied_because_not_enough_right',
                ], Response::HTTP_FORBIDDEN)
            );

        }
    }

    /**
     * On kernel controller.
     *
     * @param \Symfony\Component\HttpKernel\Event\FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller) || !is_object($controller[0])) {
            return;
        }

        $classname = get_class($controller[0]);
        switch ($classname) {
            case UserController::class:
                if (
                    ('getCollectionAction' === $controller[1]) &&
                    !$this->securityContext->isGranted(
                        UserRightConstants::MANAGE_ATTRIBUTE,
                        UserRightConstants::USER_RIGHT_FEATURE
                    )
                ) {
                    throw UserRightAccessDeniedException::create();
                }

                break;
            case ClassContentController::class:
                if (!in_array($controller[1], ['postAction', 'putAction', 'deleteAction'])) {
                    break;
                }

                $request = $event->getRequest();
                $contextualPageUid = $request->query->get('page_uid');
                if (!$contextualPageUid || null === $contextualPage = $this->pageManager->get($contextualPageUid)) {
                    break;
                }

                $isGlobalContent = false;
                if ('putAction' === $controller[1] || 'deleteAction' === $controller[1]) {
                    $isGlobalContent = $this->isGlobalContentByUid($request->attributes->get('uid'));
                }

                if ($isGlobalContent) {
                    if (
                        !$this->securityContext->isGranted(
                            UserRightConstants::MANAGE_ATTRIBUTE,
                            UserRightConstants::GLOBAL_CONTENT_FEATURE
                        )
                    ) {
                        throw UserRightAccessDeniedException::create();
                    }

                    return;
                }

                $isPutAction = 'putAction' === $controller[1] ?
                    UserRightConstants::EDIT_CONTENT_ATTRIBUTE : UserRightConstants::DELETE_CONTENT_ATTRIBUTE;

                $subject = $contextualPage->isOnline(true)
                    ? UserRightConstants::ONLINE_PAGE
                    : UserRightConstants::OFFLINE_PAGE;
                $attribute = new UserRightPageAttribute(
                    'postAction' === $controller[1] ? UserRightConstants::CREATE_CONTENT_ATTRIBUTE : $isPutAction,
                    $this->pageTypeManager->findByPage($contextualPage)->uniqueName(),
                    $this->pageCategoryManager->getCategoryByPage($contextualPage)
                );
                if (!$this->securityContext->isGranted($attribute, $subject)) {
                    throw UserRightAccessDeniedException::create();
                }
        }
    }

    /**
     * On bundle get collection post call.
     *
     * @param \BackBee\Controller\Event\PostResponseEvent $event
     */
    public function onBundleGetCollectionPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response->isSuccessful()) {
            return;
        }

        $manageAttribute = UserRightConstants::MANAGE_ATTRIBUTE;
        if ($this->securityContext->isGranted($manageAttribute, UserRightConstants::USER_RIGHT_FEATURE)) {
            return;
        }

        $filteredData = [];
        $data = json_decode($response->getContent(), true);

        foreach ($data as $row) {
            $subject = UserRightConstants::createBundleSubject($row['id']);
            if ($this->securityContext->isGranted($manageAttribute, $subject)) {
                $filteredData[] = $row;
            }
        }

        $response->setContent(json_encode($filteredData));
    }

    /**
     * On page type get collection post call.
     *
     * @param \BackBee\Controller\Event\PostResponseEvent $event
     */
    public function onPageTypeGetCollectionPostCall(PostResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response->isSuccessful() || $event->getRequest()->query->get('context') === 'search') {
            return;
        }

        if ($this->securityContext->isGranted(UserRightConstants::CREATE_ATTRIBUTE, UserRightConstants::OFFLINE_PAGE)) {
            return;
        }

        $filteredData = [];
        $data = json_decode($response->getContent(), true);
        foreach ($data as $row) {
            $attribute = new UserRightPageAttribute(
                UserRightConstants::CREATE_ATTRIBUTE,
                $row['unique_name']
            );
            if ($this->securityContext->isGranted($attribute, UserRightConstants::OFFLINE_PAGE)) {
                $filteredData[] = $row;
            }
        }

        $response->setContent(json_encode($filteredData));
        $response->headers->set(
            'Content-Range',
            count($filteredData) ? sprintf('0-%d/%d', count($filteredData) - 1, count($filteredData)) : '-/-'
        );
    }

    /**
     * Is global content by uid.
     *
     * @param $uid
     *
     * @return bool
     */
    private function isGlobalContentByUid($uid): bool
    {
        return null !== $this->entityManager->getRepository(GlobalContent::class)->findOneBy(['content' => $uid]);
    }
}
