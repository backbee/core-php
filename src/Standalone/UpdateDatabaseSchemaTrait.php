<?php

namespace BackBeePlanet\Standalone;

use BackBee\BBApplication;
use BackBee\Event\Event;
use BackBee\Installer\Database;
use Exception;
use Symfony\Component\Security\Acl\Dbal\Schema;

/**
 * Trait UpdateDatabaseSchemaTrait
 *
 * @package BackBeePlanet\Standalone
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
trait UpdateDatabaseSchemaTrait
{
    /**
     * Update database schema.
     *
     * @param BBApplication $app
     */
    protected function updateDatabaseSchema(BBApplication $app): void
    {
        $installer = new Database($app);
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
        $conn = $app->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();

        foreach ($schema->toSql($platform) as $query) {
            try {
                $conn->executeQuery($query);
            } catch (Exception $exception) {
                $app->getLogging()->error(
                    sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
                );
            }
        }

        $app->getEventDispatcher()->dispatch('database.schema.updated', new Event($app->getEntityManager()));
    }
}
