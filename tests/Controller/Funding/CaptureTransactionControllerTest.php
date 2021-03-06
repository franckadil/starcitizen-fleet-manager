<?php

namespace App\Tests\Controller\Profile;

use App\Entity\Funding;
use App\Entity\User;
use App\Message\Funding\SendOrderCaptureSummaryMail;
use App\Tests\Service\PayPal\MockPayPalHttpClient;
use App\Tests\WebTestCase;
use Symfony\Component\Messenger\Transport\TransportInterface;

class CaptureTransactionControllerTest extends WebTestCase
{
    private ?User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->doctrine->getRepository(User::class)->findOneBy(['nickname' => 'Ioni']);
    }

    /**
     * @group functional
     * @group funding
     */
    public function testIndex(): void
    {
        $this->logIn($this->user);

        $paypalHttpClient = static::$container->get(MockPayPalHttpClient::class);
        $paypalHttpClient->setCaptureResponse('618c4d07-6e1d-49e3-91e9-d269944de266', '1.00', '0.67');

        $this->assertSame(5133, $this->user->getCoins()); // coins before capture

        $this->client->xmlHttpRequest('POST', '/api/funding/capture-transaction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'orderID' => 'cf42c65f',
        ]));

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('COMPLETED', $json['funding']['paypalStatus']);
        /** @var Funding $funding */
        $funding = $this->doctrine->getRepository(Funding::class)->find('618c4d07-6e1d-49e3-91e9-d269944de266');
        $this->assertSame('COMPLETED', $funding->getPaypalStatus());
        $this->assertSame(100, $funding->getAmount());
        $this->assertSame(67, $funding->getNetAmount());
        $this->assertArraySubset([
            'payments' => [
                'captures' => [
                    [
                        'status' => 'COMPLETED',
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '1.00',
                        ],
                        'seller_receivable_breakdown' => [
                            'gross_amount' => [
                                'currency_code' => 'USD',
                                'value' => '1.00',
                            ],
                            'paypal_fee' => [
                                'currency_code' => 'USD',
                                'value' => '0.33',
                            ],
                            'net_amount' => [
                                'currency_code' => 'USD',
                                'value' => '0.67',
                            ],
                        ],
                        'custom_id' => '618c4d07-6e1d-49e3-91e9-d269944de266',
                    ],
                ],
            ],
        ], $funding->getPaypalPurchase());
        $this->assertSame(5133 + 100, $this->user->getCoins()); // added X coins

        /** @var TransportInterface $transport */
        $transport = static::$container->get('messenger.transport.sync');
        $envelopes = $transport->get();
        $this->assertCount(1, $envelopes);
        $this->assertInstanceOf(SendOrderCaptureSummaryMail::class, $envelopes[0]->getMessage());
        $this->assertSame($funding->getId()->toString(), $envelopes[0]->getMessage()->getFundingId()->toString());
    }

    /**
     * @group functional
     * @group funding
     */
    public function testOrderNotExist(): void
    {
        $this->logIn($this->user);
        $this->client->xmlHttpRequest('POST', '/api/funding/capture-transaction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'orderID' => '34da4bd8', // order to another user
        ]));
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('order_not_exist', $json['error']);
        $this->assertSame('Sorry, we cannot find the transaction. Please try again.', $json['errorMessage']);

        $this->client->xmlHttpRequest('POST', '/api/funding/capture-transaction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'orderID' => 'not_exist',
        ]));
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @group functional
     * @group funding
     */
    public function testOrderAlreadyHandled(): void
    {
        $this->logIn($this->user);
        $this->client->xmlHttpRequest('POST', '/api/funding/capture-transaction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'orderID' => 'e39b153c', // COMPLETED funding
        ]));

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @group functional
     * @group funding
     */
    public function testIndexNotAuth(): void
    {
        $this->client->xmlHttpRequest('POST', '/api/funding/capture-transaction', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'orderID' => 'cf42c65f',
        ]));

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('no_auth', $json['error']);
    }
}
