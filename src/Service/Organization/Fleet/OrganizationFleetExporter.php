<?php

namespace App\Service\Organization\Fleet;

use App\Domain\SpectrumIdentification;
use App\Entity\Citizen;
use App\Entity\User;
use App\Repository\CitizenRepository;
use App\Service\Dto\RsiOrgaMemberInfos;
use App\Service\Organization\MembersInfosProvider\OrganizationMembersInfosProviderInterface;
use Symfony\Component\Security\Core\Security;

class OrganizationFleetExporter
{
    private CitizenRepository $citizenRepository;
    private Security $security;
    private OrganizationMembersInfosProviderInterface $organizationMembersInfosProvider;

    public function __construct(
        CitizenRepository $citizenRepository,
        Security $security,
        OrganizationMembersInfosProviderInterface $organizationMembersInfosProvider
    ) {
        $this->citizenRepository = $citizenRepository;
        $this->security = $security;
        $this->organizationMembersInfosProvider = $organizationMembersInfosProvider;
    }

    public function exportOrgaMembers(string $organizationSid): array
    {
        $memberInfos = $this->organizationMembersInfosProvider->retrieveInfos(new SpectrumIdentification($organizationSid));
        if (isset($memberInfos['error']) && $memberInfos['error'] === 'orga_too_big') {
            throw new \LogicException('This orga is too big.');
        }

        $handles = array_map(static function (RsiOrgaMemberInfos $info) {
            return mb_strtolower($info->handle);
        }, $memberInfos['visibleCitizens']);
        /** @var Citizen[] $citizens */
        $citizens = $this->citizenRepository->findSomeHandlesWithLastFleet($handles);

        $data = [];
        /** @var RsiOrgaMemberInfos $memberInfo */
        foreach ($memberInfos['visibleCitizens'] as $memberInfo) {
            $memberCitizen = null;
            foreach ($citizens as $citizen) {
                if (mb_strtolower($citizen->getActualHandle()->getHandle()) === mb_strtolower($memberInfo->handle)) {
                    $memberCitizen = $citizen;
                    break;
                }
            }

            $lastFleet = $memberCitizen !== null ? $memberCitizen->getLastFleet() : null;
            $memberData = [
                'Handle' => $memberInfo->handle,
                'Nickname' => $memberInfo->nickname,
                'Rank' => $memberInfo->rank,
                'Rank title' => $memberInfo->rankName,
                'Number of ships' => $lastFleet !== null ? count($lastFleet->getShips()) : null,
                'Last fleet upload date' => $lastFleet !== null ? $lastFleet->getUploadDate()->format('Y-m-d') : null,
            ];
            if ($memberCitizen === null) {
                $memberData['Status'] = 'Not registered';
            } elseif ($memberCitizen->getLastFleet() === null) {
                $memberData['Status'] = 'Registered';
            } else {
                $memberData['Status'] = 'Fleet uploaded';
            }
            $data[] = $memberData;
        }

        return $data;
    }

    public function exportOrgaFleet(string $organizationSid): array
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $loggedCitizen = null;
        if ($user !== null) {
            $loggedCitizen = $user->getCitizen();
        }
        $citizens = $this->citizenRepository->findVisiblesByOrganization($organizationSid, $loggedCitizen, true);

        $ships = [];
        $totalColumn = [];
        foreach ($citizens as $citizen) {
            $citizenHandle = $citizen->getActualHandle()->getHandle();
            if (null === $lastFleet = $citizen->getLastFleet()) {
                continue;
            }
            foreach ($lastFleet->getShips() as $ship) {
                $shipName = $ship->getNormalizedName() ?: $ship->getName();
                if (!isset($ships[$shipName])) {
                    $ships[$shipName] = [$citizenHandle => 1];
                } elseif (!isset($ships[$shipName][$citizenHandle])) {
                    $ships[$shipName][$citizenHandle] = 1;
                } else {
                    ++$ships[$shipName][$citizenHandle];
                }
            }
        }
        ksort($ships);

        $data = [];

        // rows Ships
        foreach ($ships as $shipName => $owners) {
            $total = 0;
            $columns = [];
            foreach ($owners as $ownerName => $countOwner) {
                $total += $countOwner;
                $columns[$ownerName] = $countOwner;
                if (!isset($totalColumn[$ownerName])) {
                    $totalColumn[$ownerName] = $countOwner;
                } else {
                    $totalColumn[$ownerName] += $countOwner;
                }
            }
            $data[] = array_merge([
                'Ship Model' => $shipName,
                'Ship Total' => $total,
            ], $columns);
        }

        // row Total
        $total = 0;
        $columns = [];
        foreach ($totalColumn as $ownerName => $countOwner) {
            $total += $countOwner;
            $columns[$ownerName] = $countOwner;
        }
        $data[] = array_merge([
            'Ship Model' => 'Total',
            'Ship Total' => $total,
        ], $columns);

        return $data;
    }
}
