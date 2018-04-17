<?php

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
