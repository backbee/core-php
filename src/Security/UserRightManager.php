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

namespace BackBeeCloud\Security;

use BackBee\NestedNode\Page;
use BackBee\Security\SecurityContext;
use BackBee\Security\User;
use BackBeeCloud\Entity\PageType;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\Security\Authorization\Voter\UserRightPageAttribute;
use BackBeeCloud\Security\GroupType\GroupTypeRight;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class UserRightManager
 *
 * @package BackBeeCloud\Security
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightManager
{
    public const CREATE_PAGE_RIGHT = 'CREATE_PAGE';
    public const DUPLICATE_PAGE_RIGHT = 'DUPLICATE_PAGE';
    public const EDIT_PAGE_RIGHT = 'EDIT_PAGE';
    public const PUBLISH_PAGE_RIGHT = 'PUBLISH_PAGE';
    public const DELETE_PAGE_RIGHT = 'DELETE_PAGE';
    public const CREATE_CONTENT_RIGHT = 'CREATE_CONTENT';
    public const EDIT_CONTENT_RIGHT = 'EDIT_CONTENT';
    public const DELETE_CONTENT_RIGHT = 'DELETE_CONTENT';

    /**
     * @var SecurityContext
     */
    private $securityContext;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var PageCategoryManager
     */
    private $pageCategoryManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $superAdminBundleRights;

    /**
     * UserRightManager constructor.
     *
     * @param SecurityContext        $securityContext
     * @param EntityManagerInterface $entityManager
     * @param PageCategoryManager    $pageCategoryManager
     * @param LoggerInterface        $logger
     * @param array                  $superAdminBundleRights
     */
    public function __construct(
        SecurityContext $securityContext,
        EntityManagerInterface $entityManager,
        PageCategoryManager $pageCategoryManager,
        LoggerInterface $logger,
        array $superAdminBundleRights = []
    ) {
        $this->securityContext = $securityContext;
        $this->entityManager = $entityManager;
        $this->pageCategoryManager = $pageCategoryManager;
        $this->superAdminBundleRights = $superAdminBundleRights;
        $this->logger = $logger;
    }

    /**
     * Get user rights.
     *
     * @param User      $user
     * @param Page|null $contextualPage
     *
     * @return array
     */
    public function getUserRights(User $user, Page $contextualPage = null): array
    {
        if ($this->securityContext->isGranted(
            UserRightConstants::CHECK_IDENTITY_ATTRIBUTE,
            UserRightConstants::SUPER_ADMIN_ID
        )) {
            return $this->getSuperAdminRights();
        }

        return array_merge(
            $this->getUserFeatureRights($user),
            $this->getUserPageRights($user, $contextualPage)
        );
    }

    /**
     * Get user authorized categories.
     *
     * @param User $user
     * @param null $contextualPageType
     *
     * @return array
     */
    public function getUserAuthorizedCategories(User $user, $contextualPageType = null): array
    {
        if (empty($this->pageCategoryManager->getCategories())) {
            return [];
        }

        $categories = $this->internalGetUserAuthorizedCategories($user, $contextualPageType);

        if ($this->securityContext->isGranted(
            UserRightConstants::CHECK_IDENTITY_ATTRIBUTE,
            UserRightConstants::SUPER_ADMIN_ID
        )) {
            $categories = $this->getSuperAdminCategories();
        }

        return $categories;
    }

    /**
     * Get super admin rights.
     *
     * @return array
     */
    protected function getSuperAdminRights(): array
    {
        return array_filter(
            array_merge(
                [
                    UserRightConstants::SEO_TRACKING_FEATURE,
                    UserRightConstants::TAG_FEATURE,
                    UserRightConstants::USER_RIGHT_FEATURE,
                    UserRightConstants::MULTILANG_FEATURE,
                    UserRightConstants::CUSTOM_DESIGN_FEATURE,
                    UserRightConstants::PRIVACY_POLICY_FEATURE,
                    UserRightConstants::GLOBAL_CONTENT_FEATURE,
                    self::CREATE_PAGE_RIGHT,
                    self::DUPLICATE_PAGE_RIGHT,
                    self::EDIT_PAGE_RIGHT,
                    self::PUBLISH_PAGE_RIGHT,
                    self::DELETE_PAGE_RIGHT,
                    self::CREATE_CONTENT_RIGHT,
                    self::EDIT_CONTENT_RIGHT,
                    self::DELETE_CONTENT_RIGHT,
                ],
                $this->superAdminBundleRights
            )
        );
    }

    /**
     * Get super admin categories.
     *
     * @return array
     */
    protected function getSuperAdminCategories(): array
    {
        return $this->pageCategoryManager->getCategories();
    }

    /**
     * Get user feature rights.
     *
     * @param User $user
     *
     * @return array
     */
    protected function getUserFeatureRights(User $user): array
    {
        $qb = $this->createGroupTypeRightQueryBuilder('gtr');

        $result = $qb
            ->select('DISTINCT(gtr.subject) as subject')
            ->leftJoin('gtr.groupType', 'gt')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->in('gt.group', ':user_groups'),
                    $qb->expr()->eq('gtr.contextMask', ':no_context_mask'),
                    $qb->expr()->notIn('gtr.subject', ':page_subjects')
                )
            )
            ->setParameter('user_groups', $user->getGroups()->toArray())
            ->setParameter('no_context_mask', UserRightConstants::NO_CONTEXT_MASK)
            ->setParameter('page_subjects', [UserRightConstants::OFFLINE_PAGE, UserRightConstants::ONLINE_PAGE])
            ->getQuery()
            ->getResult();

        return array_column($result, 'subject');
    }

    /**
     * Get user page right.
     *
     * @param User      $user
     * @param Page|null $contextualPage
     *
     * @return array
     */
    public function getUserPageRights(User $user, Page $contextualPage = null): array
    {
        $pageRights = [];

        $qb = $this->createGroupTypeRightQueryBuilder('gtr');

        $result = $qb
            ->leftJoin('gtr.groupType', 'gt')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->in('gt.group', ':user_groups'),
                    $qb->expr()->in('gtr.subject', ':offline_page_subject'),
                    $qb->expr()->in('gtr.attribute', ':create_attribute')
                )
            )
            ->setParameter('user_groups', $user->getGroups()->toArray())
            ->setParameter('offline_page_subject', UserRightConstants::OFFLINE_PAGE)
            ->setParameter('create_attribute', UserRightConstants::CREATE_ATTRIBUTE)
            ->getQuery()
            ->getResult();

        foreach ($result as $row) {
            if (false === $row->hasPageTypeContext() || false !== $row->getPageTypeContextData()) {
                $pageRights[] = self::CREATE_PAGE_RIGHT;

                break;
            }
        }

        if (null === $contextualPage) {
            return $pageRights;
        }

        $pageType = null;

        try {
            $pageType = $this->getPageTypeUniqueNameByPage($contextualPage);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }

        $category = $this->pageCategoryManager->getCategoryByPage($contextualPage);
        $subject = $contextualPage->isOnline(true)
            ? UserRightConstants::ONLINE_PAGE
            : UserRightConstants::OFFLINE_PAGE;

        if ($this->isPageAttributeGranted(
            UserRightConstants::OFFLINE_PAGE,
            UserRightConstants::CREATE_ATTRIBUTE,
            $pageType
        )) {
            $pageRights[] = self::DUPLICATE_PAGE_RIGHT;
        }

        if ($this->isPageAttributeGranted($subject, UserRightConstants::EDIT_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::EDIT_PAGE_RIGHT;
        }

        if ($this->isPageAttributeGranted($subject, UserRightConstants::DELETE_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::DELETE_PAGE_RIGHT;
        }

        if ($this->isPageAttributeGranted($subject, UserRightConstants::PUBLISH_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::PUBLISH_PAGE_RIGHT;
        }

        if ($this->isPageAttributeGranted(
            $subject,
            UserRightConstants::CREATE_CONTENT_ATTRIBUTE,
            $pageType,
            $category
        )) {
            $pageRights[] = self::CREATE_CONTENT_RIGHT;
        }

        if ($this->isPageAttributeGranted($subject, UserRightConstants::EDIT_CONTENT_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::EDIT_CONTENT_RIGHT;
        }

        if ($this->isPageAttributeGranted(
            $subject,
            UserRightConstants::DELETE_CONTENT_ATTRIBUTE,
            $pageType,
            $category
        )) {
            $pageRights[] = self::DELETE_CONTENT_RIGHT;
        }

        return $pageRights;
    }

    /**
     * @param $alias
     *
     * @return QueryBuilder
     */
    private function createGroupTypeRightQueryBuilder($alias): QueryBuilder
    {
        return $this->entityManager->getRepository(GroupTypeRight::class)->createQueryBuilder($alias);
    }

    /**
     * @param Page $page
     *
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getPageTypeUniqueNameByPage(Page $page)
    {
        $qb = $this->entityManager->getRepository(PageType::class)->createQueryBuilder('pt');

        return $qb
            ->select('pt.typeName')
            ->where(
                $qb->expr()->eq('pt.page', ':page')
            )
            ->setParameter('page', $page)
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Is page attribute granted.
     *
     * @param      $subject
     * @param      $attribute
     * @param      $pageType
     * @param null $category
     *
     * @return bool
     */
    private function isPageAttributeGranted($subject, $attribute, $pageType, $category = null): bool
    {
        return $this->securityContext->isGranted(
            new UserRightPageAttribute($attribute, $pageType, $category),
            $subject
        );
    }

    /**
     * @param User $user
     * @param null $contextualPageType
     *
     * @return array
     */
    private function internalGetUserAuthorizedCategories(User $user, $contextualPageType = null): array
    {
        $qb = $this->createGroupTypeRightQueryBuilder('gtr');

        $result = $qb
            ->leftJoin('gtr.groupType', 'gt')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->in('gt.group', ':user_groups'),
                    $qb->expr()->in('gtr.contextMask', ':context_masks')
                )
            )
            ->setParameter('user_groups', $user->getGroups()->toArray())
            ->setParameter(
                'context_masks',
                [
                    UserRightConstants::CATEGORY_CONTEXT_MASK,
                    UserRightConstants::PAGE_TYPE_CONTEXT_MASK | UserRightConstants::CATEGORY_CONTEXT_MASK,
                ]
            )
            ->getQuery()
            ->getResult();

        if (empty($result)) {
            // if no results = current user is not restricted to any category;
            // same authorized categories as super admin
            return $this->getSuperAdminCategories();
        }

        $categories = [];
        foreach ($result as $row) {
            if ($row->hasPageTypeContext() && !in_array($contextualPageType, $row->getPageTypeContextData(), true)) {
                continue;
            }
            $categories = array_merge(
                $categories,
                $row->getCategoryContextData()
            );
        }

        return array_values(
            array_unique(
                $categories
            )
        );
    }
}
