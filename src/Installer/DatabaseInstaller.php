<?php

namespace BackBee\Installer;

use BackBee\Command\AbstractCommand;
use BackBee\Command\InstallCommand;
use BackBee\Event\Event;
use BackBeePlanet\Standalone\StandaloneHelper;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Exception;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Security\Acl\Dbal\Schema;
use function in_array;

/**
 * Class DatabaseInstaller
 *
 * @package BackBee\Installer
 *
 * @author  Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class DatabaseInstaller extends AbstractInstaller
{
    /**
     * Create database.
     *
     * @param StyleInterface $io
     *
     * @return int|void
     */
    public static function createDatabase(StyleInterface $io)
    {
        $io->section('Create database');

        $config = AbstractCommand::parseYaml('doctrine.yml', InstallCommand::CONFIG_REGULAR_YAML)['dbal'];
        $dbname = $config['dbname'];
        unset($config['dbname']);
        $tmpConn = null;

        try {
            $tmpConn = DriverManager::getConnection($config);
            if (!($tmpConn->getDriver()->getDatabasePlatform() instanceof MySqlPlatform)) {
                $io->error(
                    'BackBee Standalone only support MySQL database, installation aborted.'
                );

                throw new Exception();
            }
        } catch (Exception $exception) {
            $io->error(
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
            $io->note(sprintf('Database "%s" already exists.', $dbname));
            return 0;
        }

        $sql = sprintf('CREATE DATABASE `%s`', $dbname);

        if (isset($config['collation'], $config['charset'])) {
            $sql = sprintf('%s CHARACTER SET %s COLLATE %s', $sql, $config['charset'], $config['collation']);
        }

        try {
            $tmpConn->executeUpdate($sql);
        } catch (Exception $exception) {
            $io->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        $io->text(sprintf('"%s" has been created.', $dbname));
    }

    /**
     * Update database schema.
     *
     * @param StyleInterface $io
     */
    public function updateDatabaseSchema(StyleInterface $io): void
    {
        $io->section('Update database schema');

        $installer = new Database($this->getApplication());
        $installer->updateBackBeeSchema();
        $installer->updateBundlesSchema();

        $tablesMapping = [
            'class_table_name'         => 'acl_classes',
            'entry_table_name'         => 'acl_entries',
            'oid_table_name'           => 'acl_object_identities',
            'oid_ancestors_table_name' => 'acl_object_identity_ancestors',
            'sid_table_name'           => 'acl_security_identities',
        ];

        $schema = new Schema($tablesMapping);
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();

        foreach ($schema->toSql($platform) as $query) {
            try {
                $conn->executeQuery($query);
            } catch (Exception $exception) {
                $this->getApplication()->getLogging()->error(
                    sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
                );
            }
        }

        $this->getApplication()->getEventDispatcher()->dispatch(
            'database.schema.updated',
            new Event($this->getEntityManager())
        );

        $io->success('Database\'s schema has been updated.');
    }
}