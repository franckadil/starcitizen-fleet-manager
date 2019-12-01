<?php

namespace App\Service\Ship\InfosProvider;

use App\Domain\ShipInfo;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;

class ApiShipInfosProvider implements ShipInfosProviderInterface
{
    private const BASE_URL = 'https://robertsspaceindustries.com';
    private const MEDIA_URL = 'https://media.robertsspaceindustries.com';

    /** @var Client */
    private $client;
    private $logger;
    private $cache;
    /** @var iterable */
    private $ships;

    public function __construct(LoggerInterface $logger, CacheInterface $cache)
    {
        $this->client = new Client(['base_uri' => self::BASE_URL]);
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * @return iterable|ShipInfo[]
     */
    public function getAllShips(): iterable
    {
        if (!$this->ships) {
            $this->ships = $this->cache->get('ship_matrix', function (CacheItem $cacheItem) {
                $cacheItem->expiresAfter(new \DateInterval('P3D'));

                return $this->scrap();
            });
        }

        return $this->ships;
    }

    private function scrap(): array
    {
        $response = $this->client->get('/ship-matrix/index');
        $contents = $response->getBody()->getContents();
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $this->logger->error(sprintf('Bad response status code from %s', self::BASE_URL), [
                'status_code' => $response->getStatusCode(),
                'raw_content' => $contents,
            ]);
            throw new \RuntimeException('Cannot retrieve ships infos.');
        }
        $json = \json_decode($contents, true);
        if (!$json) {
            $this->logger->error(sprintf('Bad json response from %s', self::BASE_URL), [
                'raw_content' => $contents,
                'json_error' => json_last_error(),
                'json_error_msg' => json_last_error_msg(),
            ]);
            throw new \RuntimeException('Cannot retrieve ships infos.');
        }
        if (!isset($json['success']) || !$json['success']) {
            $this->logger->error(sprintf('Bad json data from %s', self::BASE_URL), [
                'json' => $json,
            ]);
            throw new \RuntimeException('Cannot retrieve ships infos.');
        }

        $shipInfos = [];
        foreach ($json['data'] as $shipData) {
            $shipInfo = new ShipInfo();
            $shipInfo->id = $shipData['id'];
            $shipInfo->productionStatus = $shipData['production_status'] === 'flight-ready' ? ShipInfo::FLIGHT_READY : ShipInfo::NOT_READY;
            $shipInfo->minCrew = (int) $shipData['min_crew'];
            $shipInfo->maxCrew = (int) $shipData['max_crew'];
            $shipInfo->name = trim($shipData['name']);
            $shipInfo->size = $shipData['size'];
            $shipInfo->cargoCapacity = (int) $shipData['cargocapacity'];
            $shipInfo->pledgeUrl = self::BASE_URL.$shipData['url'];
            $shipInfo->manufacturerName = $shipData['manufacturer']['name'];
            $shipInfo->manufacturerCode = $shipData['manufacturer']['code'];
            $shipInfo->chassisId = $shipData['chassis_id'];
            $shipInfo->chassisName = static::transformChassisIdToFamilyName($shipInfo->chassisId);
            $shipInfo->mediaUrl = \count($shipData['media']) > 0 ? self::BASE_URL.$shipData['media'][0]['source_url'] ?? null : null;
            if (\count($shipData['media']) > 0) {
                $mediaUrl = $shipData['media'][0]['images']['store_small'] ?? null;
                if (strpos($mediaUrl, 'http') !== 0) { // fix some URL... Thanks Turbulent!
                    $mediaUrl = self::BASE_URL.$mediaUrl;
                }
                $shipInfo->mediaThumbUrl = $mediaUrl;
            }

            $shipInfos[$shipInfo->id] = $shipInfo;
        }

        $shipInfos = array_merge($shipInfos, $this->getSpecialCases($shipInfos));

        return $shipInfos;
    }

