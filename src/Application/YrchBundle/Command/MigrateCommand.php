<?php

namespace Application\YrchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
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

    /*
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * This array contains key/value couples as <old user_id> => User
     *
     * @var array
     */
    protected $users = array ();

    /**
     * This array contains key/value couples as <old site_id> => Site
     *
     * @var array
     */
    protected $sites = array ();

    /**
     * This array contains key/value couples as <old cat_id> => Category
     *
     * @var array
     */
    protected $categories = array ();

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
        $groupRepo = $this->container->get('doctrine_user.repository.group');
        // Migrating admin
        $this->output->writeln('Migrating admin users');
        $adminGroup = $groupRepo->findOneByName('Admin');
        $query = $this->conn->executeQuery(
                'SELECT pu.USER_ID
                    FROM YRCH_PROFILUSER pu
                    INNER JOIN YRCH_PROFIL p
                    ON p.PROFIL_ID=pu.PROFIL_ID
                    WHERE p.PROFIL_ALIAS="admin"'
                );
        $admins = $query->fetchAll();
        foreach ($admins as $old_user) {
            $id_user = $old_user['USER_ID'];
            $this->migrateUser($id_user);
            $this->users[$id_user]->addGroup($adminGroup);
        }
        // Migrating moderators
        $this->output->writeln('Migrating moderator users');
        $moderatorGroup = $groupRepo->findOneByName('moderator');
        $query = $this->conn->executeQuery(
                'SELECT u.USER_ID
                    FROM YRCH_USER u
                    WHERE EXISTS (
                        SELECT pu.USER_ID
                        FROM YRCH_PROFILUSER pu
                        INNER JOIN YRCH_PROFIL p
                        ON p.PROFIL_ID = pu.PROFIL_ID
                        WHERE pu.USER_ID = u.USER_ID
                        AND p.PROFIL_ALIAS = "moderator"
                    )
                    AND NOT EXISTS (
                        SELECT pu.USER_ID
                        FROM YRCH_PROFILUSER pu
                        INNER JOIN YRCH_PROFIL p
                        ON p.PROFIL_ID = pu.PROFIL_ID
                        WHERE pu.USER_ID = u.USER_ID
                        AND p.PROFIL_ALIAS = "admin"
                    )'
                );
        $moderators = $query->fetchAll();
        foreach ($moderators as $old_user) {
            $id_user = $old_user['USER_ID'];
            $this->migrateUser($id_user);
            $this->users[$id_user]->addGroup($moderatorGroup);
        }
        // Migrating categories
        $this->output->writeln('Migrating categories');
        $query = $this->conn->executeQuery(
                'SELECT c.CAT_ID
                    FROM YRCH_CAT c'
                );
        $categories = $query->fetchAll();
        foreach ($categories as $old_category) {
            $id_category = $old_category['CAT_ID'];
            $this->migrateCategory($id_category);// This does not work because of a problem on DEB
        }
        $this->em->flush();
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
        $user->setEmail($old_user['USER_EMAIL']);
        $user->setPassword($old_user['USER_PASS']);
        switch ($old_user['USER_STATUS']) {
            case 'pending':
                $user->setIsActive(false);
                $user->unlock();
                break;
            case 'ok':
                $user->setIsActive(true);
                $user->unlock();
            default:
                // dead or locked
                $user->setIsActive(true);
                $user->lock();
                break;
        }
        $old_configs = $this->conn->fetchAssoc('SELECT * FROM YRCH_USERCONF WHERE USER_ID=?', array($id_user));
        $old_config = array();
        foreach ($old_config as $row) {
            $old_config[$row['USERCONF_KEY']] = $row['USERCONF_VALUE'];
        }
        $default_config = array(
            'DEF_COUNTPERPAGE' => 25,
            'DEF_LANG' => 'fr',
            'DEF_LINKOUT' => '_blank',
            'DEF_MAILDIRECT' => 1,
            'DEF_REVIEWWARN' => 1,
            'DEF_SITEWARN' => 1,
            'DEF_THEME' => 'yrch');
        $config = array_merge($default_config, $old_config);
        if ($config['DEF_THEME'] == 'yrch'){
            $config['DEF_THEME'] = 'default';
        }
        $user->setSiteNotifications((bool) $config['DEF_SITEWARN']);
        $user->setReviewNotifications((bool) $config['DEF_REVIEWWARN']);
        $user->setTheme($config['DEF_THEME']);
        $user->setPreferedLocale($config['DEF_LANG']);
        $user->setSitesPerPage($config['DEF_COUNTPERPAGE']);
        $user->setReviewsPerPage($config['DEF_COUNTPERPAGE']);
        $user->setOutlink($config['DEF_LINKOUT']);
        $user->setContactAllowed((bool) $config['DEF_MAILDIRECT']);
        $this->em->persist($user);
        $this->users[$id_user] = $user;
        if ($this->output->getVerbosity() == Output::VERBOSITY_VERBOSE){
            $this->output->writeln(sprintf('Migrating <comment>%s</comment> user',$user->getNick()));
        }
    }

    public function migrateSite($id_site)
    {
        if (isset ($this->sites[$id_site])){
            return;
        }
        $old_site = $this->conn->fetchAssoc('SELECT * FROM YRCH_SITE WHERE SITE_ID=?', array($id_site));
        $this->migrateUser($old_site['USER_ID']);
        $site = new Site();
        $site->setUrl($old_site['SITE_URL']);
        $site->setName($old_site['SITE_TITLE']);
        $site->setSuperOwner($this->users[$old_site['USER_ID']]);
        $site->setLeech((bool) $old_site['SITE_LEECH']);
        $site->setCreatedAt(new \DateTime($old_site['SITE_ADDED']));
        $site->setUpdatedAt(new \DateTime($old_site['SITE_UPDATED']));
        $site->setNotes($old_site['SITE_BLOCNOTE']);
        $site->setStatus($old_site['SITE_STATUS']);
        $old_languages = $this->conn->executeQuery(
                'SELECT l.LANG_CODE AS code
                    FROM YRCH_LANG l
                    INNER JOIN YRCH_SITELANG sl
                    ON sl.LANG_ID=l.LANG_ID
                    WHERE sl.SITE_ID=?',
                array($id_site))
                ->fetchAll();
        $languages = array();
        foreach ($old_languages as $row){
            $languages[] = $row['code'];
        }
        $site->setLanguages($languages);
        $old_countries = $this->conn->executeQuery(
                'SELECT c.COUNTRY_CODE AS code
                    FROM YRCH_COUNTRY c
                    INNER JOIN YRCH_SITECOUNTRY sc
                    ON sc.COUNTRY_ID=c.COUNTRY_ID
                    WHERE sc.SITE_ID=?',
                array($id_site))
                ->fetchAll();
        $countries = array();
        foreach ($old_countries as $row){
            $countries[] = $row['code'];
        }
        $site->setCountries($countries);
        if ($old_site['SITE_SELECTION']){
            $site->addToSelection();
        }
        $description = unserialize($old_site['SITE_DESC']);
        foreach ($description as $locale => $desc){
            $site->setTranslatableLocale($locale);
            $site->setDescription($desc);
            $this->em->persist($site);
            $this->em->flush();
        }
        $this->sites[$id_site] = $site;
        if ($this->output->getVerbosity() == Output::VERBOSITY_VERBOSE){
            $this->output->writeln(sprintf('Migrating <comment>%s</comment> site',$site->getName()));
        }
    }

    public function migrateCategory($id_category)
    {
        if (isset ($this->categories[$id_category])){
            return;
        }
        $old_category = $this->conn->fetchAssoc('SELECT * FROM YRCH_CAT WHERE CAT_ID=?', array($id_category));
        $category = new Category();
        $parent = $this->conn->fetchAssoc('SELECT * FROM YRCH_CATCAT WHERE CAT_CHILD=? AND LNK_MASTER=? LIMIT 1', array($id_category, 1));
        if ($parent['CAT_ID'] != $id_category){
            // The root node has no parent
            $this->migrateCategory($parent['CAT_ID']);
            $category->setParent($this->categories[$parent['CAT_ID']]);
        }
        $description = unserialize($old_category['CAT_DESC']);
        $old_titles = $this->conn->fetchAll('SELECT * FROM YRCH_CATLANG WHERE CAT_ID=?', array($id_category));
        foreach ($old_titles as $row) {
            $locale = $row['LANG_CODE'];
            $desc = ($description[$locale])?:'';
            $category->setTranslatableLocale($locale);
            $category->setName($row['CAT_TITLE']);
            $category->setDescription($desc);
            $this->em->persist($category);
            $this->em->flush();
        }
        $this->categories[$id_category] = $category;
        if ($this->output->getVerbosity() == Output::VERBOSITY_VERBOSE){
            $this->output->writeln(sprintf('Migrating <comment>%s</comment> category',$category->getName()));
        }
    }
}
