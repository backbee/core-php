<?php

namespace BackBeeCloud\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchController extends AbstractSearchController
{
    /**
     * {@inheritdoc}
     */
    protected function getRedirectionUrlForLang($lang, Request $request)
    {
        return $this->routing->getUrlByRouteName(
            'cloud.search_i18n',
            ['lang' => $lang],
            null,
            false
        );
    }
}
