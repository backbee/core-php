<?php

namespace BackBeeCloud\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="import_status")
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ImportStatus implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var string
     */
    protected $label;

    /**
     * @ORM\Column(name="imported_count", type="integer", options={"unsigned": true})
     *
     * @var int
     */
    protected $importedCount = 0;

    /**
     * @ORM\Column(name="max_count", type="integer", options={"unsigned": true})
     *
     * @var int
     */
    protected $maxCount;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     *
     * @var \DateTime
     */
    protected $createdAt;

    public function __construct($label, $maxCount)
    {
        $this->label = (string) $label;
        $this->maxCount = (int) $maxCount;
        $this->createdAt = new \DateTime();
    }

    public function id()
    {
        return $this->id;
    }

    public function maxCount()
    {
        return $this->maxCount;
    }

    public function importedCount()
    {
        return $this->importedCount;
    }

    public function incrImportedCount()
    {
        $this->importedCount++;
    }

    public function statusPercent()
    {
        return $this->importedCount > 0
            ? ceil(($this->importedCount / $this->maxCount) * 100)
            : 0
        ;
    }

    public function createdAt()
    {
        return $this->createdAt;
    }

    public function jsonSerialize()
    {
        return [
            'id'             => $this->id,
            'label'          => $this->label,
            'imported_count' => $this->importedCount,
            'max_count'      => $this->maxCount,
            'progress_state' => $this->statusPercent(),
        ];
    }
}
