<?php

namespace Application\YrchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Application\YrchBundle\Entity\User;

/**
 * InternalMigrateUsersCommand
 */
class InternalMigrateUsersCommand extends BaseMigrateCommand
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
     * @see Command
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('yrch:internal:migrate_users')
            ->setDescription('Internal command: Migrate the users from v1')
            ->addArgument('special_user', InputArgument::REQUIRED, 'The username of the special_user in old database')
            ->addOption('force', null, InputOption::VALUE_NONE)
            ->setHelp(<<<EOT
The <info>yrch:internal:migrate</info> command migrates the users from Yrch! 1.0.
This is an internal command used by <info>yrch:migrate</info>

EOT
        );
    }

    /**
     * @see Command
     * @todo Find a solution to get a database connection in the subprocess
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('force') !== true) {
            throw new \LogicException('This is an internal command. You should not use it directly.');
        }
        $this->output = $output;
        parent::execute($input, $this->output);
        $groupRepo = $this->container->get('doctrine_user.repository.group');
        $old_special_user = $this->conn->fetchAssoc(
                'SELECT USER_ID
                    FROM YRCH_USER
                    WHERE USER_NAME=?',
                array ($input->getArgument('special_user'))
                );
        $special_user = $this->container->get('doctrine_user.repository.user')->findOnebyUsername($this->container->getParameter('yrch.special_user.username'));
        $this->users[$old_special_user['USER_ID']] = $special_user;
        // Migrating admin
        $this->output->writeln('Migrating admin users');
        $adminGroup = $groupRepo->findOneByName('Admin');
        $query = $this->conn->executeQuery(
                'SELECT pu.USER_ID
                    FROM YRCH_PROFILUSER pu
                    INNER JOIN YRCH_PROFIL p
                    ON p.PROFIL_ID=pu.PROFIL_ID
                    WHERE p.PROFIL_ALIAS="admin"
                    AND pu.USER_ID<>?',
                array ($old_special_user['USER_ID'])
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
        // Migrating other users
        $this->output->writeln('Migrating site and review owners');
        $query = $this->conn->executeQuery(
                'SELECT u.USER_ID
                    FROM YRCH_USER u
                    WHERE (
                        EXISTS (
                            SELECT r.USER_ID
                            FROM YRCH_REVIEWS r
                            WHERE r.USER_ID = u.USER_ID
                        )
                        OR EXISTS (
                            SELECT s.USER_ID
                            FROM YRCH_SITE s
                            WHERE s.USER_ID = u.USER_ID
                        )
                    )
                    AND NOT EXISTS (
                        SELECT pu.USER_ID
                        FROM YRCH_PROFILUSER pu
                        INNER JOIN YRCH_PROFIL p
                        ON p.PROFIL_ID = pu.PROFIL_ID
                        WHERE pu.USER_ID = u.USER_ID
                        AND (
                            p.PROFIL_ALIAS = "admin"
                            OR p.PROFIL_ALIAS = "moderator"
                        )
                    )'
                );
        $users = $query->fetchAll();
        foreach ($users as $old_user) {
            $id_user = $old_user['USER_ID'];
            $this->migrateUser($id_user);
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
}
