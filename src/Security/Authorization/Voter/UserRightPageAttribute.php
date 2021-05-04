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

namespace BackBeeCloud\Security\Authorization\Voter;

use BackBeeCloud\Security\UserRightConstants;

/**
 * Page contextualized (by page type or category) attribute.
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserRightPageAttribute
{
    /**
     * @var string
     */
    private $attribute;

    /**
     * @var string
     */
    private $pageType;

    /**
     * @var null|string
     */
    private $category;

    public function __construct($attribute, $pageType, $category = null)
    {
        $isValidPageAttribute = in_array($attribute, [
            UserRightConstants::CREATE_ATTRIBUTE,
            UserRightConstants::EDIT_ATTRIBUTE,
            UserRightConstants::DELETE_ATTRIBUTE,
            UserRightConstants::PUBLISH_ATTRIBUTE,
            UserRightConstants::CREATE_CONTENT_ATTRIBUTE,
            UserRightConstants::EDIT_CONTENT_ATTRIBUTE,
            UserRightConstants::DELETE_CONTENT_ATTRIBUTE,
        ]);
        if (!$isValidPageAttribute) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Provided attribute "%s" is not a valid page attribute',
                    $attribute
                )
            );
        }

        $this->attribute = $attribute;

        if (!is_string($pageType) || false == $pageType) {
            throw new \InvalidArgumentException(
                'Page type must be type of string and not empty'
            );
        }

        $this->pageType = $pageType;
        $this->category = $category;
    }

    public function getAttribute()
    {
        return $this->attribute;
    }

    public function getPageType()
    {
        return $this->pageType;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function __toString()
    {
        return $this->attribute;
    }
}
