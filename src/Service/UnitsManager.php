<?php

namespace App\Service;

use App\Entity\Units;
use Doctrine\ORM\EntityManagerInterface;

class UnitsManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get all units
     */
    public function getAllUnits(): array
    {
        return $this->entityManager
            ->getRepository(Units::class)
            ->findAll();
    }

    /**
     * Get a unit by id
     */
    public function getUnitById(int $id): ?Units
    {
        return $this->entityManager
            ->getRepository(Units::class)
            ->find($id);
    }

    /**
     * Create a new unit
     */
    public function createUnit(string $name, ?string $symbol = null): Units
    {
        $unit = new Units();
        $unit->setName(trim($name));
        if ($symbol !== null) {
            $unit->setSymbol(trim($symbol));
        }

        $this->entityManager->persist($unit);
        $this->entityManager->flush();

        return $unit;
    }

    /**
     * Update an existing unit
     */
    public function updateUnit(Units $unit, ?string $name = null, ?string $symbol = null): Units
    {
        if ($name !== null) {
            $unit->setName(trim($name));
        }
        if ($symbol !== null) {
            $unit->setSymbol($symbol ? trim($symbol) : null);
        }

        $this->entityManager->flush();

        return $unit;
    }

    /**
     * Delete a unit
     */
    public function deleteUnit(Units $unit): void
    {
        $this->entityManager->remove($unit);
        $this->entityManager->flush();
    }

    /**
     * Serialize a unit to array
     */
    public function serializeUnit(Units $unit): array
    {
        return [
            'id' => $unit->getId(),
            'name' => $unit->getName(),
            'symbol' => $unit->getSymbol(),
        ];
    }
}