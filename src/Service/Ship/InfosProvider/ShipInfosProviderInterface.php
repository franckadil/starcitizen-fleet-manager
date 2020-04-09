<?php

namespace App\Service\Ship\InfosProvider;

use App\Domain\ShipInfo;

interface ShipInfosProviderInterface
{
    /**
     * @return iterable|ShipInfo[]
     */
    public function getAllShips(): iterable;

    public function getShipById(string $id): ?ShipInfo;

    public function getShipByName(string $name): ?ShipInfo;

    /**
     * @return iterable|ShipInfo[]
     */
    public function getShipsByChassisId(string $chassisId): iterable;

    public function shipNamesAreEquals(string $hangarName, string $providerName): bool;

    public function transformProviderToHangar(string $providerName): string;

    public function transformHangarToProvider(string $hangarName): string;

    /**
     * @return iterable|ShipInfo[]
     */
    public function getShipsByIdOrName(array $ids, array $names = []): iterable;
}
