<?php

namespace BackBeeCloud\Security\Authorization\Voter;

use BackBee\Security\Authorization\Voter\SudoVoter as BaseSudoVoter;
use BackBee\Security\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SudoVoter extends BaseSudoVoter
{
    /**
     * @var UserRightVoter
     */
    private $masterVoter;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager, UserRightVoter $masterVoter)
    {
        $this->entityManager = $entityManager;
        $this->masterVoter = $masterVoter;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $masterVote = $this->masterVoter->vote($token, $object, $attributes);
        if (VoterInterface::ACCESS_ABSTAIN !== $masterVote) {
            return $masterVote;
        }

        $sudoers = $this->getSudoers();
        $isSudoer = false;

        if (
            $this->supportsClass(get_class($token))
            && isset($sudoers[$token->getUser()->getUsername()])
            && $token->getUser()->getId() === $sudoers[$token->getUser()->getUsername()]
        ) {
            $isSudoer = true;
        }

        return $isSudoer
            ? VoterInterface::ACCESS_GRANTED
            : parent::vote($token, $object, $attributes)
        ;
    }

    /**
     * Returns list of sudoers.
     *
     * @return array
     */
    protected function getSudoers()
    {
        $sudoers = [];

        $criteria = [
            '_activated'       => true,
            '_api_key_enabled' => true,
        ];
        foreach ($this->entityManager->getRepository(User::class)->findBy($criteria) as $user) {
            $sudoers[$user->getUsername()] = $user->getId();
        }

        return $sudoers;
    }
}
