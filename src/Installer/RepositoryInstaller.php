<?php

namespace BackBee\Installer;

use BackBee\Command\AbstractCommand;
use BackBeePlanet\Standalone\StandaloneHelper;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class RepositoryInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class RepositoryInstaller extends AbstractInstaller
{
    /**
     * Build repository.
     *
     * @param string         $appName
     * @param StyleInterface $io
     */
    public static function buildRepository(string $appName, StyleInterface $io): void
    {
        $io->section('Build repository');

        // Config directory
        $configDir = StandaloneHelper::configDir();

        // Data directories
        $dataDir = StandaloneHelper::repositoryDir() . DIRECTORY_SEPARATOR . 'Data';
        StandaloneHelper::mkdirOnce($dataDir);
        StandaloneHelper::mkdirOnce($dataDir . DIRECTORY_SEPARATOR . 'Media');
        StandaloneHelper::mkdirOnce($dataDir . DIRECTORY_SEPARATOR . 'Storage');
        StandaloneHelper::mkdirOnce($dataDir . DIRECTORY_SEPARATOR . 'Tmp');

        $io->text('Data\'s folders are ready.');

        // bootstrap.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'bootstrap.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
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

            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // build config.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'config.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            $config = AbstractCommand::parseYaml('config.yml.dist');

            if (null === $config['app']['name']) {
                $config['app']['name'] = $appName;
            }

            file_put_contents($filepath, Yaml::dump($config));
            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // build doctrine.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'doctrine.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            $config = array_merge(
                AbstractCommand::parseYaml('doctrine.yml.dist')['dbal'],
                self::getDoctrineConfig()
            );

            file_put_contents($filepath, Yaml::dump(['dbal' => $config], 20));
            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // build security.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'security.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            file_put_contents($filepath, Yaml::dump(AbstractCommand::parseYaml('security.yml.dist'), 20));
            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // build elasticsearch.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'elasticsearch.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            $config = array_merge(
                AbstractCommand::parseYaml('elasticsearch.yml.dist'),
                self::getElasticsearchConfig()
            );
            file_put_contents($filepath, Yaml::dump($config, 20));
            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // build redis.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'redis.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            $config = array_merge(
                AbstractCommand::parseYaml('redis.yml.dist'),
                self::getRedisConfig()
            );
            file_put_contents($filepath, Yaml::dump($config, 20));
            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // build mailer.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'mailer.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            $config = array_merge(
                AbstractCommand::parseYaml('mailer.yml.dist'),
                self::getMailerConfig()
            );
            file_put_contents($filepath, Yaml::dump($config, 20));
            $io->text(sprintf('%s has been generated.', $filepath));
        }

        $io->newLine();

        // services.yml
        $filepath = $configDir . DIRECTORY_SEPARATOR . 'services.yml';
        if (file_exists($filepath)) {
            $io->note(sprintf('%s already exists.', $filepath));
        } else {
            file_put_contents($filepath, Yaml::dump(array_merge(
                AbstractCommand::parseYaml('services.yml.dist'),
                [
                    'parameters' => [
                        'bbapp.cache.dir' => StandaloneHelper::cacheDir(),
                        'bbapp.data.dir'  => $dataDir,
                        'bbapp.log.dir'   => StandaloneHelper::logDir(),
                        'secret_key'      => md5($appName),
                    ],
                ]
            ), 20));

            $io->text(sprintf('%s has been generated.', $filepath));
        }

        // bundles folder
        $filesystem = new Filesystem();
        $originDir = StandaloneHelper::distDir() . DIRECTORY_SEPARATOR . 'bundles';
        $targetDir = $configDir . DIRECTORY_SEPARATOR . 'bundles';

        if ($filesystem->exists($originDir) && false === $filesystem->exists($targetDir)) {
            $filesystem->mirror($originDir, $targetDir);
            $finder = new Finder();
            $finder->files()->in($targetDir);
            foreach ($finder as $file) {
                $filesystem->rename($file->getRealPath(), str_replace('.dist', '', $file->getRealPath()));
            }
        }

        $io->newLine();
    }

    /**
     * Get doctrine configuration.
     *
     * @return array
     */
    private static function getDoctrineConfig(): array
    {
        if (null === ($config['host'] = AbstractCommand::getInput()->getOption('db_host'))) {
            $config['host'] = AbstractCommand::askFor('Database host: ');
        }

        if (null === ($config['dbname'] = AbstractCommand::getInput()->getOption('db_name'))) {
            $config['dbname'] = AbstractCommand::askFor('Database name: ');
        }

        if (null === ($config['user'] = AbstractCommand::getInput()->getOption('db_username'))) {
            $config['user'] = AbstractCommand::askFor('Database username: ');
        }

        if (null === ($config['password'] = AbstractCommand::getInput()->getOption('db_password'))) {
            $config['password'] = AbstractCommand::askFor('Database password: ', true);
        }

        return $config;
    }

    /**
     * Get elasticsearch configuration.
     *
     * @return array
     */
    private static function getElasticsearchConfig(): array
    {
        if (null === ($config['host'] = AbstractCommand::getInput()->getOption('elasticsearch_host'))) {
            $config['host'] = AbstractCommand::askFor('Elasticsearch host with port: ');
        }

        return $config;
    }

    /**
     * Get redis configuration.
     *
     * @return array
     */
    private static function getRedisConfig(): array
    {
        if (null === ($config['host'] = AbstractCommand::getInput()->getOption('redis_host'))) {
            $config['host'] = AbstractCommand::askFor('Redis host: ');
        }

        return $config;
    }

    /**
     * Get mailer configuration.
     *
     * @return array
     */
    private static function getMailerConfig(): array
    {
        if (null === ($config['server'] = AbstractCommand::getInput()->getOption('mailer_host'))) {
            $config['server'] = AbstractCommand::askFor('Mailer host: ');
        }

        if (null === ($config['port'] = AbstractCommand::getInput()->getOption('mailer_port'))) {
            $config['port'] = AbstractCommand::askFor('Mailer port: ');
        }

        if (null === ($config['email_from'] = AbstractCommand::getInput()->getOption('mailer_from'))) {
            $config['email_from'] = AbstractCommand::askFor('Mailer from: ');
        }

        return $config;
    }
}