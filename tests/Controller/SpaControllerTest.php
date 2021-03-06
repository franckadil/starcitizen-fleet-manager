<?php

namespace App\Tests\Controller;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class SpaControllerTest extends PantherTestCase
{
    use ReloadDatabaseTrait;

    private $client;

    public static function setUpBeforeClass(): void
    {
        @mkdir('var/screenshots');
    }

    public function setUp(): void
    {
        if ($_SERVER['NO_PANTHER'] ?? false) {
            $this->client = Client::createSeleniumClient('http://selenium-hub:4444/wd/hub', DesiredCapabilities::chrome()->setCapability(
                'goog:'.ChromeOptions::CAPABILITY, [
                'w3c' => false,
                'binary' => '',
                'args' => ['start-maximized'],
            ],//(new ChromeOptions())->addArguments(['start-maximized'])
            ), 'http://apache-test');
        } else {
            $this->client = Client::createChromeClient(null, null, [], 'http://apache-test');
        }
        static::bootKernel();
    }

    public function tearDown(): void
    {
        $this->client->quit();
    }

    private function pause(int $s = 30): void
    {
        $this->client->wait($s, 100)->until(static function () {
            return false;
        });
    }

    private function login(?string $userId = null): void
    {
        $this->client->request('GET', '/connect/discord'.($userId ? "?userId=$userId" : ''));
    }

    /**
     * @group end2end
     * @group spa
     */
    public function testGlobalSpa(): void
    {
        try {
            $this->client->request('GET', '/');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return stripos($driver->findElement(WebDriverBy::cssSelector('h1'))->getText(), 'Fleet Manager') !== false;
            });
            $this->client->refreshCrawler();

