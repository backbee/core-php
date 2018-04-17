<?php

namespace BackBeeCloud\ClassContent;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ReplaceTransformation implements ClassContentTransformationInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var string
     */
    private $type;

    public function __construct($key, $value, $type = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->type = $type;
    }

    public function apply(\ArrayObject $data)
    {
        if ($this->type) {
            if (!isset($data[$this->type])) {
                $data[$this->type] = [];
            }

            $this->assertKeyExists($data[$this->type], $this->key);
            $data[$this->type][$this->key] = $this->value;

            return;
        }

        $this->assertKeyExists($data->getArrayCopy(), $this->key);
        $data[$this->key] = $this->value;
    }

    protected function assertKeyExists(array $array, $key)
    {
        if (!isset($array[$key])) {
            throw new \LogicException(sprintf(
                'Attempted to replace "%s" key but it does not exist.',
                $key
            ));
        }
    }
}
