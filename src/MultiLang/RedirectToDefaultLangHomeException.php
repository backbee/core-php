<?php

namespace BackBeeCloud\MultiLang;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class RedirectToDefaultLangHomeException extends \Exception
{
    /**
     * @var string
     */
    protected $redirectTo;

    public function __construct($redirectTo = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct('', $code, $previous);

        $this->redirectTo = $redirectTo;
    }

    public function getRedirectTo()
    {
        return $this->redirectTo;
    }
}
