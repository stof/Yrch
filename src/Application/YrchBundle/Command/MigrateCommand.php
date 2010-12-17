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
use Application\YrchBundle\Entity\User;
use Application\YrchBundle\Entity\Site;
use Application\YrchBundle\Entity\Review;
use Application\YrchBundle\Entity\Category;
use Application\YrchBundle\Entity\SiteTemp;
use Symfony\Component\Process\Process;

/**
 * MigrateCommand.
 */
class MigrateCommand extends BaseMigrateCommand
{
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
        parent::configure();

        $this
            ->setName('yrch:migrate')
            ->setDescription('Migrate the database from v1')
            ->addArgument('special_user', InputArgument::REQUIRED, 'The username of the special_user in old database')
            ->setHelp(<<<EOT
The <info>yrch:migrate</info> command migrates the database from Yrch! 1.0:

  <info>php app/console yrch:test dbname dbuser dbpassword special_user</info>

EOT
        );
    }

    /**
     * @see Command
     * @todo Use PhpProcess when it will work
     * @todo Find a way to get the PhpBinary instead of hardcoding it
     * @todo Migrate sites and categories
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $string_input = new StringInput('yrch:populate');
        $application = new Application($this->application->getKernel());
        $application->setAutoExit(false);
        $application->run($string_input, $this->output);
        
        // Migrating users
        $console_file = ($this->container->getParameter('kernel.environment') == 'dev')?'console':'console_test';
        $script = 'C:\\wamp\\bin\\php\\php5.3.3\\php.exe ';
        $script .= 'app/'.$console_file.' yrch:internal:migrate_users --force ';
        if (true === $input->getOption('verbose')){
            $script .= ' --verbose ';
        } elseif (true === $input->getOption('quiet')){
            $script .= ' --quiet ';
        }
        $script .= $input->getArgument('dbname').' ';
        $script .= $input->getArgument('user').' ';
        $script .= $input->getArgument('password').' ';
        $script .= $input->getArgument('special_user');
        $process = new Process($script);
        $process->run($callback);
        $this->output->writeln($process->getOutput());
        $this->output->writeln($process->getErrorOutput());
        /*
        // Migrating categories
        $this->output->writeln('Migrating categories');
        $query = $this->conn->executeQuery(
                'SELECT c.CAT_ID
                    FROM YRCH_CAT c'
                );
        $categories = $query->fetchAll();
        foreach ($categories as $old_category) {
            $id_category = $old_category['CAT_ID'];
            $this->migrateCategory($id_category);
        }
        // Migrating sites
        $this->output->writeln('Migrating sites');
        $query = $this->conn->executeQuery(
                'SELECT s.SITE_ID
                    FROM YRCH_SITE s'
                );
        $sites = $query->fetchAll();
        foreach ($sites as $old_site) {
            $id_site = $old_site['SITE_ID'];
            $this->migrateSite($id_site);
        }
        $this->em->flush();*/
    }
/*
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
        $old_categories = $this->conn->fetchAll('SELECT * FROM YRCH_CATSITE WHERE SITE_ID=?', array($id_site));
        foreach ($old_categories as $row) {
            $this->migrateCategory($row['CAT_ID']); // Normally all categories are still migrated
            $site->addCategory($this->categories[$row['CAT_ID']]);
        }
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
    }*/
}
