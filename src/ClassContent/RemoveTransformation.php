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

namespace BackBeeCloud\ClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class RemoveTransformation implements ClassContentTransformationInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $type;

    public function __construct($key, $type = null)
    {
        $this->key = $key;
        $this->type = $type;
    }

    public function apply(\ArrayObject $data)
    {
        if ($this->type) {
            if (!isset($data[$this->type])) {
                $data[$this->type] = [];
            }

            $this->assertKeyExists($data[$this->type], $this->key);
            unset($data[$this->type][$this->key]);

            return;
        }

        $this->assertKeyExists($data->getArrayCopy(), $this->key);
        unset($data[$this->key]);
    }

    protected function assertKeyExists(array $array, $key)
    {
        if (!isset($array[$key])) {
            throw new \LogicException(sprintf(
                'Attempted to remove "%s" key but it does not exist.',
                $key
            ));
        }
    }
}
