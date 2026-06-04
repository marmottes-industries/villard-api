<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, InventoryItem>
     */
    #[ORM\OneToMany(targetEntity: InventoryItem::class, mappedBy: 'category')]
    private Collection $inventoryItems;

    /**
     * @var Collection<int, ShoppingItem>
     */
    #[ORM\OneToMany(targetEntity: ShoppingItem::class, mappedBy: 'category')]
    private Collection $shoppingItems;

    public function __construct()
    {
        $this->inventoryItems = new ArrayCollection();
        $this->shoppingItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function getInventoryItems(): Collection
    {
        return $this->inventoryItems;
    }

    public function addInventoryItem(InventoryItem $inventoryItem): static
    {
        if (!$this->inventoryItems->contains($inventoryItem)) {
            $this->inventoryItems->add($inventoryItem);
            $inventoryItem->setCategory($this);
        }

        return $this;
    }

    public function removeInventoryItem(InventoryItem $inventoryItem): static
    {
        if ($this->inventoryItems->removeElement($inventoryItem)) {
            // set the owning side to null (unless already changed)
            if ($inventoryItem->getCategory() === $this) {
                $inventoryItem->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ShoppingItem>
     */
    public function getShoppingItems(): Collection
    {
        return $this->shoppingItems;
    }

    public function addShoppingItem(ShoppingItem $shoppingItem): static
    {
        if (!$this->shoppingItems->contains($shoppingItem)) {
            $this->shoppingItems->add($shoppingItem);
            $shoppingItem->setCategory($this);
        }

        return $this;
    }

    public function removeShoppingItem(ShoppingItem $shoppingItem): static
    {
        if ($this->shoppingItems->removeElement($shoppingItem)) {
            // set the owning side to null (unless already changed)
            if ($shoppingItem->getCategory() === $this) {
                $shoppingItem->setCategory(null);
            }
        }

        return $this;
    }
}
