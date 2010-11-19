<?php

namespace Application\YrchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Doctrine\DBAL\DriverManager;
use Application\YrchBundle\Entity\User;
use Application\YrchBundle\Entity\Site;
use Application\YrchBundle\Entity\Review;
use Application\YrchBundle\Entity\Category;
use Application\YrchBundle\Entity\SiteTemp;
use Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;

/**
 * MigrateCommand.
 */
class MigrateCommand extends Command
{
    /**
     * @var Doctrine\DBAL\Connection
     */
    protected $conn;
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;
    protected $users = array ();
    protected $sites = array ();
    protected $categories = array ();
    /*
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('yrch:migrate')
            ->setDescription('Migrate the database from v1')
            ->setDefinition(array(
                new InputArgument('dbname', InputArgument::REQUIRED, 'The old database name'),
                new InputArgument('user', InputArgument::REQUIRED, 'The username to use'),
                new InputArgument('password', InputArgument::REQUIRED, 'The password to use'),
            ))
            ->setHelp(<<<EOT
The <info>yrch:migrate</info> command migrates the database from Yrch! 1.0:

  <info>php app/console yrch:test dbname dbuser dbpassword</info>

EOT
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $string_input = new StringInput('yrch:generate:groups');
        $application = new Application($this->application->getKernel());
        $application->setAutoExit(false);
        $application->run($string_input, $this->output);

        $dbal_config = array (
            'driver' => 'pdo_mysql',
            'user' => $input->getArgument('user'),
            'password' => $input->getArgument('password'),
            'dbname' => $input->getArgument('dbname'),
            'host' => 'localhost'
        );
        $this->conn = DriverManager::getConnection($dbal_config);
        $this->conn->connect(); // an exception is raised if the connection fails
        // Remove the Timestampable listener to migrate creation and update dates
        $this->em = $this->container->get('doctrine.orm.entity_manager');
        DoctrineExtensionsBundle::removeTimestampableListener($this->em);
        $this->output->writeln('connected');
        $query = $this->conn->executeQuery(
                'SELECT pu.USER_ID as
                    FROM YRCH_PROFILUSER pu
                    ON pu.USER_ID=u.USER_ID
                    INNER JOIN YRCH_PROFIL p
                    ON p.PROFIL_ID=pu.PROFIL_ID
                    WHERE p.PROFIL_ALIAS="admin"'
                );
        $users = $query->fetchAll();
        foreach ($users as $id_user) {
            $this->migrateUser($id_user);
        }
    }

    protected function migrateUser($id_user)
    {
        if (isset ($this->users[$id_user])){
            return;
        }
        $old_user = $this->conn->fetchAssoc('SELECT * FROM YRCH_USER WHERE USER_ID=?', array($id_user));
        $user = new User();
        $user->setUsername($old_user['USER_NAME']);
        $user->setNick($old_user['USER_NICK']);
    }
}
