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

namespace BackBeeCloud\PageType;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface TypeInterface extends \JsonSerializable, \Serializable
{
    /**
     * Returns page type label.
     *
     * @return string
     */
    public function label();

    /**
     * Returns page type unique name.
     *
     * @return string
     */
    public function uniqueName();

    /**
     * Returns page type layout file name.
     *
     * @return string
     */
    public function layoutName();

    /**
     * Returns true if current page type is reserved to developers.
     *
     * @return bool
     */
    public function isProtected();

    /**
     * Returns true if current page can be duplicate.
     *
     * @return bool
     */
    public function isDuplicable();

    /**
     * Returns true if current page can be delete.
     *
     * @return bool
     */
    public function isRemovable();

    /**
     * Returns true if it's the default type to use.
     *
     * @return bool
     */
    public function isDefault();

    /**
     * Returns true if the page can be retrieve by content autoblock.
     *
     * @return bool
     */
    public function isPullable();

    /**
     * Returns true if the page can be dumped, else false.
     *
     * @return bool
     */
    public function isDumpable();

    /**
     * Returns an array that contains the namespace of every content
     * to include by default.
     *
     * Expected syntax for the array:
     *
     * [
     *     'BackBee\ClassContent\Element\Text'  => function($content) {
     *         // put your code here...
     *     },
     *     'BackBee\ClassContent\Element\Image' => null,
     * ]
     *
     * Note that the classname must be the key and the value must be a callback
     * or null. The callback allow you to do custom configuration after the
     * content is instantiated.
     *
     * Keep in mind that the order is important (the first declared content will
     * be on the top of the page).
     *
     * @return array
     */
    public function defaultContents();

    /**
     * Returns namespace of exclusives classcontents to current page type.
     *
     * @return array
     */
    public function exclusiveClassContents();
}
