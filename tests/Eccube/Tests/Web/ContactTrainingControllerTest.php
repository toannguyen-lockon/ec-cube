<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Tests\Web;

class ContactTrainingControllerTest extends AbstractWebTestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->initializeMailCatcher();
    }

    public function tearDown()
    {
        $this->cleanUpMailCatcherMessages();
        parent::tearDown();
    }

    protected function createFormData()
    {
        $faker = $this->getFaker();
        $tel = explode('-', $faker->phoneNumber);

        $email = $faker->safeEmail;

        $form = array(
            'name' => array(
                'name01' => $faker->lastName,
                'name02' => $faker->firstName,
            ),
            'kana' => array(
                'kana01' => $faker->lastKanaName ,
                'kana02' => $faker->firstKanaName,
            ),
            'zip' => array(
                'zip01' => $faker->postcode1(),
                'zip02' => $faker->postcode2(),
            ),
            'address' => array(
                'pref' => '5',
                'addr01' => $faker->city,
                'addr02' => $faker->streetAddress,
            ),
            'tel' => array(
                'tel01' => $tel[0],
                'tel02' => $tel[1],
                'tel03' => $tel[2],
            ),
            'email' => $email,
            'contents' => $faker->text(),
            '_token' => 'dummy'
        );

        return $form;
    }

    public function testRoutingIndex()
    {
        $client = $this->createClient();
        $client->request('GET', $this->app->path('contact_training'));
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    /**
     * If select any item other than the dropdown list
     */
    public function testAddress_OutOfList()
    {
        $client = $this->createClient();

        $formData = $this->createFormData();
        $formData['address']['pref'] = 99999;

        $crawler = $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $formData,
                'mode' => 'confirm')
        );
        $this->expected = 'Contact input';
        $this->actual = $crawler->filter('title')->text();
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);
    }

    public function testConfirm()
    {
        $client = $this->createClient();

        $crawler = $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $this->createFormData(),
                  'mode' => 'confirm')
        );
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->expected = 'Contact confirmation';
        $this->actual = $crawler->filter('title')->text();
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);
    }

    public function testComplete()
    {
        $client = $this->createClient();
        $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $this->createFormData(),
                  'mode' => 'complete')
        );

        $this->assertTrue($client->getResponse()->isRedirect($this->app->url('contact_training_complete')));

        $BaseInfo = $this->app['eccube.repository.base_info']->get();
        $Messages = $this->getMailCatcherMessages();
        $Message = $this->getMailCatcherMessage($Messages[0]->id);

        $this->expected = '[' . $BaseInfo->getShopName() . '] We received your inquiries.';
        $this->actual = $Message->subject;
        $this->verify();

    }

    /**
     * If the name field is set to null value from the confirmation page
     */
    public function testConfirm_NameValMistake()
    {
        $client = $this->createClient();

        $crawler = $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $this->createFormData(),
                'mode' => 'confirm')
        );
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->expected = 'Contact confirmation';
        $this->actual = $crawler->filter('title')->text();
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);

        $form = $crawler->selectButton('Send')->form();

        $form->setValues(
            array('contact_training_form' =>
                array('name' =>
                    array(
                        'name01' => '',
                        'name02' => ''
                    )
                )
            )
        );

        $crawler_second = $client->submit($form);
        $this->assertFalse($client->getResponse()->isRedirect($this->app->url('contact_training_complete')));

        $this->actual = $crawler_second->filter('title')->text();
        $this->expected = 'Contact input';
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);
    }

    /**
     * If the name (kana) field is set to wrong value from the confirmation page
     */
    public function testConfirm_NameKanaValWrong()
    {
        $client = $this->createClient();

        $crawler = $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $this->createFormData(),
                'mode' => 'confirm')
        );
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->expected = 'Contact confirmation';
        $this->actual = $crawler->filter('title')->text();
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);

        $form = $crawler->selectButton('Send')->form();

        $form->setValues(
            array('contact_training_form' =>
                array('kana' =>
                    array(
                        'kana01' => 'AA',
                        'kana02' => 'BB'
                    )
                )
            )
        );

        $crawler_second = $client->submit($form);
        $this->assertFalse($client->getResponse()->isRedirect($this->app->url('contact_training_complete')));

        $this->actual = $crawler_second->filter('title')->text();
        $this->expected = 'Contact input';
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);
    }

    /**
     * Wrong format email from confirm page
     */
    public function testConfirm_EmailValWrong()
    {
        $client = $this->createClient();

        $crawler = $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $this->createFormData(),
                'mode' => 'confirm')
        );
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->expected = 'Contact confirmation';
        $this->actual = $crawler->filter('title')->text();
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);

        $form = $crawler->selectButton('Send')->form();

        $form->setValues(
            array('contact_training_form' =>
                array('email' => 'toannguyen.gmail.com')
            )
        );

        $crawler_second = $client->submit($form);
        $this->assertFalse($client->getResponse()->isRedirect($this->app->url('contact_training_complete')));

        $this->actual = $crawler_second->filter('title')->text();
        $this->expected = 'Contact input';
        $this->assertRegexp('/'.preg_quote($this->expected).'$/', $this->actual);
    }

    /**
     * 必須項目のみのテストケース
     * @link https://github.com/EC-CUBE/ec-cube/issues/1314
     */
    public function testCompleteWithRequired()
    {
        $client = $this->createClient();

        $formData = $this->createFormData();
        $formData['kana']['kana01'] = null;
        $formData['kana']['kana02'] = null;
        $formData['zip']['zip01'] = null;
        $formData['zip']['zip02'] = null;
        $formData['address']['pref'] = null;
        $formData['address']['addr01'] = null;
        $formData['address']['addr02'] = null;
        $formData['tel']['tel01'] = null;
        $formData['tel']['tel02'] = null;
        $formData['tel']['tel03'] = null;

        $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $formData,
                  'mode' => 'complete')
        );
        $this->assertTrue($client->getResponse()->isRedirect($this->app->url('contact_training_complete')));

        $BaseInfo = $this->app['eccube.repository.base_info']->get();
        $Messages = $this->getMailCatcherMessages();
        $Message = $this->getMailCatcherMessage($Messages[0]->id);

        $this->expected = '[' . $BaseInfo->getShopName() . '] We received your inquiries.';
        $this->actual = $Message->subject;
        $this->verify();
    }

    public function testCompleteWithLogin()
    {
        $client = $this->createClient();
        $this->logIn();
        $client->request(
            'POST',
            $this->app->path('contact_training'),
            array('contact_training_form' => $this->createFormData(),
                  'mode' => 'complete')
        );
        $this->assertTrue($client->getResponse()->isRedirect($this->app->url('contact_training_complete')));

        $BaseInfo = $this->app['eccube.repository.base_info']->get();
        $Messages = $this->getMailCatcherMessages();
        $Message = $this->getMailCatcherMessage($Messages[0]->id);

        $this->expected = '[' . $BaseInfo->getShopName() . '] We received your inquiries.';
        $this->actual = $Message->subject;
        $this->verify();

    }

    public function testRoutingComplete()
    {
        $client = $this->createClient();
        $client->request('GET', $this->app->path('contact_training_complete'));
        $this->assertTrue($client->getResponse()->isSuccessful());
    }
}