            // registration
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Use Now")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::id('modal-registration-login'))->isDisplayed();
            });
            $this->client->findElement(WebDriverBy::xpath('//span[contains(text(), "Sign up")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::xpath('//button[contains(text(), "Register")]'))->isDisplayed();
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#input-registration-email'))->sendKeys('foobar@example.com');
            $this->client->findElement(WebDriverBy::cssSelector('input#input-registration-password'))->sendKeys('123456');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Register")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::xpath('//*[@id="modal-registration-login"]//button[contains(text(), "Log in")]'))->isDisplayed()
                    && $driver->findElement(WebDriverBy::cssSelector('.alert'))->isDisplayed();
            });
            $this->assertContains('Well done! An email has been sent to you to confirm your registration.', $this->client->findElement(WebDriverBy::cssSelector('.alert.alert-success'))->getText());

            // lost password
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::xpath('//*[@id="modal-registration-login"]//button[contains(text(), "Log in")]'))->isDisplayed();
            });
            $this->client->findElement(WebDriverBy::xpath('//div[contains(text(), "lost your password?")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::xpath('//button[contains(text(), "Send me a new password")]'))->isDisplayed();
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#input-lost-password-email'))->sendKeys('foobar@example.com');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Send me a new password")]'))->click();

            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::xpath('//*[@id="modal-registration-login"]//button[contains(text(), "Log in")]'))->isDisplayed()
                    && $driver->findElement(WebDriverBy::cssSelector('.alert'))->isDisplayed();
            });
            $this->assertContains('If we recognize this email, we will send to you the instructions to create a new password.', $this->client->findElement(WebDriverBy::cssSelector('.alert.alert-success'))->getText());

            // login
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::xpath('//*[@id="modal-registration-login"]//button[contains(text(), "Log in")]'))->isDisplayed();
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#input-email'))->sendKeys('ioni@example.com');
            $this->client->findElement(WebDriverBy::cssSelector('input#input-password'))->sendKeys('123456');
            $this->client->findElement(WebDriverBy::xpath('//*[@id="modal-registration-login"]//button[contains(text(), "Log in")]'))->click();

            // Profile
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $cardBody = $this->client->findElement(WebDriverBy::cssSelector('.js-update-sc-handle .card-body'))->getText();
            $this->assertContains('SC Handle : ionni', $cardBody);
            $this->assertContains('SC Number : 123456', $cardBody);
            $this->assertContains('Update my SC handle', $cardBody);

            $cardBody = $this->client->findElement(WebDriverBy::cssSelector('.js-preferences .card-body'))->getText();
            $this->assertContains('Personal fleet policy', $cardBody);

            $this->assertTrue($this->client->executeScript('return document.querySelector(\'input[name="public-choice"][value="public"]\').checked;'), 'Personal fleet policy is not public.');

            // My Fleet
            $this->client->refreshCrawler();
            $this->client->clickLink('My Fleet');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-card-ship'))) > 0;
            });
            $this->assertSame('Cutlass Black', $this->client->findElement(WebDriverBy::cssSelector('.card-title'))->getText());
            $this->assertContains('Manufacturer: Drake', $this->client->findElement(WebDriverBy::cssSelector('.card-body'))->getText());
            $this->assertContains('Insurance: Lifetime', $this->client->findElement(WebDriverBy::cssSelector('.card-body'))->getText());
            $this->assertContains('Cost: $ 110', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('.card-body'))->getText()));
            $this->assertContains('Pledge date: April 10, 2019', $this->client->findElement(WebDriverBy::cssSelector('.card-body'))->getText());
            $this->assertStringEndsWith('/api/create-citizen-fleet-file', $this->client->findElement(WebDriverBy::linkText('Export my fleet (.json)'))->getAttribute('href'));

            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Update my fleet")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::id('modal-upload-fleet___BV_modal_body_'))->isDisplayed();
            });
            $this->client->refreshCrawler();
            $this->client->wait(5, 100)->until(static function (WebDriver $driver) {
                if (count($driver->findElements(WebDriverBy::cssSelector('#modal-upload-fleet .close'))) === 1) {
                    $driver->findElement(WebDriverBy::cssSelector('#modal-upload-fleet .close'))->click();
                }

                return count($driver->findElements(WebDriverBy::cssSelector('#modal-upload-fleet'))) === 0;
            });

            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::id('modal-upload-fleet'))) === 0;
            });

            // My Orgas
            $this->client->clickLink('My Orgas');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return (int) $driver->executeScript('return document.querySelectorAll(".card-ship").length;') > 0;
            });
            $this->client->refreshCrawler();
            $this->assertSame('FallKrom', $this->client->findElement(WebDriverBy::cssSelector('#select-orga__BV_toggle_'))->getText());

            // Months Insurance
            $this->login('46380677-9915-4b7c-87ba-418840cb1772');
            $this->client->request('GET', '/my-fleet');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-card-ship'))) > 0;
            });
            $this->assertSame('Aurora MR', $this->client->findElement(WebDriverBy::cssSelector('.card-title'))->getText());
            $this->assertContains('Manufacturer: Roberts Space Industries', $this->client->findElement(WebDriverBy::cssSelector('.card-body'))->getText());
            $this->assertContains('Insurance: 6 months', $this->client->findElement(WebDriverBy::cssSelector('.card-body'))->getText());
        } catch (\Exception $e) {
            $this->client->takeScreenshot(sprintf('var/screenshots/error-%s.png', date('Y-m-d_H:i:s')));
            throw $e;
        }
    }

    /**
     * @group end2end
     * @group spa
     */
    public function testProfile(): void
    {
        $this->login();

        try {
            // change personal fleet policy
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->findElement(WebDriverBy::xpath('//label[contains(text(), "Private")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Changes saved', $this->client->findElement(WebDriverBy::cssSelector('.toast-success'))->getText());

            // refresh my RSI profile
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Refresh my RSI Profile")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Your RSI public profile has been successfully refreshed.', $this->client->findElement(WebDriverBy::cssSelector('.toast-success'))->getText());

            // refresh my RSI profile too soon
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Refresh my RSI Profile")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Please wait 9 minutes before refreshing.', $this->client->findElement(WebDriverBy::cssSelector('.toast-error'))->getText());

            // update sc handle not exist
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#form_update_sc_handle'))->sendKeys('not_found');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Update my SC handle")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert-danger'))) > 0;
            });
            $this->assertContains('Sorry, the handle not_found does not exist.', $this->client->findElement(WebDriverBy::cssSelector('.alert.alert-danger'))->getText());

            // update sc handle not same number
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#form_update_sc_handle'))->sendKeys('fake_citizen_1');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Update my SC handle")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert-danger'))) > 0;
            });
            $this->assertContains('This SC handle does not have the same SC number than yours.', $this->client->findElement(WebDriverBy::cssSelector('.alert.alert-danger'))->getText());

            // update sc handle success
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#form_update_sc_handle'))->sendKeys('ionni');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Update my SC handle")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Your new SC Handle has been successfully updated!', $this->client->findElement(WebDriverBy::cssSelector('.toast-success'))->getText());

            // change password success
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-security'))) > 0;
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#input-change-password-old-password'))->sendKeys('123456');
            $this->client->findElement(WebDriverBy::cssSelector('input#input-change-password-password'))->sendKeys('456789');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Change my password")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Your password has been successfully updated!', $this->client->findElement(WebDriverBy::cssSelector('.toast-success'))->getText());

            // change email success
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-security'))) > 0;
            });
            $this->client->findElement(WebDriverBy::cssSelector('input#input-change-email-new-email'))->sendKeys('new-email@example.com');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Change my email")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert'))) > 0;
            });
            $this->assertContains('An email has been sent to you to confirm your new email address.', $this->client->findElement(WebDriverBy::cssSelector('.alert-success'))->getText());

            // Link Discord
            $this->login('77ea3d73-a786-4f0f-83dd-36a37265f952'); // linksocialnetworks-with-citizen@example.com
            $this->client->request('GET', '/connect/service/discord?discordId=123456789002&discordTag=0002&nickname=Ashuvidz'); // click on "Link my Discord"
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('.js-security .alert-warning form'))) > 0;
            });
            $labels = $this->client->findElements(WebDriverBy::cssSelector('.js-security label'));
            $this->assertContains('ashuvidz (VyrtualSynthese)', $labels[1]->getText());
            $this->assertContains('link_social_networks (Link social networks)', $labels[0]->getText());
            $this->client->findElement(WebDriverBy::xpath('//label[contains(text(), "ashuvidz (VyrtualSynthese)")]'))->click();
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Link my Discord account and use the selected Citizen.")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Your Discord account is successfully linked!', $this->client->findElement(WebDriverBy::cssSelector('.toast-success'))->getText());
            $this->assertCount(0, $this->client->findElements(WebDriverBy::cssSelector('.js-security .alert-warning')));

            // Link RSI Account
            $this->login('2a288e5d-f83f-4b0d-9275-3351b8cb3848'); // NoCitizen
            $this->client->request('GET', '/profile');
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::className('card-header'))->getText(), 'Link your RSI Account') !== false;
            });
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Okay, I\'m ready to link my account")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('.collapse.show'))) === 1
                    && count($driver->findElements(WebDriverBy::cssSelector('.collapsing'))) === 0;
            });
            $this->assertContains('1. Who are you?', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-1 h4'))->getText());
            $this->client->findElement(WebDriverBy::cssSelector('input#form_handle'))->sendKeys('not_found');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Search")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert-danger'))) > 0;
            });
            $this->assertContains('Sorry, it seems that SC Handle not_found does not exist. Try to check the typo and search again.', $this->client->findElement(WebDriverBy::cssSelector('.alert.alert-danger'))->getText());
            $this->client->findElement(WebDriverBy::cssSelector('input#form_handle'))->clear();
            $this->client->findElement(WebDriverBy::cssSelector('input#form_handle'))->sendKeys('fake_citizen_1');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Search")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText(), 'Handle: fake_citizen_1') !== false;
            });

            $this->assertContains('Nickname: Fake Citizen 1', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText());
            $this->assertContains('Handle: fake_citizen_1', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText());
            $this->assertContains('Number: 135790', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText());
            $this->assertContains('Main orga: flk', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText());
            $this->assertContains('All orgas: flk, gardiens', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText());

            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Great, this is my account, let\'s continue!")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('.collapse.show'))) === 2
                    && count($driver->findElements(WebDriverBy::cssSelector('.collapsing'))) === 0;
            });
            $this->assertContains('2. Special marker', $this->client->findElement(WebDriverBy::cssSelector('#collapse-step-2 h4'))->getText());
            $this->assertSame(64, $this->client->executeScript('return document.getElementById("form_user_token").value.length;'), 'The token must be 64 chars long.');
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Done! I\'ve pasted the token in my bio.")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert-danger'))) > 0;
            });
            $this->assertContains('Sorry, your RSI bio does not contain this token. Please copy-paste the following token to your RSI short bio.', $this->client->findElement(WebDriverBy::cssSelector('.alert.alert-danger'))->getText());

            // set a well-formed short bio user
            $this->client->findElement(WebDriverBy::cssSelector('input#form_handle'))->clear();
            $this->client->findElement(WebDriverBy::cssSelector('input#form_handle'))->sendKeys('user_nocitizen_well_formed_bio'); // we are logged with "nocitizen" user
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Search")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('#collapse-step-1'))->getText(), 'Handle: user_nocitizen_well_formed_bio') !== false;
            });
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Great, this is my account, let\'s continue!")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('.collapse.show'))) === 2
                    && count($driver->findElements(WebDriverBy::cssSelector('.collapsing'))) === 0;
            });
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Done! I\'ve pasted the token in my bio.")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('toast-message'))) > 0;
            });
            $this->assertContains('Your RSI account has been successfully linked! You can remove the token from your bio.', $this->client->findElement(WebDriverBy::cssSelector('.toast-success'))->getText());
        } catch (\Exception $e) {
            $this->client->takeScreenshot(sprintf('var/screenshots/error-%s.png', date('Y-m-d_H:i:s')));
            throw $e;
        }
    }

    /**
     * @group end2end
     * @group spa
     * @group spa_orgas
     */
    public function testOrganizationFleets(): void
    {
        $this->login('503e3bc1-cff9-42b8-9f27-a6064b0a78f2'); // multiple orga + ships each size

        try {
            // check main orga as default orga
            $this->client->request('GET', '/profile');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('js-update-sc-handle'))) > 0;
            });
            $this->client->clickLink('My Orgas');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return (int) $driver->executeScript('return document.querySelectorAll(".card-ship").length;') > 0
                    && count($driver->findElements(WebDriverBy::id('select-orga'))) === 1;
            });
            $this->assertSame('http://apache-test/organization-fleet/gardiens', $this->client->getCurrentURL());
            $this->assertContains('Les Gardiens', $this->client->findElement(WebDriverBy::id('select-orga'))->getText());

            $this->assertContains('Les Gardiens', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertContains('Lord', $this->client->findElement(WebDriverBy::cssSelector('p'))->getText());
            $this->assertCount(4, $this->client->findElements(WebDriverBy::cssSelector('.rank-icon-active')));

            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(6, $cardShips);
            $this->assertSame('RSI - Aurora', $cardShips[0]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('2', $cardShips[0]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('TMBL - Ranger', $cardShips[1]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[1]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('RSI - Orion', $cardShips[2]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[2]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('DRAK - Dragonfly', $cardShips[3]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[3]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('DRAK - Cutlass', $cardShips[4]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[4]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('RSI - Constellation', $cardShips[5]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[5]->findElement(WebDriverBy::className('card-ship-counter'))->getText());

            $cardShips[0]->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('.ship-family-detail-variant h4'))->getText(), 'Aurora MR') !== false
                    && strpos($driver->findElement(WebDriverBy::className('ship-family-detail-variant-ownerlist'))->getText(), 'gardien1') !== false;
            });

            $detail = $this->client->findElement(WebDriverBy::id('ship-family-detail-5'));
            $variants = $detail->findElements(WebDriverBy::className('ship-family-detail-variant'));
            $this->assertCount(1, $variants);
            $this->assertSame('Aurora MR', $variants[0]->findElement(WebDriverBy::cssSelector('h4'))->getText());
            $this->assertContains('gardien1 : 1', $variants[0]->findElement(WebDriverBy::className('ship-family-detail-variant-ownerlist'))->getText());
            $this->assertContains('ihaveships : 1', $variants[0]->findElement(WebDriverBy::className('ship-family-detail-variant-ownerlist'))->getText());

            $this->client->findElement(WebDriverBy::id('filters_input_ship_name'))->click();
            $this->client->findElement(WebDriverBy::xpath('//li[contains(text(), "Aurora MR")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('card-ship'))) === 1;
            });
            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(1, $cardShips);
            $this->assertSame('RSI - Aurora', $cardShips[0]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('2', $cardShips[0]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->client->findElement(WebDriverBy::className('vs__deselect'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return !$driver->findElement(WebDriverBy::className('ship-family-detail-variants-wrapper'))->isDisplayed();
            });

            $this->client->findElement(WebDriverBy::id('filters_input_citizen_id'))->click();
            $this->client->findElement(WebDriverBy::xpath('//li[contains(text(), "gardien1")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('card-ship'))) === 1;
            });
            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(1, $cardShips);
            $this->assertSame('RSI - Aurora', $cardShips[0]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[0]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->client->findElement(WebDriverBy::className('vs__deselect'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return !$driver->findElement(WebDriverBy::className('ship-family-detail-variants-wrapper'))->isDisplayed();
            });

            $this->client->findElement(WebDriverBy::id('filters_input_ship_size'))->click();
            $this->client->findElement(WebDriverBy::xpath('//li[contains(text(), "Vehicle")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('card-ship'))) === 1;
            });
            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(1, $cardShips);
            $this->assertSame('TMBL - Ranger', $cardShips[0]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[0]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->client->findElement(WebDriverBy::className('vs__deselect'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return !$driver->findElement(WebDriverBy::className('ship-family-detail-variants-wrapper'))->isDisplayed();
            });

            $this->client->findElement(WebDriverBy::id('filters_input_ship_status'))->click();
            $this->client->findElement(WebDriverBy::xpath('//li[contains(text(), "In concept")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('card-ship'))) === 2;
            });
            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(2, $cardShips);
            $this->assertSame('TMBL - Ranger', $cardShips[0]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[0]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('RSI - Orion', $cardShips[1]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[1]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->client->findElements(WebDriverBy::className('vs__clear'))[3]->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return !$driver->findElement(WebDriverBy::className('ship-family-detail-variants-wrapper'))->isDisplayed();
            });

            // Statistics Panel
            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Statistics")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return $driver->findElement(WebDriverBy::cssSelector('#orga-stats-total-ships .text-primary'))->getText() === '7'
                    && $driver->findElement(WebDriverBy::cssSelector('#orga-stats-ships-most-ships-citizen .text-primary'))->getText() === 'ihaveships (6)';
            });

            $this->assertSame('7', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-total-ships .text-primary'))->getText());
            $this->assertSame('TOTAL SHIPS', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-total-ships .text-muted'))->getText());
            $this->assertSame('5 / 2', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-ships-status .text-primary'))->getText());
            $this->assertSame('FLIGHT READY / IN CONCEPT', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-ships-status .text-muted'))->getText());
            $this->assertSame('13 / 18', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-crew .text-primary'))->getText());
            $this->assertSame('MIN CREW / MAX CREW', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-crew .text-muted'))->getText());
            $this->assertSame('0', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-cargo-capacity .text-primary'))->getText());
            $this->assertSame('CARGO CAPACITY (SCU)', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-cargo-capacity .text-muted'))->getText());

            $this->assertSame('2 / 5', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-registered-total .text-primary'))->getText());
            $this->assertSame('REGISTERED / TOTAL', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-registered-total .text-muted'))->getText());
            $this->assertSame('3.5', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-average-ships .text-primary'))->getText());
            $this->assertSame('AVERAGE SHIPS PER CITIZEN', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-average-ships .text-muted'))->getText());
            $this->assertSame('ihaveships (6)', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-ships-most-ships-citizen .text-primary'))->getText());
            $this->assertSame('CITIZEN WITH MOST SHIPS', $this->client->findElement(WebDriverBy::cssSelector('#orga-stats-ships-most-ships-citizen .text-muted'))->getText());

            // Orga Selector
            $this->client->findElement(WebDriverBy::id('select-orga__BV_toggle_'))->click();
            $this->client->findElement(WebDriverBy::xpath('//a[contains(@class, "dropdown-item")][contains(text(), "FallKrom")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('h4 a'))->getText(), 'FallKrom') !== false;
            });
            $this->assertSame('http://apache-test/organization-fleet/flk', $this->client->getCurrentURL());
            $this->assertContains('FallKrom', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertContains('Peasant', $this->client->findElement(WebDriverBy::cssSelector('p'))->getText());
            $this->assertCount(2, $this->client->findElements(WebDriverBy::cssSelector('.rank-icon-active')));

            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('card-ship'))) === 6;
            });
            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(6, $cardShips);
            $this->assertSame('DRAK - Cutlass', $cardShips[0]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('2', $cardShips[0]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('TMBL - Ranger', $cardShips[1]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[1]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('RSI - Orion', $cardShips[2]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[2]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('DRAK - Dragonfly', $cardShips[3]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[3]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('RSI - Constellation', $cardShips[4]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[4]->findElement(WebDriverBy::className('card-ship-counter'))->getText());
            $this->assertSame('RSI - Aurora', $cardShips[5]->findElement(WebDriverBy::className('card-title'))->getText());
            $this->assertSame('1', $cardShips[5]->findElement(WebDriverBy::className('card-ship-counter'))->getText());

            $cardShips[0]->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('.ship-family-detail-variant h4'))->getText(), 'Cutlass Black') !== false;
            });

            // supporter badge
            $this->assertCount(1, $this->client->findElements(WebDriverBy::cssSelector('.ship-family-detail-variant-ownerlist .supporter-badge')));

            $this->client->request('GET', '/organization-fleet/not_exist'); // inexistent orga
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert'))) > 0;
            });
            $this->assertContains("Sorry, this organization's fleet does not exist or is private. Try to login to see it.", $this->client->findElement(WebDriverBy::className('alert-danger'))->getText());

            // Public + Logged + Not My Orga
            $this->client->request('GET', '/logout');
            $this->login('d92e229e-e743-4583-905a-e02c57eacfe0'); // orga flk
            $this->client->request('GET', '/organization-fleet/gardiens'); // orga public + not my orga
            $this->client->refreshCrawler();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return (int) $driver->executeScript('return document.querySelectorAll(".card-ship").length;') > 0
                    && !$driver->executeScript('return !!document.getElementById("select-orga");');
            });
            $this->assertCount(6, $this->client->findElements(WebDriverBy::className('card-ship')));
            $this->assertFalse($this->client->executeScript('return !!document.getElementById("select-orga");'), 'There must not be the orga selector.');
            $this->assertContains('Les Gardiens', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertCount(0, $this->client->findElements(WebDriverBy::xpath('//button[contains(text(), "Export fleet")]')));
            $this->assertCount(1, $this->client->findElements(WebDriverBy::id('filters_input_ship_name')));
            $this->assertCount(0, $this->client->findElements(WebDriverBy::id('filters_input_citizen_id')));
            $this->assertCount(1, $this->client->findElements(WebDriverBy::id('filters_input_ship_size')));
            $this->assertCount(1, $this->client->findElements(WebDriverBy::id('filters_input_ship_status')));

            // Private + Logged + Not My Orga
            $this->client->request('GET', '/logout');
            $this->login('46380677-9915-4b7c-87ba-418840cb1772'); // orga gardiens
            $this->client->request('GET', '/organization-fleet/flk'); // orga private + not my orga
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert'))) > 0
                    && strpos($driver->findElement(WebDriverBy::cssSelector('h4 a'))->getText(), 'FallKrom') !== false;
            });
            $this->assertContains('FallKrom', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertContains('Sorry, you have not the rights to access to FallKrom fleet page.', $this->client->findElement(WebDriverBy::className('alert-danger'))->getText());

            // Public + Logout
            $this->client->request('GET', '/logout');
            $this->client->request('GET', '/organization-fleet/gardiens'); // orga public
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return (int) $driver->executeScript('return document.querySelectorAll(".card-ship").length;') > 0
                    && stripos($driver->findElement(WebDriverBy::cssSelector('h4 a'))->getText(), 'Les Gardiens') !== false;
            });
            $this->assertCount(6, $this->client->findElements(WebDriverBy::className('card-ship')));
            $this->assertFalse($this->client->executeScript('return !!document.getElementById("select-orga");'), 'There must not be the orga selector.');
            $this->assertContains('Les Gardiens', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertCount(0, $this->client->findElements(WebDriverBy::xpath('//button[contains(text(), "Export fleet")]')));
            $this->assertCount(1, $this->client->findElements(WebDriverBy::id('filters_input_ship_name')));
            $this->assertCount(0, $this->client->findElements(WebDriverBy::id('filters_input_citizen_id')));
            $this->assertCount(1, $this->client->findElements(WebDriverBy::id('filters_input_ship_size')));
            $this->assertCount(1, $this->client->findElements(WebDriverBy::id('filters_input_ship_status')));

            // Private + Logout
            $this->client->request('GET', '/logout');
            $this->client->request('GET', '/organization-fleet/flk'); // orga private
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::className('alert'))) > 0
                    && strpos($driver->findElement(WebDriverBy::cssSelector('h4 a'))->getText(), 'FallKrom') !== false;
            });
            $this->assertContains('FallKrom', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertContains('Sorry, you have not the rights to access to FallKrom fleet page.', $this->client->findElement(WebDriverBy::className('alert-danger'))->getText());

            // view admin orga with private + public members
            $this->client->request('GET', '/logout');
            $this->login('def951eb-14ce-4fd7-8226-3d127e547f62'); // admin of pulsar42 orga
            $this->client->request('GET', '/organization-fleet/pulsar42');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return (int) $driver->executeScript('return document.querySelectorAll(".card-ship").length;') > 0
                    && count($driver->findElements(WebDriverBy::id('select-orga'))) === 1;
            });
            $this->assertContains('Pulsar42', $this->client->findElement(WebDriverBy::id('select-orga'))->getText());
            $this->assertContains('Pulsar42', $this->client->findElement(WebDriverBy::cssSelector('h4 a'))->getText());
            $this->assertContains('Admin', $this->client->findElement(WebDriverBy::cssSelector('p'))->getText());
            $this->assertCount(5, $this->client->findElements(WebDriverBy::cssSelector('.rank-icon-active')));

            $cardShips = $this->client->findElements(WebDriverBy::className('card-ship'));
            $this->assertCount(1, $cardShips);

            $cardShips[0]->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('.ship-family-detail-variant h4'))->getText(), 'Aurora MR') !== false
                    && strpos($driver->findElement(WebDriverBy::className('ship-family-detail-variant-ownerlist'))->getText(), 'hidden owner') !== false;
            });
            $detail = $this->client->findElement(WebDriverBy::id('ship-family-detail-0'));
            $variants = $detail->findElements(WebDriverBy::className('ship-family-detail-variant'));
            $this->assertCount(1, $variants);
            $this->assertSame('Aurora MR', $variants[0]->findElement(WebDriverBy::cssSelector('h4'))->getText());
            $this->assertContains('pulsar42_member2 : 1', $variants[0]->findElement(WebDriverBy::className('ship-family-detail-variant-ownerlist'))->getText());
            $this->assertContains('+ 1 hidden owner', $variants[0]->findElement(WebDriverBy::className('ship-family-detail-variant-ownerlist'))->getText());
        } catch (\Exception $e) {
            $this->client->takeScreenshot(sprintf('var/screenshots/error-%s.png', date('Y-m-d_H:i:s')));
            throw $e;
        }
    }

    /**
     * @group end2end
     * @group spa
     * @group funding
     */
    public function testFunding(): void
    {
        $this->login('d92e229e-e743-4583-905a-e02c57eacfe0');

        try {
            $this->client->request('GET', '/my-backings');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return stripos($driver->findElement(WebDriverBy::id('backings-table'))->getText(), 'You have no backings! 😢 Feel free to support us. 😎') === false;
            });
            $this->client->refreshCrawler();

            $this->assertSame("$\n51.33", $this->client->findElement(WebDriverBy::id('total-backed'))->getText());
            $this->assertSame('5133', $this->client->findElement(WebDriverBy::id('count-fm-coins'))->getText());

            $this->assertSame('Completed', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(1) td:nth-child(2)'))->getText());
            $this->assertSame('$51.33', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(1) td:nth-child(3)'))->getText());
            $this->assertSame('5133', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(1) td:nth-child(4)'))->getText());
            $this->assertSame('5133', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(1) td:nth-child(5)'))->getText());

            $this->assertSame('Created', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(2) td:nth-child(2)'))->getText());
            $this->assertSame('$1.00', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(2) td:nth-child(3)'))->getText());
            $this->assertSame('', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(2) td:nth-child(4)'))->getText());
            $this->assertSame('0', $this->client->findElement(WebDriverBy::cssSelector('#backings-table tbody tr:nth-child(2) td:nth-child(5)'))->getText());

            $this->assertCount(1, $this->client->findElements(WebDriverBy::cssSelector('.navbar-text .supporter-badge')));

            $this->login('14203774-91fa-4300-b464-bcda42697b10');
            $this->client->request('GET', '/supporters');
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('.spinner'))) === 0
                    && strpos($driver->findElement(WebDriverBy::cssSelector('#progress-amount'))->getText(), '63.12') !== false;
            });
            $this->client->refreshCrawler();

            $this->assertSame('$ 63.12 / $ 150.00', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#progress-amount'))->getText()));
            $this->assertSame('1. ionni $ 51.33', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-all-time .row:nth-child(1)'))->getText()));
            $this->assertSame('2. Anonymous $ 21.50', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-all-time .row:nth-child(2)'))->getText())); // fundings-1 /w supporterVisible == false
            $this->assertSame('20. 16_fundings $ 1.16', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-all-time .row:nth-child(20)'))->getText()));
            $this->assertSame('26. 10_fundings $ 1.10', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-all-time .row:nth-child(21)'))->getText()));

            $this->client->findElement(WebDriverBy::xpath('//button[contains(text(), "Support Us")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('#input-funding-amount'))) === 1
                    && $driver->findElement(WebDriverBy::cssSelector('#modal-funding button.close'))->isDisplayed();
            });
            $this->client->refreshCrawler();
            $this->client->wait(5, 100)->until(static function (WebDriver $driver) {
                if (count($driver->findElements(WebDriverBy::cssSelector('#modal-funding button.close'))) === 1) {
                    $driver->findElement(WebDriverBy::cssSelector('#modal-funding button.close'))->click();
                }

                return count($driver->findElements(WebDriverBy::cssSelector('#modal-funding'))) === 0;
            });

            $this->client->findElement(WebDriverBy::xpath('//label[contains(text(), "Organizations Tops")]'))->click();
            $this->client->wait(3, 100)->until(static function (WebDriver $driver) {
                return strpos($driver->findElement(WebDriverBy::cssSelector('#ladder-all-time'))->getText(), 'Les Gardiens') !== false;
            });
            $this->assertSame('1. Anonymous $ 58.33', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-all-time .row:nth-child(1)'))->getText())); // FallKrom /w supporterVisible==false
            $this->assertSame('2. Les Gardiens $ 0.90', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-all-time .row:nth-child(2)'))->getText()));

            $this->client->findElement(WebDriverBy::xpath('//a[contains(text(), "Monthly Top 20")]'))->click();
            $this->assertSame('1. Les Gardiens $ 0.90', preg_replace('~\s+~', ' ', $this->client->findElement(WebDriverBy::cssSelector('#ladder-monthly .row:nth-child(1)'))->getText()));
        } catch (\Exception $e) {
            $this->client->takeScreenshot(sprintf('var/screenshots/error-%s.png', date('Y-m-d_H:i:s')));
            throw $e;
        }
    }
}