    private function getSpecialCases(array $officialInfos): array
    {
        $shipInfos = [];

        // add Greycat PTV
        $shipInfo = new ShipInfo();
        $shipInfo->id = '0';
        $shipInfo->productionStatus = ShipInfo::FLIGHT_READY;
        $shipInfo->minCrew = 1;
        $shipInfo->maxCrew = 2;
        $shipInfo->name = 'Greycat PTV';
        $shipInfo->size = ShipInfo::SIZE_VEHICLE;
        $shipInfo->pledgeUrl = self::BASE_URL.'/pledge/Standalone-Ships/Greycat-PTV-Buggy';
        $shipInfo->manufacturerName = 'Greycat Industrial';
        $shipInfo->manufacturerCode = 'GRIN'; // id=84
        $shipInfo->chassisId = '0';
        $shipInfo->chassisName = static::transformChassisIdToFamilyName($shipInfo->chassisId);
        $shipInfo->mediaUrl = self::BASE_URL.'/media/5rg8z7erquf0wr/source/Buggy.jpg';
        $shipInfo->mediaThumbUrl = self::BASE_URL.'/media/5rg8z7erquf0wr/store_small/Buggy.jpg';
        $shipInfos[$shipInfo->id] = $shipInfo;

        // add F8C Lightning Civilian
        $shipInfo = new ShipInfo();
        $shipInfo->id = '1001';
        $shipInfo->productionStatus = ShipInfo::NOT_READY;
        $shipInfo->minCrew = 1;
        $shipInfo->maxCrew = 1;
        $shipInfo->name = 'F8C Lightning Civilian';
        $shipInfo->size = ShipInfo::SIZE_MEDIUM;
        $shipInfo->pledgeUrl = 'https://starcitizen.tools/F8C_Lightning';
        $shipInfo->manufacturerName = 'Anvil Aerospace';
        $shipInfo->manufacturerCode = 'ANVL'; // id=3
        $shipInfo->chassisId = '1001';
        $shipInfo->chassisName = static::transformChassisIdToFamilyName($shipInfo->chassisId);
        $shipInfo->mediaUrl = 'https://starcitizen.tools/images/8/87/F8C_concierge.jpg';
        $shipInfo->mediaThumbUrl = 'https://starcitizen.tools/images/8/87/F8C_concierge.jpg';
        $shipInfos[$shipInfo->id] = $shipInfo;

        // add F8C Lightning Executive Edition
        $shipInfo = clone $shipInfos['1001'];
        $shipInfo->id = '1002';
        $shipInfo->name = 'F8C Lightning Executive Edition';
        $shipInfo->pledgeUrl = 'https://starcitizen.tools/F8C_Lightning_Executive_Edition';
        $shipInfo->mediaUrl = 'https://starcitizen.tools/images/1/16/F8C_Lightning_Executive_Edition.jpg';
        $shipInfo->mediaThumbUrl = 'https://starcitizen.tools/images/1/16/F8C_Lightning_Executive_Edition.jpg';
        $shipInfos[$shipInfo->id] = $shipInfo;

        // add Mustang Omega : AMD Edition
        $shipInfo = new ShipInfo();
        $shipInfo->id = '1070';
        $shipInfo->productionStatus = ShipInfo::FLIGHT_READY;
        $shipInfo->minCrew = 1;
        $shipInfo->maxCrew = 1;
        $shipInfo->name = 'Mustang Omega : AMD Edition';
        $shipInfo->size = ShipInfo::SIZE_SMALL;
        $shipInfo->pledgeUrl = 'https://robertsspaceindustries.com/pledge/ships/mustang/Mustang-Omega';
        $shipInfo->manufacturerName = 'Consolidated Outland';
        $shipInfo->manufacturerCode = 'CNOU'; // id=22
        $shipInfo->chassisId = '16';
        $shipInfo->chassisName = static::transformChassisIdToFamilyName($shipInfo->chassisId);
        $shipInfo->mediaUrl = self::BASE_URL.'/media/gmru9y7ynd1bbr/source/Omega-Front.jpg';
        $shipInfo->mediaThumbUrl = self::BASE_URL.'/media/gmru9y7ynd1bbr/store_small/Omega-Front.jpg';
        $shipInfos[$shipInfo->id] = $shipInfo;

        // add Carrack with Pisces Expedition
        $shipInfo = clone $officialInfos['62'];
        $shipInfo->id = '1062';
        $shipInfo->name = 'Carrack with Pisces Expedition';
        $shipInfo->pledgeUrl = 'https://robertsspaceindustries.com/pledge/Standalone-Ships/Anvil-Carrack-IAE-2949';
        $shipInfo->mediaUrl = self::MEDIA_URL.'/g7dx300udpe1v/source.jpg';
        $shipInfo->mediaThumbUrl = self::MEDIA_URL.'/g7dx300udpe1v/store_small.jpg';
        $shipInfos[$shipInfo->id] = $shipInfo;

        return $shipInfos;
    }

