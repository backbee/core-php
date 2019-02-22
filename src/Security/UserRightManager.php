<?php

namespace BackBeeCloud\Security;

use BackBeeCloud\Entity\PageType;
use BackBeeCloud\PageCategory\PageCategory;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\Security\Authorization\Voter\UserRightPageAttribute;
use BackBeeCloud\Security\GroupType\GroupTypeRight;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\NestedNode\Page;
use BackBee\Security\SecurityContext;
use BackBee\Security\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightManager
{
    const CREATE_PAGE_RIGHT = 'CREATE_PAGE';
    const DUPLICATE_PAGE_RIGHT = 'DUPLICATE_PAGE';
    const EDIT_PAGE_RIGHT = 'EDIT_PAGE';
    const PUBLISH_PAGE_RIGHT = 'PUBLISH_PAGE';
    const DELETE_PAGE_RIGHT = 'DELETE_PAGE';
    const CREATE_CONTENT_RIGHT = 'CREATE_CONTENT';
    const EDIT_CONTENT_RIGHT = 'EDIT_CONTENT';
    const DELETE_CONTENT_RIGHT = 'DELETE_CONTENT';

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

    public function __construct(
        SecurityContext $securityContext,
        EntityManagerInterface $entityManager,
        PageCategoryManager $pageCategoryManager
    ) {
        $this->securityContext = $securityContext;
        $this->entityManager = $entityManager;
        $this->pageCategoryManager = $pageCategoryManager;
    }

    public function getUserRights(User $user, Page $contextualPage = null)
    {
        if (
            $this->securityContext->isGranted(
                UserRightConstants::CHECK_IDENTITY_ATTRIBUTE,
                UserRightConstants::SUPER_ADMIN_ID
            )
        ) {
            return $this->getSuperAdminRights();
        }

        return array_merge(
            $this->getUserFeatureRights($user),
            $this->getUserPageRights($user, $contextualPage)
        );
    }

    public function getUserAuthorizedCategories(User $user, $contextualPageType = null)
    {
        if (false == $this->pageCategoryManager->getCategories()) {
            return;
        }

        if (
            $this->securityContext->isGranted(
                UserRightConstants::CHECK_IDENTITY_ATTRIBUTE,
                UserRightConstants::SUPER_ADMIN_ID
            )
        ) {
            return $this->getSuperAdminCategories();
        }

        return $this->internalGetUserAuthorizedCategories($user, $contextualPageType);
    }

    protected function getSuperAdminRights()
    {
        return [
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
        ];
    }

    protected function getSuperAdminCategories()
    {
        return $this->pageCategoryManager->getCategories();
    }

    protected function getUserFeatureRights(User $user)
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
            ->getResult()
        ;

        return array_column($result, 'subject');
    }

    public function getUserPageRights(User $user, Page $contextualPage = null)
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
            ->getResult()
        ;

        foreach ($result as $row) {
            if (false === $row->hasPageTypeContext() || false != $row->getPageTypeContextData()) {
                $pageRights[] = self::CREATE_PAGE_RIGHT;

                break;
            }
        }

        if (null === $contextualPage) {
            return $pageRights;
        }

        $pageType = $this->getPageTypeUniqueNameByPage($contextualPage);
        $category = $this->pageCategoryManager->getCategoryByPage($contextualPage);
        $subject = $contextualPage->isOnline(true)
            ? UserRightConstants::ONLINE_PAGE
            : UserRightConstants::OFFLINE_PAGE
        ;

        if ($this->isPageAttributeGranted(UserRightConstants::OFFLINE_PAGE, UserRightConstants::CREATE_ATTRIBUTE, $pageType)) {
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

        if ($this->isPageAttributeGranted($subject, UserRightConstants::CREATE_CONTENT_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::CREATE_CONTENT_RIGHT;
        }

        if ($this->isPageAttributeGranted($subject, UserRightConstants::EDIT_CONTENT_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::EDIT_CONTENT_RIGHT;
        }

        if ($this->isPageAttributeGranted($subject, UserRightConstants::DELETE_CONTENT_ATTRIBUTE, $pageType, $category)) {
            $pageRights[] = self::DELETE_CONTENT_RIGHT;
        }

        return $pageRights;
    }

    private function createGroupTypeRightQueryBuilder($alias)
    {
        return $this->entityManager->getRepository(GroupTypeRight::class)->createQueryBuilder($alias);
    }

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
            ->getSingleScalarResult()
        ;
    }

    private function isPageAttributeGranted($subject, $attribute, $pageType, $category = null)
    {
        return $this->securityContext->isGranted(
            new UserRightPageAttribute(
                $attribute,
                $pageType,
                $category
            ),
            $subject
        );
    }

    private function internalGetUserAuthorizedCategories(User $user, $contextualPageType = null)
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
            ->setParameter('context_masks', [
                UserRightConstants::CATEGORY_CONTEXT_MASK,
                UserRightConstants::PAGE_TYPE_CONTEXT_MASK | UserRightConstants::CATEGORY_CONTEXT_MASK,
            ])
            ->getQuery()
            ->getResult()
        ;

        if (false == $result) {
            // if no results = current user is not restricted to any category;
            // same authorized categories as super admin
            return $this->getSuperAdminCategories();
        }

        $categories = [];
        foreach ($result as $row) {
            if (
                $row->hasPageTypeContext()
                && !in_array($contextualPageType, $row->getPageTypeContextData())
            ) {
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
