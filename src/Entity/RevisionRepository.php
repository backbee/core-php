<?php

namespace BackBeeCloud\Entity;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Repository\RevisionRepository as BBRevisionRepository;
use BackBee\Security\Token\BBUserToken;
use BackBee\Security\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class RevisionRepository extends BBRevisionRepository
{
    protected $uniqToken;

    public function __construct($em, ClassMetadata $class)
    {
        parent::__construct($em, $class);

        $uniqUser = new User('uniq_user');
        $this->uniqToken = new BBUserToken(['ROLE_API_USER']);
        $this->uniqToken
            ->setUser($uniqUser)
            ->setCreated(new \DateTime())
        ;
    }

    /**
     * Returns the unique token used to unify all users drafts.
     *
     * @return BBUserToken
     */
    public function getUniqToken()
    {
        return $this->uniqToken;
    }

    /**
     * Checkouts a new revision for $content
     *
     * @param AbstractClassContent $content
     * @param BBUserToken          $token
     *
     * @return Revision
     */
    public function checkout(AbstractClassContent $content, BBUserToken $token)
    {
        return parent::checkout($content, $this->uniqToken);
    }

    public function getDraft(AbstractClassContent $content, BBUserToken $token, $checkoutOnMissing = false)
    {
        return parent::getDraft($content, $this->uniqToken, $checkoutOnMissing);
    }

    /**
     * Returns all current drafts for authenticated user.
     *
     * @param TokenInterface $token
     *
     * @return array
     */
    public function getAllDrafts(TokenInterface $token)
    {
        return parent::getAllDrafts($this->uniqToken);
    }
}
