<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FleetRepository")
 */
class Fleet
{
    /**
     * @var UuidInterface
     *
     * @ORM\Id()
     * @ORM\Column(type="uuid", unique=true)
     * @Groups({"my-fleet"})
     */
    private $id;

    /**
     * @var Citizen
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Citizen", inversedBy="fleets")
     */
    private $owner;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @Groups({"my-fleet"})
     */
    private $version;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     * @Groups({"my-fleet"})
     */
    private $refreshDate;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable")
     * @Groups({"my-fleet"})
     */
    private $uploadDate;

    /**
     * @var iterable|Ship[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Ship", mappedBy="fleet", cascade={"all"})
     * @Groups({"my-fleet", "public-fleet"})
     */
    private $ships;

    public function __construct(?UuidInterface $id = null)
    {
        $this->id = $id;
        $this->version = 0;
        $this->ships = new ArrayCollection();
        $this->uploadDate = new \DateTimeImmutable();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getOwner(): ?Citizen
    {
        return $this->owner;
    }

    public function setOwner(?Citizen $owner): self
    {
        $this->owner = $owner;
        if ($owner !== null) {
            $owner->addFleet($this);
        }

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getUploadDate(): \DateTimeImmutable
    {
        return $this->uploadDate;
    }

    public function setUploadDate(\DateTimeImmutable $uploadDate): self
    {
        $this->uploadDate = $uploadDate;

        return $this;
    }

    public function getRefreshDate(): ?\DateTimeImmutable
    {
        return $this->refreshDate;
    }

    public function setRefreshDate(?\DateTimeImmutable $refreshDate): self
    {
        $this->refreshDate = $refreshDate;

        return $this;
    }

    /**
     * @return Ship[]|iterable
     */
    public function getShips(): iterable
    {
        return $this->ships;
    }

    public function addShip(Ship $ship): self
    {
        if (!$this->ships->contains($ship)) {
            $this->ships->add($ship);
        }
        if ($ship->getFleet() !== $this) {
            $ship->setFleet($this);
        }

        return $this;
    }

    public function isUploadedDateTooClose(): bool
    {
        return $this->uploadDate >= new \DateTimeImmutable('-15 minutes');
    }

    public function createRawData(): array
    {
        $res = [];
        foreach ($this->ships as $ship) {
            $res[] = $ship->getRawData();
        }

        return $res;
    }
}
