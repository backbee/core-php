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

namespace BackBee\KnowledgeGraph\Schema;

/**
 * Class SchemaIds
 *
 * @package BackBee\KnowledgeGraph\Schema
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
 */
class SchemaIds
{
    /**
     * Hash used for the Author `@id`.
     */
    public const AUTHOR_HASH = '#author';

    /**
     * Hash used for the Author Logo's `@id`.
     */
    public const AUTHOR_LOGO_HASH = '#authorlogo';

    /**
     * Hash used for the Breadcrumb's `@id`.
     */
    public const BREADCRUMB_HASH = '#breadcrumb';

    /**
     * Hash used for the Person `@id`.
     */
    public const PERSON_HASH = '#/schema/person/';

    /**
     * Hash used for the Article `@id`.
     */
    public const ARTICLE_HASH = '#article';

    /**
     * Hash used for the Organization `@id`.
     */
    public const ORGANIZATION_HASH = '#organization';

    /**
     * Hash used for the Organization `@id`.
     */
    public const ORGANIZATION_LOGO_HASH = '#logo';

    /**
     * Hash used for the Organization `@id`.
     */
    public const ORGANIZATION_IMAGE_HASH = '#image';

    /**
     * Hash used for the logo `@id`.
     */
    public const PERSON_LOGO_HASH = '#personlogo';

    /**
     * Hash used for an Article's primary image `@id`.
     */
    public const PRIMARY_IMAGE_HASH = '#primaryimage';

    /**
     * Hash used for the WebPage's `@id`.
     */
    public const WEBPAGE_HASH = '#webpage';

    /**
     * Hash used for the Website's `@id`.
     */
    public const WEBSITE_HASH = '#website';
}