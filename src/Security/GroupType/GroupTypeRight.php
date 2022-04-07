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

namespace BackBeeCloud\Security\GroupType;

use BackBeeCloud\Security\UserRightConstants;
use Doctrine\ORM\Mapping as ORM;

/**
 * @category BackbeeCloud
 *
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 *
 * @ORM\Entity
 * @ORM\Table(name="group_type_right")
 */
class GroupTypeRight
{
    /**
     * Unique id
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(type="bigint", options={"unsigned": true})
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * Link to GroupType
     *
     * @var GroupType
     *
     * @ORM\ManyToOne(targetEntity="GroupType", inversedBy="rights")
     * @ORM\JoinColumn(name="group_type_id", referencedColumnName="id", nullable=false)
     */
    protected $groupType;

    /**
     * Subject of the group type right
     *
     * @var string
     *
     * @ORM\Column(type="string", name="subject")
     */
    protected $subject;

    /**
     * Attribute given to group type right.
     *
     * @var string
     *
     * @ORM\Column(type="string", name="attribute")
     */
    protected $attribute;

    /**
     * @var string
     *
     * @ORM\Column(type="integer", name="context_mask")
     */
    protected $contextMask = UserRightConstants::NO_CONTEXT_MASK;

    /**
     * @var array
     *
     * @ORM\Column(type="json_array", name="context_data")
     */
    protected $contextData = [];

    public function __construct(
        GroupType $groupType,
        $subject,
        $attribute,
        $contextMask = UserRightConstants::NO_CONTEXT_MASK,
        array $contextData = []
    ) {
        $this->groupType = $groupType;
        UserRightConstants::assertSubjectExists($subject);
        $this->subject = $subject;
        UserRightConstants::assertAttributeExists($attribute);
        $this->attribute = $attribute;
        UserRightConstants::assertContextMaskIsValid($contextMask);
        $this->contextMask = $contextMask;
        $this->contextData = UserRightConstants::normalizeContextData($contextData);
    }

    /**
     * @return string $groupType
     */
    public function getGroupType()
    {
        return $this->groupType;
    }

    /**
     * @return string $subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string $attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    public function getContextMask()
    {
        return $this->contextMask;
    }

    public function hasPageTypeContext()
    {
        return 0 !== ($this->contextMask & UserRightConstants::PAGE_TYPE_CONTEXT_MASK);
    }

    public function getPageTypeContextData()
    {
        return isset($this->contextData['page_types'])
            ? $this->contextData['page_types']
            : []
        ;
    }

    public function hasCategoryContext()
    {
        return 0 !== ($this->contextMask & UserRightConstants::CATEGORY_CONTEXT_MASK);
    }

    public function getCategoryContextData()
    {
        return isset($this->contextData['categories'])
            ? $this->contextData['categories']
            : []
        ;
    }
}
