<?php

namespace App\Tests\Controller\Organization\Fleet;

use App\Entity\User;
use App\Tests\WebTestCase;

class FleetsControllerTest extends WebTestCase
{
    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPublicNotAuth(): void
    {
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/gardiens', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArraySubset([
            [
                'chassisId' => '8502c9fd-6b1a-47e1-a7fc-6cb034b94da1',
                'name' => 'Aurora',
                'count' => 2,
                'manufacturerCode' => 'RSI',
                'mediaThumbUrl' => 'https://robertsspaceindustries.com/media/ohbfgn1ebcsnar/store_small/Rsi_aurora_mr_storefront_visual.jpg',
            ],
        ], $json);
    }

    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPrivateAuth(): void
    {
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Ioni']);
        $this->logIn($user);
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/flk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArraySubset([
            [
                'chassisId' => 'f92c7c98-a8d2-4c79-b34c-c728d4fffbfc',
                'name' => 'Cutlass',
                'count' => 2,
                'manufacturerCode' => 'DRAK',
                'mediaThumbUrl' => 'https://robertsspaceindustries.com/media/7tcxllnna6a9hr/store_small/Drake_cutlass_storefront_visual.jpg',
            ],
        ], $json);
    }

    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPrivateAuthBadOrga(): void
    {
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Gardien1']);
        $this->logIn($user);
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/not_exist', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('bad_organization', $json['error']);
    }

    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPrivateNotAuth(): void
    {
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/flk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('not_enough_rights_public', $json['error']);
    }

    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPrivateAuthPrivateOrga(): void
    {
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Gardien1']);
        $this->logIn($user);
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/flk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('not_enough_rights_private', $json['error']);
    }

    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPrivateAuthAdminOnlyOrgaFail(): void
    {
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Pulsar42Member1']);
        $this->logIn($user);
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/pulsar42', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $json = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('not_enough_rights_admin', $json['error']);
    }

    /**
     * @group functional
     * @group organization_fleet
     */
    public function testOrgaFleetsPrivateAuthAdminOnlyOrgaSuccess(): void
    {
        $user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Pulsar42Admin']);
        $this->logIn($user);
        $this->client->xmlHttpRequest('GET', '/api/fleet/orga-fleets/pulsar42', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }
}
