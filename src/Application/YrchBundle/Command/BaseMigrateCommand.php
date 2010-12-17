<?php

namespace Application\YrchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\DriverManager;
use Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;

/**
 * BaseMigrateCommand.
 */
abstract class BaseMigrateCommand extends Command
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var Doctrine\DBAL\Connection
     */
    protected $conn = null;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('dbname', InputArgument::REQUIRED, 'The old database name'),
                new InputArgument('user', InputArgument::REQUIRED, 'The username to use'),
                new InputArgument('password', InputArgument::REQUIRED, 'The password to use'),
            ));
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dbal_config = array (
            'driver' => 'pdo_mysql',
            'user' => $input->getArgument('user'),
            'password' => $input->getArgument('password'),
            'dbname' => $input->getArgument('dbname'),
            'host' => 'localhost'
        );
        $this->conn = DriverManager::getConnection($dbal_config);
        $this->conn->connect(); // an exception is raised if the connection fails
        $this->conn->executeQuery('SET NAMES UTF8');
        // Remove the Timestampable listener to migrate creation and update dates
        $this->em = $this->container->get('doctrine.orm.entity_manager');
        DoctrineExtensionsBundle::removeTimestampableListener($this->em);
    }
}
