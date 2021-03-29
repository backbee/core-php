<?php

namespace BackBee\Command;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\NestedNode\KeyWord;
use BackBee\NestedNode\Page;
use BackBee\Security\SecurityContext;
use BackBee\Security\User;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use BackBeeCloud\Elasticsearch\IndexElasticsearchTrait;
use BackBeePlanet\GlobalSettings;
use BackBeePlanet\Standalone\Application;
use BackBeePlanet\Standalone\ManageUserRightsTrait;
use BackBeePlanet\Standalone\StandaloneHelper;
use BackBeePlanet\Standalone\UpdateDatabaseSchemaTrait;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\EntityManager;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Class InstallCommand
 *
 * @package BackBee\Command
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class InstallCommand extends AbstractCommand
{
    use ManageUserRightsTrait;
    use UpdateDatabaseSchemaTrait;
    use IndexElasticsearchTrait;

    public const CONFIG_DIST_YAML = [StandaloneHelper::class, 'distDir'];
    public const CONFIG_REGULAR_YAML = [StandaloneHelper::class, 'configDir'];

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var GlobalSettings
     */
    protected $settings;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var bool
     */
    protected $isFirstInstall = false;

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('backbee:install')
            ->setDescription('Installs everything to get BackBee Standalone ready for use.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->input = $input;
        $this->output = $output;
        $this->settings = new GlobalSettings();
        $this->io = new SymfonyStyle($input, $output);

        $this->io->section('BackBee Standalone installer is now processing');
        $this->io->text(
            sprintf(
                'Do not forget to fill %s with your environment information.',
                StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'global_settings.yml'
            )
        );

        if (false === ($appName = $this->settings->appname())) {
            $appName = basename(StandaloneHelper::rootDir());
            $this->io->error(sprintf(
                '<c2>  - Cannot find application\'s name in global settings, "%s" will be used.</c2>',
                $appName
            ));
        }

        $this->io->section('Build repository');
        $this->buildRepository($appName);

        $this->io->section('Create database');
        $this->createDatabase();

        Application::setRepositoryDir(StandaloneHelper::repositoryDir());
        $app = new Application();

        $this->io->section('Update database schema');
        $this->updateDatabaseSchema($app);
        $this->io->text('Database\'s schema has been updated.');

        $this->io->section('Create suoder');
        $this->createSuoder($app->getSecurityContext(), $app->getEntityManager());

        $this->io->section('Create site');
        $this->createSite($app->getEntityManager(), $appName);

        $this->io->section('Create clean layout');
        $this->createCleanLayout($app->getEntityManager());

        $this->io->section('Create root page');
        $this->createRootPage($app->getEntityManager());

        $this->io->section('Create root keyword');
        $this->createRootKeyword($app->getEntityManager());

        $this->io->section('Install user rights');
        $this->installUserRights($app);
        $this->io->text('User right successfully installed.');

        $this->io->section('Index elasticsearch');
        $this->indexElasticsearch($app, $this->io);
        $this->io->text('Elasticsearch has been initialized.');

        $this->cleanup();

        $this->io->success('Installation of BackBee Standalone is now done.');

        return 0;
    }

    /**
     * Build repository.
     *
     * @param $appName
     */
    private function buildRepository($appName): void
    {
        // Config directory
        $configDir = StandaloneHelper::configDir();

        // Data directories
        $dataDir = StandaloneHelper::repositoryDir() . DIRECTORY_SEPARATOR . 'Data';
        StandaloneHelper::mkdirOnce($dataDir);
        StandaloneHelper::mkdirOnce($dataDir . DIRECTORY_SEPARATOR . 'Media');
        StandaloneHelper::mkdirOnce($dataDir . DIRECTORY_SEPARATOR . 'Storage');
        StandaloneHelper::mkdirOnce($dataDir . DIRECTORY_SEPARATOR . 'Tmp');

        $this->io->text('Data\'s folders are ready.');

        // bootstrap.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'bootstrap.yml';
        if (file_exists($filepath)) {
            $this->io->note(sprintf('%s already exists.', $filepath));
        } else {
            file_put_contents($filepath, Yaml::dump(
                [
                'debug'     => false,
                'container' => [
                    'dump_directory' => StandaloneHelper::cacheDir(),
                    'autogenerate'   => true,
                ],
            ],
                20
            ));

            $this->io->text(sprintf('%s has been generated.', $filepath));
        }

        // build config.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'config.yml';
        if (file_exists($filepath)) {
            $this->io->note(sprintf('%s already exists.', $filepath));
        } else {
            file_put_contents($filepath, Yaml::dump($this->parseYaml('config.yml.dist'), 20));
            $this->io->text(sprintf('%s has been generated.', $filepath));
        }

        // build doctrine.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'doctrine.yml';
        if (file_exists($filepath)) {
            $this->io->note(sprintf('%s already exists.', $filepath));
        } else {
            $config = array_merge($this->parseYaml('doctrine.yml.dist')['dbal'], $this->settings->database());

            if (!isset($config['dbname']) || false === $config['dbname']) {
                $config['dbname'] = $appName;
                $this->io->warning(sprintf('Cannot find database name, "%s" will be used.', $appName));
            }

            file_put_contents($filepath, Yaml::dump(['dbal' => $config], 20));
            $this->io->text(sprintf('%s has been generated.', $filepath));
        }

        // build security.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'security.yml';
        if (file_exists($filepath)) {
            $this->io->note(sprintf('%s already exists.', $filepath));
        } else {
            file_put_contents($filepath, Yaml::dump($this->parseYaml('security.yml.dist'), 20));
            $this->io->text(sprintf('%s has been generated.', $filepath));
        }

        // services.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'services.yml';
        if (file_exists($filepath)) {
            $this->io->note(sprintf('%s already exists.', $filepath));
        } else {
            file_put_contents($filepath, Yaml::dump(array_merge(
                $this->parseYaml('services.yml.dist'),
                [
                    'parameters' => [
                        'bbapp.cache.dir' => StandaloneHelper::cacheDir(),
                        'bbapp.data.dir'  => $dataDir,
                        'bbapp.log.dir'   => StandaloneHelper::logDir(),
                        'secret_key'      => md5($appName),
                    ],
                ]
            ), 20));

            $this->io->text(sprintf('%s has been generated.', $filepath));
        }
    }

    /**
     * Create database.
     *
     * @return int|void
     */
    protected function createDatabase()
    {
        $config = $this->parseYaml('doctrine.yml', self::CONFIG_REGULAR_YAML)['dbal'];
        $dbname = $config['dbname'];
        unset($config['dbname']);
        $tmpConn = null;

        try {
            $tmpConn = DriverManager::getConnection($config);
            if (!($tmpConn->getDriver()->getDatabasePlatform() instanceof MySqlPlatform)) {
                $this->io->error(
                    'BackBee Standalone only support MySQL database, installation aborted.'
                );

                throw new Exception();
            }
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );

            unlink(StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'doctrine.yml');

            return 1;
        }

        if (in_array($dbname, $tmpConn->getSchemaManager()->listDatabases(), true)) {
            $this->io->note(sprintf('Database "%s" already exists.', $dbname));
            return;
        }

        $this->isFirstInstall = true;
        $sql = sprintf('CREATE DATABASE `%s`', $dbname);

        if (isset($config['collation'], $config['charset'])) {
            $sql = sprintf('%s CHARACTER SET %s COLLATE %s', $sql, $config['charset'], $config['collation']);
        }

        try {
            $tmpConn->executeUpdate($sql);
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $this->io->text(sprintf('"%s" has been created.', $dbname));
    }

    /**
     * Create suoder.
     *
     * @param SecurityContext $securityContext
     * @param EntityManager   $entityMgr
     */
    private function createSuoder(SecurityContext $securityContext, EntityManager $entityMgr): void
    {
        if (null !== $entityMgr->getRepository(User::class)->findOneBy([])) {
            $this->io->note('Super user already exists.');
            return;
        }

        $this->io->text('Admin user creation');
        $this->io->newLine();

        $email = $this->askFor('Email (it will also be the username): ', 'Email is required');
        $password = $this->askFor('Password: ', 'Password is required', null, true);
        $encoder = $securityContext->getEncoderFactory()->getEncoder(User::class);

        $user = new User(
            $email,
            $encoder->encodePassword($password, ''),
            'SuperAdmin',
            'SuperAdmin'
        );

        try {
            $user
                ->setApiKeyEnabled(true)
                ->setActivated(true)
                ->setEmail($email)
                ->generateRandomApiKey()
            ;

            $entityMgr->persist($user);
            $entityMgr->flush($user);
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $config = $this->parseYaml('security.yml', self::CONFIG_REGULAR_YAML);
        $config['sudoers'][$user->getLogin()] = (int) $user->getId();

        file_put_contents(
            StandaloneHelper::configDir() . DIRECTORY_SEPARATOR . 'security.yml',
            Yaml::dump($config, 3, 2)
        );

        $this->io->text(sprintf('Super admin "%s" has been created.', $email));
    }

    /**
     * Creates a site if it does not exist.
     *
     * @param EntityManager $entityManager
     * @param string        $appName
     */
    private function createSite(EntityManager $entityManager, string $appName): void
    {
        if (null !== $entityManager->getRepository(Site::class)->findOneBy([])) {
            $this->io->note('Site already exists.');
            return;
        }

        $this->io->text('Site creation');
        $this->io->newLine();

        $domain = $this->askFor('Site domain: ', 'Site domain is required');

        $site = new Site(md5($appName));
        $site->setLabel($appName);
        $site->setServerName($domain);

        try {
            $entityManager->persist($site);
            $entityManager->flush($site);
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $this->io->text(sprintf('Site %s (%s) has been created.', $appName, $domain));
    }

    /**
     * Create clean layout.
     *
     * @param EntityManager $entityManager
     */
    private function createCleanLayout(EntityManager $entityManager): void
    {
        if (null !== $entityManager->getRepository(Layout::class)->findOneBy([])) {
            $this->io->note('Clean layout already exists.</c1>');
            return;
        }

        $layout = new Layout(md5('clean-layout'));
        $layout
            ->setData('{"templateLayouts":[{"title":"Main zone","layoutSize":{"height":300,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1332943638139_1","layoutClass":"bb4ResizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":5,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","position":"none","height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":true,"accept":[],"maxentry":"0","defaultClassContent":null}]}')
            ->setLabel('Clean layout')
            ->setPath('CleanLayout.twig')
            ->setPicPath($layout->getUid() . '.png')
        ;

        try {
            $entityManager->persist($layout);
            $entityManager->flush($layout);
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }


        $this->io->text('Clean layout has been created.');
    }

    /**
     * Create root page.
     *
     * @param EntityManager $entityManager
     */
    private function createRootPage(EntityManager $entityManager): void
    {
        $uid = md5('root-page');

        try {
            if (null !== $entityManager->find(Page::class, $uid)) {
                $this->io->note('Home page already exists.');
                return;
            }
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $page = new Page($uid);

        try {
            $page
                ->setTitle('Home')
                ->setLayout($entityManager->getRepository(Layout::class)->findOneBy([]))
                ->setSite($entityManager->getRepository(Site::class)->findOneBy([]))
                ->setUrl('/')
                ->setState(Page::STATE_ONLINE)
                ->setPosition(1)
            ;
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        try {
            $page
                ->getContentSet()
                ->setRevision(1)
                ->setState(AbstractClassContent::STATE_NORMAL)
                ->first()
                ->setRevision(1)
                ->setState(AbstractClassContent::STATE_NORMAL)
            ;

            $entityManager->persist($page);
            $entityManager->flush($page);
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $this->io->text('Home page has been created.');
    }

    /**
     * Create root keyword.
     *
     * @param EntityManager $entityManager
     */
    private function createRootKeyword(EntityManager $entityManager): void
    {
        $uid = md5('root');

        try {
            if (null !== $entityManager->find(KeyWord::class, $uid)) {
                $this->io->note('Root keyword already exists.');
                return;
            }
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $keyword = new KeyWord($uid);
        $keyword->setRoot($keyword);
        $keyword->setKeyWord('root');

        try {
            $entityManager->persist($keyword);
            $entityManager->flush($keyword);
        } catch (Exception $exception) {
            $this->getLogger()->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $this->io->text('Root keyword has been created.');
    }

    /**
     * Parse yaml.
     *
     * @param string         $filename
     * @param array|string[] $srcCb
     *
     * @return mixed
     */
    private function parseYaml(string $filename, array $srcCb = self::CONFIG_DIST_YAML)
    {
        return Yaml::parse(file_get_contents(call_user_func_array($srcCb, []) . DIRECTORY_SEPARATOR . $filename));
    }

    /**
     * Ask for.
     *
     * @param string $message
     * @param string $messageValidator
     * @param null   $defaultValue
     * @param bool   $isHidden
     *
     * @return bool|mixed|string|void|null
     */
    private function askFor(
        string $message,
        string $messageValidator = 'Value is required',
        $defaultValue = null,
        bool $isHidden = false
    ) {
        $helper = $this->getHelper('question');
        $question = new Question($message, $defaultValue);
        $question->setValidator(function ($answer) use ($messageValidator) {
            if (empty($answer)) {
                throw new RuntimeException(
                    $messageValidator
                );
            }

            return $answer;
        });

        if ($isHidden) {
            $question->setHidden($isHidden);
        }

        return $helper->ask($this->input, $this->output, $question);
    }
}
