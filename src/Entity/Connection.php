<?php

namespace App\Entity;

use App\Repository\ConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConnectionRepository::class)
 */
class Connection
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $crmUrl;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $apiKey;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $deliveryLogin;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $deliveryPassword;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $deliveryIKN;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCrmUrl(): ?string
    {
        return $this->crmUrl;
    }

    public function setCrmUrl(string $crmUrl): self
    {
        $this->crmUrl = $crmUrl;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getDeliveryLogin(): ?string
    {
        return $this->deliveryLogin;
    }

    public function setDeliveryLogin(string $deliveryLogin): self
    {
        $this->deliveryLogin = $deliveryLogin;

        return $this;
    }

    public function getDeliveryPassword(): ?string
    {
        return $this->deliveryPassword;
    }

    public function setDeliveryPassword(string $deliveryPassword): self
    {
        $this->deliveryPassword = $deliveryPassword;

        return $this;
    }

    public function getDeliveryIKN(): ?string
    {
        return $this->deliveryIKN;
    }

    public function setDeliveryIKN(string $deliveryIKN): self
    {
        $this->deliveryIKN = $deliveryIKN;

        return $this;
    }
}
