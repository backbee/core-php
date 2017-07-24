<?php

namespace BackBeeCloud\PageType;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TemplateCustomType extends AbstractType
{
    /**
     * @var string
     */
    private $uniqueName;

    /**
     * @var string
     */
    private $label;

    /**
     * @var array
     */
    private $contentsRawData;

    public function __construct($uniqueName, $label, array $contentsRawData = [])
    {
        $this->uniqueName = $uniqueName;
        $this->label = $label;
        $this->contentsRawData = $contentsRawData;
    }

    /**
     * {@inheritdoc}
     */
    public function layoutName()
    {
        return (new BlankType())->layoutName();
    }

    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return $this->label;
    }

    /**
     * {@inheritdoc}
     */
    public function uniqueName()
    {
        return $this->uniqueName;
    }

    /**
     * Returns an array that contains contents raw data.
     *
     * @return array
     */
    public function contentsRawData()
    {
        return $this->contentsRawData;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            $this->uniqueName,
            $this->label,
            $this->contentsRawData,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($raw)
    {
        list(
            $this->uniqueName,
            $this->label,
            $this->contentsRawData
        ) = unserialize($raw);
    }
}
