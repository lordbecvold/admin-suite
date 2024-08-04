<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Log;
use App\Manager\LogManager;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

/**
 * Class LogFixtures
 *
 * The testing log data fixtures
 *
 * @package App\DataFixtures
 */
class LogFixtures extends Fixture
{
    /**
     * Load the log fixtures
     *
     * @param ObjectManager $manager The entity manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        // create 100 logs
        for ($i = 0; $i < 100; $i++) {
            $log = new Log();

            // set the log properties
            $log->setName($faker->word)
                ->setMessage($faker->sentence)
                ->setTime($faker->dateTimeThisYear)
                ->setUserAgent($faker->userAgent)
                ->setIpAdderss($faker->ipv4)
                ->setStatus('UNREADED')
                ->setLevel(LogManager::LEVEL_CRITICAL)
                ->setUserId(1);

            // save the log
            $manager->persist($log);
        }

        // save the logs
        $manager->flush();
    }
}