    public function getShipsByChassisId(string $chassisId): iterable
    {
        return array_filter($this->getAllShips(), static function (ShipInfo $shipInfo) use ($chassisId): bool {
            return $shipInfo->chassisId === $chassisId;
        });
    }

    public function getShipById(string $id): ?ShipInfo
    {
        $ships = $this->getAllShips();
        if (!\array_key_exists($id, $ships)) {
            return null;
        }

        return $ships[$id];
    }

    public function getShipByName(string $name): ?ShipInfo
    {
        $name = trim($name);
        $ships = $this->getAllShips();
        /** @var ShipInfo $ship */
        foreach ($ships as $ship) {
            if (mb_strtolower($ship->name) === mb_strtolower($name)) {
                return $ship;
            }
        }

        return null;
    }

    public static function transformChassisIdToFamilyName(string $chassisId): string
    {
        switch ($chassisId) {
            case '0':
                return 'Greycat';
            case '1':
                return 'Aurora';
            case '2':
                return '300';
            case '3':
                return 'Hornet';
            case '4':
                return 'Constellation';
            case '5':
                return 'Freelancer';
            case '6':
                return 'Cutlass';
            case '7':
                return 'Avenger';
            case '8':
                return 'Gladiator';
            case '9':
                return 'M50';
            case '10':
                return 'Starfarer';
            case '11':
                return 'Caterpillar';
            case '12':
                return 'Retaliator';
            case '13':
                return 'Scythe';
            case '14':
                return 'Idris';
            case '15':
                return 'Merlin';
            case '16':
                return 'Mustang';
            case '17':
                return 'Redeemer';
            case '18':
                return 'Gladius';
            case '19':
                return 'Khartu';
            case '20':
                return 'Merchantman';
            case '21':
                return '890 Jump';
            case '22':
                return 'Carrack';
            case '23':
                return 'Herald';
            case '24':
                return 'Hull';
            case '25':
                return 'Orion';
            case '26':
                return 'Reclaimer';
            case '28':
                return 'Javelin';
            case '30':
                return 'Vanguard';
            case '31':
                return 'Reliant';
            case '32':
                return 'Starliner';
            case '33':
                return 'Glaive';
            case '34':
                return 'Endeavor';
            case '35':
                return 'Sabre';
            case '37':
                return 'Crucible';
            case '38':
                return 'P72 Archimedes';
            case '39':
                return 'Blade';
            case '40':
                return 'Prospector';
            case '41':
                return 'Buccaneer';
            case '42':
                return 'Dragonfly';
            case '43':
                return 'MPUV';
            case '44':
                return 'Terrapin';
            case '45':
                return 'Polaris';
            case '46':
                return 'Prowler';
            case '47':
                return '85X';
            case '48':
                return 'Razor';
            case '49':
                return 'Hurricane';
            case '50':
                return 'Defender';
            case '51':
                return 'Eclipse';
            case '52':
                return 'Nox';
            case '53':
                return 'Cyclone';
            case '54':
                return 'Ursa';
            case '55':
                return '600i';
            case '56':
                return 'X1';
            case '57':
                return 'Pioneer';
            case '58':
                return 'Hawk';
            case '59':
                return 'Hammerhead';
            case '60':
                return 'Planetary Beacon'; // NOT A SHIP ! Oo
            case '61':
                return 'Nova';
            case '62':
                return 'Vulcan';
            case '63':
                return '100';
            case '64':
                return 'Starlifter';
            case '65':
                return 'Vulture';
            case '66':
                return 'Apollo';
            case '67':
                return 'Mercury Star Runner';
            case '68':
                return 'Valkyrie';
            case '69':
                return 'Kraken';
            case '70':
                return 'Arrow';
            case '71':
                return "San'tok.yāi";
            case '72':
                return 'SRV';
            case '73':
                return 'Corsair';
            case '74':
                return 'Ranger';
            case '75':
                return 'Ballista';
            case '76':
                return 'Nautilus';
            case '77':
                return 'Mantis';
            case '78':
                return 'Pisces';
            case '1001':
                return 'F8C';
        }

        return 'Unknown chassis';
    }

