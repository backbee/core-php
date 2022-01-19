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

namespace BackBeeCloud\Security;

use InvalidArgumentException;
use function in_array;
use function is_string;

/**
 * Class UserRightConstants
 *
 * @package BackBeeCloud\Security
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
final class UserRightConstants
{
    public const SUPER_ADMIN_ID = 'super_admin';

    // context mask constants
    public const NO_CONTEXT_MASK = 0;
    public const PAGE_TYPE_CONTEXT_MASK = 1;   // 2**0
    public const CATEGORY_CONTEXT_MASK = 2;    // 2**1

    //
    // attribute constants
    //

    // check identity constants
    public const CHECK_IDENTITY_ATTRIBUTE = 'CHECK_IDENTITY';

    // page constants
    public const CREATE_ATTRIBUTE = 'CREATE';
    public const EDIT_ATTRIBUTE = 'EDIT';
    public const DELETE_ATTRIBUTE = 'DELETE';
    public const PUBLISH_ATTRIBUTE = 'PUBLISH';
    public const MANAGE_ATTRIBUTE = 'MANAGE';


    // content block attributes constants
    public const CREATE_CONTENT_ATTRIBUTE = 'CREATE_CONTENT';
    public const EDIT_CONTENT_ATTRIBUTE = 'EDIT_CONTENT';
    public const DELETE_CONTENT_ATTRIBUTE = 'DELETE_CONTENT';

    public const OFFLINE_PAGE = 'OFFLINE_PAGE';
    public const ONLINE_PAGE = 'ONLINE_PAGE';

    public const SEO_TRACKING_FEATURE = 'SEO_TRACKING_FEATURE';
    public const TAG_FEATURE = 'TAG_FEATURE';
    public const USER_RIGHT_FEATURE = 'USER_RIGHT_FEATURE';
    public const MULTILANG_FEATURE = 'MULTILANG_FEATURE';
    public const CUSTOM_DESIGN_FEATURE = 'CUSTOM_DESIGN_FEATURE';
    public const PRIVACY_POLICY_FEATURE = 'PRIVACY_POLICY_FEATURE';
    public const GLOBAL_CONTENT_FEATURE = 'GLOBAL_CONTENT_FEATURE';

    public const BUNDLE_FEATURE_PATTERN = 'BUNDLE_%s_FEATURE';
    public const BUNDLE_FEATURE_REGEX = '/^BUNDLE_[\w]+_FEATURE$/';

    /**
     * Assert subject exists.
     *
     * @param $subject
     *
     * @return bool|void
     */
    public static function assertSubjectExists($subject)
    {
        if (!is_string($subject)) {
            throw new InvalidArgumentException('Provided value must be type of string.');
        }

        if (1 === preg_match(self::BUNDLE_FEATURE_REGEX, $subject)) {
            return true;
        }

        $result = in_array(
            $subject,
            [
                self::SUPER_ADMIN_ID,
                self::SEO_TRACKING_FEATURE,
                self::TAG_FEATURE,
                self::USER_RIGHT_FEATURE,
                self::MULTILANG_FEATURE,
                self::CUSTOM_DESIGN_FEATURE,
                self::PRIVACY_POLICY_FEATURE,
                self::GLOBAL_CONTENT_FEATURE,
                self::OFFLINE_PAGE,
                self::ONLINE_PAGE,
            ]
        );

        if (false === $result) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided user right subject value (%s) does not exist.',
                    $subject
                )
            );
        }
    }

    /**
     * Assert attribute exists.
     *
     * @param $attribute
     *
     * @return void
     */
    public static function assertAttributeExists($attribute): void
    {
        $result = in_array(
            $attribute,
            [
                self::CHECK_IDENTITY_ATTRIBUTE,
                self::CREATE_ATTRIBUTE,
                self::EDIT_ATTRIBUTE,
                self::DELETE_ATTRIBUTE,
                self::PUBLISH_ATTRIBUTE,
                self::MANAGE_ATTRIBUTE,
                self::CREATE_CONTENT_ATTRIBUTE,
                self::EDIT_CONTENT_ATTRIBUTE,
                self::DELETE_CONTENT_ATTRIBUTE,
            ],
            true
        );

        if (false === $result) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided user right attribute value (%s) does not exist.',
                    $attribute
                )
            );
        }
    }

    /**
     * Assert context mask is valid.
     *
     * @param $contextMask
     *
     * @return void
     */
    public static function assertContextMaskIsValid($contextMask): void
    {
        if (self::NO_CONTEXT_MASK === $contextMask) {
            return;
        }

        $allMasks = [
            self::PAGE_TYPE_CONTEXT_MASK,
            self::CATEGORY_CONTEXT_MASK,
        ];
        if (in_array($contextMask, $allMasks, true)) {
            return;
        }

        foreach ($allMasks as $mask) {
            if ($contextMask & $mask) {
                $contextMask -= $mask;
            }

            if (0 === $contextMask) {
                break;
            }
        }

        if ($contextMask) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided user right context mask value (%d) is not valid',
                    $contextMask
                )
            );
        }
    }

    /**
     * Normalize context data.
     *
     * @param array $data
     *
     * @return array
     */
    public static function normalizeContextData(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        ksort($data);

        foreach ($data as &$row) {
            sort($row);
        }

        return $data;
    }

    /**
     * Create bundle subject.
     *
     * @param $bundleId
     *
     * @return string
     */
    public static function createBundleSubject($bundleId): string
    {
        return sprintf(
            self::BUNDLE_FEATURE_PATTERN,
            $bundleId
        );
    }
}
