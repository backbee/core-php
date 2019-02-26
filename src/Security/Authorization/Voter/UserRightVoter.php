<?php

namespace BackBeeCloud\Security\Authorization\Voter;

use BackBeeCloud\Security\Authorization\Voter\UserRightPageAttribute;
use BackBeeCloud\Security\GroupType\GroupTypeManager;
use BackBeeCloud\Security\GroupType\GroupTypeRightManager;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\Security\Token\BBUserToken;
use BackBee\Security\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightVoter implements VoterInterface
{
    /**
     * @var GroupTypeManager
     */
    private $groupTypeManager;

    /**
     * @var GroupTypeRightManager
     */
    private $groupTypeRightManager;

    public function __construct(GroupTypeManager $groupTypeManager, GroupTypeRightManager $groupTypeRightManager)
    {
        $this->groupTypeManager = $groupTypeManager;
        $this->groupTypeRightManager = $groupTypeRightManager;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        if ($attribute instanceof UserRightPageAttribute) {
            return true;
        }

        try {
            UserRightConstants::assertAttributeExists($attribute);
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        try {
            UserRightConstants::assertSubjectExists($class);
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $attribute = array_pop($attributes);
        if (
            !$this->supportsClass($subject)
            || !$this->supportsAttribute($attribute)
        ) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        if (!($token instanceof BBUserToken)) {
            return VoterInterface::ACCESS_DENIED;
        }

        $userGroupTypes = $this->getGroupTypesFromUser($token->getUser());
        if ($this->isInSuperAdminGroupType($userGroupTypes)) {
            return VoterInterface::ACCESS_GRANTED;
        }

        $isGranted = false != $this->groupTypeRightManager->findBy([
            'groupType' => $userGroupTypes,
            'subject' => $subject,
            'attribute' => $attribute,
            'contextMask' => UserRightConstants::NO_CONTEXT_MASK,
        ]);

        if ($isGranted) {
            return VoterInterface::ACCESS_GRANTED;
        }

        if (!($attribute instanceof UserRightPageAttribute)) {
            return VoterInterface::ACCESS_DENIED;
        }

        $rights = $this->groupTypeRightManager->findBy(
            [
                'groupType' => $userGroupTypes,
                'subject' => $subject,
                'attribute' => $attribute,
                'contextMask' => [
                    UserRightConstants::PAGE_TYPE_CONTEXT_MASK,
                    UserRightConstants::CATEGORY_CONTEXT_MASK,
                    UserRightConstants::PAGE_TYPE_CONTEXT_MASK | UserRightConstants::CATEGORY_CONTEXT_MASK,
                ],
            ],
            [
                'contextMask' => 'asc',
            ]
        );

        foreach ($rights as $right) {
            switch ($right->getContextMask()) {
                case UserRightConstants::PAGE_TYPE_CONTEXT_MASK:
                    $isGranted = in_array($attribute->getPageType(), $right->getPageTypeContextData());

                    break;
                case UserRightConstants::CATEGORY_CONTEXT_MASK:
                    if (null === $attribute->getCategory()) {
                        $isGranted = true;

                        break;
                    }

                    $isGranted = in_array($attribute->getCategory(), $right->getCategoryContextData());

                    break;
                case (UserRightConstants::PAGE_TYPE_CONTEXT_MASK | UserRightConstants::CATEGORY_CONTEXT_MASK):
                    if (null === $attribute->getCategory()) {
                        $isGranted = in_array($attribute->getPageType(), $right->getPageTypeContextData());

                        break;
                    }

                    $isGranted =
                        in_array($attribute->getPageType(), $right->getPageTypeContextData())
                        && in_array($attribute->getCategory(), $right->getCategoryContextData())
                    ;
            }

            if ($isGranted) {
                break;
            }
        }

        return $isGranted
            ? VoterInterface::ACCESS_GRANTED
            : VoterInterface::ACCESS_DENIED
        ;
    }

    private function getGroupTypesFromUser(User $user)
    {
        $groupTypes = [];
        foreach ($user->getGroups() as $group) {
            $groupTypes[] = $this->groupTypeManager->getByGroup($group);
        }

        return array_filter($groupTypes);
    }

    private function isInSuperAdminGroupType(array $groupTypes)
    {
        foreach ($groupTypes as $groupType) {
            if ('super_admin' === $groupType->getId()) {
                return true;
            }
        }

        return false;
    }
}
