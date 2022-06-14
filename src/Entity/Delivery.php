<?php

namespace App\Entity;

use App\Repository\DeliveryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRepository::class)
 */
class Delivery
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $sum;

    /**
     * @ORM\ManyToOne(targetEntity=Connection::class, inversedBy="deliveries")
     * @ORM\JoinColumn(nullable=false)
     */
    private $connection;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $orderId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $deliveryId;

    /**
     * @ORM\Column(type="string", length="255")
     */
    private $date;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSum(): ?float
    {
        return $this->sum;
    }

    public function setSum(?float $sum): self
    {
        $this->sum = $sum;

        return $this;
    }

    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    public function setConnection(?Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getDeliveryId(): ?string
    {
        return $this->deliveryId;
    }

    public function setDeliveryId(string $deliveryId): self
    {
        $this->deliveryId = $deliveryId;

        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(string $date): self
    {
        $this->date = $date;

        return $this;
    }
}
