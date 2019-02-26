<?php

namespace BackBeeCloud\Security\GroupType;

use BackBeeCloud\Security\UserRightConstants;
use BackBee\Security\Group;
use BackBee\Security\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @category BackbeeCloud
 *
 * @copyright Lp digital system
 * @author Quentin Guitard <quentin.guitard@lp-digital.com>
 *
 * @ORM\Entity
 * @ORM\Table(name="group_type")
 */
class GroupType implements \JsonSerializable
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
     * @return  integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get group is open
     *
     * @return  bool
     */
    public function isOpen()
    {
        return $this->isOpen;
    }

    /**
     * Get group option read only
     *
     * @return  bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * Get group name
     * @return string
     */
    public function getName()
    {
        return $this->group->getName();
    }

    /**
     * Set group name
     * @param string $name
     */
    public function setName($name)
    {
        $this->assertIsNotReadOnly();

        $this->group->setName($name);
    }

    /**
     * Get group description
     * @return string
     */
    public function getDescription()
    {
        return $this->group->getDescription();
    }

    /**
     * Set group description
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->assertIsNotReadOnly();

        $this->group->setDescription($description);
    }

    public function addUser(User $user)
    {
        $this->assertIsOpen();
        if ($this->group->getUsers()->contains($user)) {
            return;
        }

        $this->group->addUser($user);
    }

    public function removeUser(User $user)
    {
        $this->assertIsOpen();
        if (!$this->group->getUsers()->contains($user)) {
            return;
        }

        $this->group->removeUser($user);
    }

    public function getUsers()
    {
        return $this->group->getUsers()->toArray();
    }

    public function isRemovable()
    {
        return false === $this->readOnly && false == $this->getUsers();
    }

    public function jsonSerialize()
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

                    if (false == $pageTypes) {
                        if ($right->hasPageTypeContext()) {
                            $pageTypes = $right->getPageTypeContextData();
                        } else {
                            $pageTypes = ['all'];
                        }
                    }

                    if (false == $categories) {
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

    private function assertIsOpen()
    {
        if (!$this->isOpen) {
            throw CannotAddOrRemoveUserToClosedGroupTypeException::create();
        }
    }

    private function assertIsNotReadOnly()
    {
        if ($this->readOnly) {
            throw CannotUpdateReadOnlyGroupTypeException::create();
        }
    }
}
