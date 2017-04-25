<?php

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Contact;
use BackBee\Security\User;
use BackBeeCloud\Structure\ContentHandlerInterface;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContactHandler implements ContentHandlerInterface
{

    protected $entyMgr;

    public function __construct(EntityManager $entyMgr)
    {
        $this->entyMgr = $entyMgr;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!$this->supports($content)) {
            return;
        }

        $user = $this->entyMgr->getRepository(User::class)->findOneBy([]);
        $content->setParam('email', $user->getEmail());
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Contact;
    }
}
