<?php

namespace BackBeeCloud\Security\Authorization\Voter;

use BackBee\BBApplication;
use BackBee\Security\Authorization\Voter\SudoVoter as BaseSudoVoter;
use BackBee\Security\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SudoVoter extends BaseSudoVoter
{
    private $sudoers = [];

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $criteria = [
            '_activated'       => true,
            '_api_key_enabled' => true,
        ];
        foreach ($app->getEntityManager()->getRepository(User::class)->findBy($criteria) as $user) {
            $this->sudoers[$user->getUsername()] = $user->getId();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $isSudoer = false;
        if (
            $this->supportsClass(get_class($token))
            && isset($this->sudoers[$token->getUser()->getUsername()])
            && $token->getUser()->getId() === $this->sudoers[$token->getUser()->getUsername()]
        ) {
            $isSudoer = true;
        }

        return $isSudoer
            ? VoterInterface::ACCESS_GRANTED
            : parent::vote($token, $object, $attributes)
        ;
    }
}
