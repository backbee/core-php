<?php

namespace BackBeeCloud\Security\GroupType;

use BackBeeCloud\Security\UserRightConstants;
use Doctrine\ORM\Mapping as ORM;

/**
 * @category BackbeeCloud
 *
 * @author Quentin Guitard <quentin.guitard@lp-digital.fr>
 * @copyright Lp digital system
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
