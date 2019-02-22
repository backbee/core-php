<?php

namespace BackBeeCloud\Security;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
final class UserRightConstants
{
    const SUPER_ADMIN_ID = 'super_admin';

    // context mask constants
    const NO_CONTEXT_MASK = 0;
    const PAGE_TYPE_CONTEXT_MASK = 1;   // 2**0
    const CATEGORY_CONTEXT_MASK = 2;    // 2**1

    //
    // attribute constants
    //

    // check identity constants
    const CHECK_IDENTITY_ATTRIBUTE = 'CHECK_IDENTITY';

    // page constants
    const CREATE_ATTRIBUTE = 'CREATE';
    const EDIT_ATTRIBUTE = 'EDIT';
    const DELETE_ATTRIBUTE = 'DELETE';
    const PUBLISH_ATTRIBUTE = 'PUBLISH';
    const MANAGE_ATTRIBUTE = 'MANAGE';


    // content block attributes constants
    const CREATE_CONTENT_ATTRIBUTE = 'CREATE_CONTENT';
    const EDIT_CONTENT_ATTRIBUTE = 'EDIT_CONTENT';
    const DELETE_CONTENT_ATTRIBUTE = 'DELETE_CONTENT';

    const OFFLINE_PAGE = 'OFFLINE_PAGE';
    const ONLINE_PAGE = 'ONLINE_PAGE';

    const SEO_TRACKING_FEATURE = 'SEO_TRACKING_FEATURE';
    const TAG_FEATURE = 'TAG_FEATURE';
    const USER_RIGHT_FEATURE = 'USER_RIGHT_FEATURE';
    const MULTILANG_FEATURE = 'MULTILANG_FEATURE';
    const CUSTOM_DESIGN_FEATURE = 'CUSTOM_DESIGN_FEATURE';
    const PRIVACY_POLICY_FEATURE = 'PRIVACY_POLICY_FEATURE';
    const GLOBAL_CONTENT_FEATURE = 'GLOBAL_CONTENT_FEATURE';

    const BUNDLE_FEATURE_PATTERN = 'BUNDLE_%s_FEATURE';
    const BUNDLE_FEATURE_REGEX = '/^BUNDLE_[\w]+_FEATURE$/';

    public static function assertSubjectExists($subject)
    {
        if (!is_string($subject)) {
            throw new \InvalidArgumentException('Provided value must be type of string.');
        }

        if (1 === preg_match(self::BUNDLE_FEATURE_REGEX, $subject)) {
            return true;
        }

        $result = in_array($subject, [
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
        ]);

        if (false === $result) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Provided user right subject value (%s) does not exist.',
                    $subject
                )
            );
        }
    }

    public static function assertAttributeExists($attribute)
    {
        $result = in_array($attribute, [
            self::CHECK_IDENTITY_ATTRIBUTE,
            self::CREATE_ATTRIBUTE,
            self::EDIT_ATTRIBUTE,
            self::DELETE_ATTRIBUTE,
            self::PUBLISH_ATTRIBUTE,
            self::MANAGE_ATTRIBUTE,
            self::CREATE_CONTENT_ATTRIBUTE,
            self::EDIT_CONTENT_ATTRIBUTE,
            self::DELETE_CONTENT_ATTRIBUTE,
        ]);

        if (false === $result) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Provided user right attribute value (%s) does not exist.',
                    $attribute
                )
            );
        }
    }

    public static function assertContextMaskIsValid($contextMask)
    {
        if (self::NO_CONTEXT_MASK === $contextMask) {
            return;
        }

        $allMasks = [
            self::PAGE_TYPE_CONTEXT_MASK,
            self::CATEGORY_CONTEXT_MASK,
        ];
        if (in_array($contextMask, $allMasks)) {
            return;
        }

        foreach ($allMasks as $mask) {
            if ($contextMask & $mask) {
                $contextMask = $contextMask - $mask;
            }

            if (0 === $contextMask) {
                break;
            }
        }

        if ($contextMask) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Provided user right context mask value (%d) is not valid',
                    $contextMask
                )
            );
        }
    }

    public static function normalizeContextData(array $data)
    {
        if (false == $data) {
            return $data;
        }

        ksort($data);
        foreach ($data as &$row) {
            sort($row);
        }

        return $data;
    }

    public static function createBundleSubject($bundleId)
    {
        return strtoupper(
            sprintf(
                self::BUNDLE_FEATURE_PATTERN,
                $bundleId
            )
        );
    }
}
