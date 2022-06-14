<?php

namespace App\Entity;

use App\Repository\ConnectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use RetailCrm\Validator\CrmUrl;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ConnectionRepository::class)
 * @UniqueEntity(fields={"clientId"}, message="There is already an account with this clientId")
 */
class Connection implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     * @CrmUrl()
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

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isActive;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $clientId;

    /**
     * @ORM\OneToMany(targetEntity=Delivery::class, mappedBy="connection", orphanRemoval=true)
     */
    private $deliveries;

    public function __construct()
    {
        $this->deliveries = new ArrayCollection();
    }

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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getRoles()
    {
        return ['ROLE_USER'];
    }

    public function getSalt()
    {
        // TODO: Implement getSalt() method.
    }

    public function getPassword()
    {
        // TODO: Implement getPassword() method.
    }

    public function getUsername()
    {
        return $this->getCrmUrl();
    }

    public function getUserIdentifier(): string
    {
        return $this->getCrmUrl();
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    /**
     * @return Collection<int, Delivery>
     */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(Delivery $delivery): self
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries[] = $delivery;
            $delivery->setConnection($this);
        }

        return $this;
    }

    public function removeDelivery(Delivery $delivery): self
    {
        if ($this->deliveries->removeElement($delivery)) {
            // set the owning side to null (unless already changed)
            if ($delivery->getConnection() === $this) {
                $delivery->setConnection(null);
            }
        }

        return $this;
    }
}
