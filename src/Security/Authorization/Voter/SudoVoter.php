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
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entyMgr;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->entyMgr = $app->getEntityManager();
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $isSudoer = false;
        $sudoers = $this->getSudoers();

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
        foreach ($this->entyMgr->getRepository(User::class)->findBy($criteria) as $user) {
            $sudoers[$user->getUsername()] = $user->getId();
        }

        return $sudoers;
    }
}