    public function shipNamesAreEquals(string $hangarName, string $providerName): bool
    {
        return $this->transformHangarToProvider(trim($hangarName)) === $providerName;
    }

    public function transformProviderToHangar(string $providerName): string
    {
        $map = array_flip(static::mapHangarToProvider());
        if (\array_key_exists($providerName, $map)) {
            return $map[$providerName];
        }

        return $providerName;
    }

    public function transformHangarToProvider(string $hangarName): string
    {
        $hangarName = trim($hangarName);
        $map = static::mapHangarToProvider();
        if (\array_key_exists($hangarName, $map)) {
            return $map[$hangarName];
        }

        return $hangarName;
    }

    private static function mapHangarToProvider(): array
    {
        // hangar name => provider name
        return [
            '315p Explorer' => '315p',
            '325a Fighter' => '325a',
            '350r Racer' => '350r',
            '600i Exploration Module' => '600i Explorer',
            '600i Touring Module' => '600i Touring',
            '890 JUMP' => '890 Jump',
            'Aopoa San\'tok.yāi' => 'San\'tok.yāi',
            'Argo SRV' => 'SRV',
            'Ballista' => 'Anvil Ballista',
            'Ballista Snowblind' => 'Anvil Ballista Snowblind',
            'Ballista Dunestalker' => 'Anvil Ballista Dunestalker',
            'Pisces' => 'C8 Pisces',
            'Pisces - Expedition' => 'C8X Pisces Expedition',
            'Consolidated Outland Pioneer' => 'Pioneer',
            'Crusader Mercury Star Runner' => 'Mercury Star Runner',
            'Cyclone RC' => 'Cyclone-RC',
            'Cyclone RN' => 'Cyclone-RN',
            'Cyclone-TR' => 'Cyclone-TR', // yes, same
            'Cyclone AA' => 'Cyclone-AA',
            'Defender' => 'Banu Defender',
            'Hercules Starlifter C2' => 'C2 Hercules',
            'Hercules Starlifter M2' => 'M2 Hercules',
            'Hercules Starlifter A2' => 'A2 Hercules',
            'Hornet F7C' => 'F7C Hornet',
            'Hornet F7C-M Heartseeker' => 'F7C-M Super Hornet Heartseeker',
            'Idris-P Frigate' => 'Idris-P',
            'Idris-M Frigate' => 'Idris-M',
            'Khartu-al' => 'Khartu-Al',
            'Nova Tank' => 'Nova',
            'P-72 Archimedes' => 'P72 Archimedes',
            'Reliant Kore - Mini Hauler' => 'Reliant Kore',
            'Reliant Mako - News Van' => 'Reliant Mako',
            'Reliant Sen - Researcher' => 'Reliant Sen',
            'Reliant Tana - Skirmisher' => 'Reliant Tana',
            'Valkyrie' => 'Valkyrie',
            'Valkyrie Liberator Edition' => 'Valkyrie Liberator Edition',
            'X1' => 'X1 Base',
            'X1 - FORCE' => 'X1 Force',
            'X1 - VELOCITY' => 'X1 Velocity',
        ];
    }
}
