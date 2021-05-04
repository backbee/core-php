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

namespace BackBeeCloud\Structure\ContentHandler;

use BackBee\ClassContent\AbstractClassContent;
use BackBeeCloud\Structure\ContentHandlerInterface;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ParameterHandler implements ContentHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(AbstractClassContent $content, array $data)
    {
        if (!isset($data['parameters']) || !is_array($data['parameters'])) {
            return;
        }

        foreach ($data['parameters'] as $attr => $value) {
            $content->setParam($attr, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleReverse(AbstractClassContent $content, array $data = [])
    {
        $params = $content->getAllParams();

        foreach ($content->getDefaultParams() as $attr => $default) {
            if ($params[$attr]['value'] === $default['value']) {
                unset($params[$attr]);
            }
        }

        return $params
            ? [
                'parameters' => array_map(function ($param) {
                    $result = $param['value'];
                    if (isset($param['type']) && 'selectTag' === $param['type']) {
                        $result = array_map(function ($value) {
                            return isset($value['label']) ? $value['label'] : $value;
                        }, $result);
                    }

                    return $result;
                }, $params),
            ]
            : [];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(AbstractClassContent $content)
    {
        return true;
    }
}
