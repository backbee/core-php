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

namespace BackBeeCloud\Security\Authorization\Voter;

use BackBee\BBApplication;
use BackBee\Security\Authorization\Voter\SudoVoter;
use BackBee\Security\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use function get_class;

/**
 * Class BackBeeSudoVoter
 *
 * @package BackBeeCloud\Security\Authorization\Voter
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class BackBeeSudoVoter extends SudoVoter
{
    /**
     * @var UserRightVoter
     */
    private $masterVoter;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * BackBeeSudoVoter constructor.
     *
     * @param EntityManager  $entityManager
     * @param UserRightVoter $masterVoter
     * @param BBApplication  $bbApp
     */
    public function __construct(EntityManager $entityManager, UserRightVoter $masterVoter, BBApplication $bbApp)
    {
        $this->entityManager = $entityManager;
        $this->masterVoter = $masterVoter;

        parent::__construct($bbApp);
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes): int
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

        return $isSudoer ? VoterInterface::ACCESS_GRANTED : parent::vote($token, $object, $attributes);
    }

    /**
     * Returns list of sudoers.
     *
     * @return array
     */
    protected function getSudoers(): array
    {
        $sudoers = [];

        $criteria = [
            '_activated' => true,
            '_api_key_enabled' => true,
        ];
        foreach ($this->entityManager->getRepository(User::class)->findBy($criteria) as $user) {
            $sudoers[$user->getUsername()] = $user->getId();
        }

        return $sudoers;
    }
}
