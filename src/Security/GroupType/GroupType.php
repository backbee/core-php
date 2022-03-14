<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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
use BackBee\Security\Group;
use BackBee\Security\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @category BackbeeCloud
 *
 * @copyright Lp digital system
 * @author Quentin Guitard <quentin.guitard@lp-digital.com>
 *
 * @ORM\Entity
 * @ORM\Table(name="group_type")
 */
class GroupType implements JsonSerializable
{
    /**
     * Unique identifier of the group.
     *
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", name="id")
     */
    protected $id;

    /**
     * Group is open
     *
     * @var bool
     * @ORM\Column(type="boolean", name="is_open")
     */
    protected $isOpen;

    /**
     * Group option read only
     *
     * @var bool
     * @ORM\Column(type="boolean", name="read_only")
     */
    protected $readOnly;

    /**
     * @var Group
     *
     * @ORM\OneToOne(targetEntity="BackBee\Security\Group", cascade={"remove"})
     * @ORM\JoinColumn(name="group_id", referencedColumnName="id", nullable=false)
     */
    protected $group;

    /**
     * @var GroupTypeRight
     *
     * @ORM\OneToMany(targetEntity="GroupTypeRight", mappedBy="groupType", cascade={"remove"})
     */
    protected $rights;

    /**
     * Constructor.
     *
     * @param                         $id
     * @param                         $isOpen
     * @param                         $readOnly
     * @param \BackBee\Security\Group $group
     */
    public function __construct($id, $isOpen, $readOnly, Group $group)
    {
        $this->id = $id;
        $this->isOpen = $isOpen;
        $this->readOnly = $readOnly;
        $this->group = $group;

        $this->rights = new ArrayCollection();
    }

    /**
     * Get unique identifier of the group.
     *
     * @return  string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get group is open
     *
     * @return  bool
     */
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    /**
     * Get group option read only
     *
     * @return  bool
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Get group name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->group->getName();
    }

    /**
     * Set group name.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->assertIsNotReadOnly();

        $this->group->setName($name);
    }

    /**
     * Get group description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->group->getDescription();
    }

    /**
     * Set group description.
     *
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->assertIsNotReadOnly();

        $this->group->setDescription($description);
    }

    /**
     * Add user for the current group type.
     *
     * @param \BackBee\Security\User $user
     *
     * @return void
     */
    public function addUser(User $user): void
    {
        $this->assertIsOpen();
        if ($this->group->getUsers()->contains($user)) {
            return;
        }

        $this->group->addUser($user);
    }

    /**
     * Remove user for the current group type.
     *
     * @param \BackBee\Security\User $user
     *
     * @return void
     */
    public function removeUser(User $user): void
    {
        $this->assertIsOpen();
        if (!$this->group->getUsers()->contains($user)) {
            return;
        }

        $this->group->removeUser($user);
    }

    /**
     * Get all user for the current group type.
     *
     * @return array
     */
    public function getUsers(): array
    {
        return $this->group->getUsers()->toArray();
    }

    /**
     * Check if is removable.
     *
     * @return bool
     */
    public function isRemovable(): bool
    {
        return false === $this->readOnly && true === empty($this->getUsers());
    }

    /**
     * Return result serialized.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $pageTypes = [];
        $categories = [];
        $pageRights = [];
        $featureRights = [];
        foreach ($this->rights as $right) {
            switch ($subject = $right->getSubject()) {
                case UserRightConstants::OFFLINE_PAGE:
                case UserRightConstants::ONLINE_PAGE:
                    $pageRights[] = sprintf('%s_%s', $subject, $right->getAttribute());

                    if (false === $pageTypes) {
                        if ($right->hasPageTypeContext()) {
                            $pageTypes = $right->getPageTypeContextData();
                        } else {
                            $pageTypes = ['all'];
                        }
                    }

                    if (false === $categories) {
                        if ($right->hasCategoryContext()) {
                            $categories = $right->getCategoryContextData();
                        } else {
                            $categories = ['all'];
                        }
                    }

                    break;
                default:
                    $featureRights[] = $subject;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->group->getName(),
            'description' => $this->group->getDescription(),
            'is_open' => $this->isOpen,
            'can_add_users' => $this->isOpen,
            'read_only' => $this->readOnly,
            'is_removable' => $this->isRemovable(),
            'features_rights' => $featureRights,
            'pages_rights' => [
                'page_types' => $pageTypes,
                'categories' => $categories,
                'rights' => $pageRights,
            ]
        ];
    }

    /**
     * Assert is open.
     *
     * @return void
     */
    private function assertIsOpen(): void
    {
        if (!$this->isOpen) {
            throw CannotAddOrRemoveUserToClosedGroupTypeException::create();
        }
    }

    /**
     * Assert is not read only.
     *
     * @return void
     */
    private function assertIsNotReadOnly(): void
    {
        if ($this->readOnly) {
            throw CannotUpdateReadOnlyGroupTypeException::create();
        }
    }
}
