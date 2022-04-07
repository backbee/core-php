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
    public function handleReverse(AbstractClassContent $content, array $data = [])
    {
        $result = $data['current_data'];
        unset($result['parameters']['email']);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return $content instanceof Contact;
    }
}
