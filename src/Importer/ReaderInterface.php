<?php

namespace BackBeePlanet\Importer;

use InvalidArgumentException;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
interface ReaderInterface
{
    /**
     * Returns reader name.
     *
     * @return string
     */
    public function name();

    /**
     * Verifies if the reader is well configured and ready to collect rows.
     *
     * @param mixed $source
     *
     * @throws InvalidArgumentException if the reader is not enable to fetch data
     */
    public function verify($source);

    /**
     * Returns an iterable collection.
     *
     * @param mixed $source
     *
     * @return mixed
     */
    public function collect($source);

    /**
     * Returns metadata about import (max item, max page, etc.).
     *
     * @param mixed $source
     *
     * @return array
     */
    public function sourceMetadata($source);

    /**
     * Returns true if the provided type is supported by current reader. Else false.
     *
     * @param string $type
     *
     * @return bool
     */
    public function supports($type);
}
