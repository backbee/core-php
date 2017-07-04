<?php

namespace BackBeeCloud\Security;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractContent;
use BackBee\Security\Acl\Permission\MaskBuilder;
use BackBee\Security\Group;
use BackBee\Security\User;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class AclHandler
{
    protected $app;
    protected $em;
    protected $site;
    protected $aclProvider;

    public function __construct(BBApplication $app)
    {
        $this->app = $app;
        $this->em = $this->app->getEntityManager();
        $this->aclProvider = $this->app->getSecurityContext()->getACLProvider();
        $this->categoryManager = $this->app->getContainer()->get('classcontent.category_manager');
        $this->site = $this->em->getRepository('BackBee\Site\Site')->findOneBy([]);
    }

    /**
     * Gets user permission according to his group.
     *
     * An empty array will be returned if user does not belong to any group or if
     * the group is not valid.
     *
     * @param  User   $user The user we want to get its permissions
     *
     * @return array the array that contains user permissions
     */
    public function getPermissionsByUser(User $user)
    {
        $group = $user->getGroups()->first();
        if (false == $group) {
            return [];
        }

        $all = $this->app->getConfig()->getSection('groups');
        $permissions = isset($all[$group->getName()])
            ? $all[$group->getName()]
            : []
        ;
        unset($permissions['description']);

        return $permissions;
    }

    public function handle(array $groups = [])
    {
        foreach ($groups as $groupName => $rights) {
            if (null !== $this->em->getRepository(Group::class)->findOneBy(['_name' => $groupName])) {
                continue;
            }

            $group = new Group();
            $group->setName($groupName);

            if (array_key_exists('description', $rights)) {
                $group->setDescription($rights['description']);
                unset($rights['description']);
            }

            $group->setSite($this->site);

            $this->em->persist($group);
            $this->em->flush($group);

            $this->setSiteGroupRights($group, $rights);
        }

        $adminGroup = $this->em->getRepository(Group::class)->findOneBy(['_name' => 'administrator']);
        if (0 === $adminGroup->getUsers()->count()) {
            $user = $this->em->getRepository(User::class)->findOneBy([], ['_created' => 'asc']);
            $adminGroup->addUser($user);
            $this->em->flush($adminGroup);
        }
    }

    public function setSiteGroupRights(Group $group, array $rights)
    {
        $securityIdentity = new UserSecurityIdentity($group->getObjectIdentifier(), 'BackBee\Security\Group');

        if (array_key_exists('sites', $rights)) {
            $sites = $this->addSiteRights($rights['sites'], $securityIdentity);

            if (array_key_exists('layouts', $rights)) {
                $this->addLayoutRights($rights['layouts'], $securityIdentity);
            }

            if (array_key_exists('pages', $rights)) {
                $this->addPageRights($rights['pages'], $securityIdentity);
            }

            if (array_key_exists('mediafolders', $rights)) {
                $this->addFolderRights($rights['mediafolders'], $securityIdentity);
            }

            if (array_key_exists('contents', $rights)) {
                $this->addContentRights($rights['contents'], $securityIdentity, $this->getAllContentClasses());
            }

            if (array_key_exists('bundles', $rights)) {
                $this->addBundleRights($rights['bundles'], $securityIdentity);
            }

            if (array_key_exists('users', $rights)) {
                $this->addUserRights($rights['users'], $securityIdentity);
            }

            if (array_key_exists('groups', $rights)) {
                $this->addGroupRights($rights['groups'], $securityIdentity);
            }

            return $sites;
        }
    }

    public function addSiteRights($sitesDefinition, $securityIdentity)
    {
        if (!array_key_exists('resources', $sitesDefinition) || !array_key_exists('actions', $sitesDefinition)) {
            return [];
        }

        $actions = $this->getActions($sitesDefinition['actions']);
        if (0 === count($actions)) {
            return [];
        }

        if (is_array($sitesDefinition['resources']) && in_array($site->getLabel(), $sitesDefinition['resources'])) {
            $this->addObjectAcl($site, $securityIdentity, $actions);
        } elseif ('all' === $sitesDefinition['resources']) {
            $this->addClassAcl('BackBee\Site\Site', $securityIdentity, $actions);
        }
    }

    public function addLayoutRights($layoutDefinition, $securityIdentity)
    {
        if (!array_key_exists('resources', $layoutDefinition) || !array_key_exists('actions', $layoutDefinition)) {
            return;
        }

        $actions = $this->getActions($layoutDefinition['actions']);
        if (0 === count($actions)) {
            return array();
        }

        if (is_array($layoutDefinition['resources'])) {
            foreach ($layoutDefinition['resources'] as $layoutLabel) {
                if (null === $layout = $this->em->getRepository('BackBee\Site\Layout')->findOneBy(['_site' => $site, '_label' => $layoutLabel])) {
                    continue;
                }

                $this->addObjectAcl($layout, $securityIdentity, $actions);
            }
        } elseif ('all' === $layoutDefinition['resources']) {
            $this->addClassAcl('BackBee\Site\Layout', $securityIdentity, $actions);
        }
    }

    public function addPageRights($pageDefinition, $securityIdentity)
    {
        if (!array_key_exists('resources', $pageDefinition) || !array_key_exists('actions', $pageDefinition)) {
            return;
        }

        $actions = $this->getActions($pageDefinition['actions']);
        if (0 === count($actions)) {
            return [];
        }

        if (is_array($pageDefinition['resources'])) {
            foreach ($pageDefinition['resources'] as $pageUrl) {
                $pages = $this->em->getRepository('BackBee\NestedNode\Page')->findBy(['_url' => $pageUrl]);
                foreach ($pages as $page) {
                    $this->addObjectAcl($page, $securityIdentity, $actions);
                }
            }
        } elseif ('all' === $pageDefinition['resources']) {
            $this->addClassAcl('BackBee\NestedNode\Page', $securityIdentity, $actions);
        }
    }

    public function addFolderRights($folderDefinition, $securityIdentity)
    {
        if (!array_key_exists('resources', $folderDefinition) || !array_key_exists('actions', $folderDefinition)) {
            return;
        }

        $actions = $this->getActions($folderDefinition['actions']);
        if (0 === count($actions)) {
            return [];
        }

        if ('all' === $folderDefinition['resources']) {
            $this->addClassAcl('BackBee\NestedNode\MediaFolder', $securityIdentity, $actions);
        }
    }

    public function addContentRights($contentDefinition, $securityIdentity, $allClasses = [])
    {
        if (!array_key_exists('resources', $contentDefinition) || !array_key_exists('actions', $contentDefinition)) {
            return;
        }

        if ('all' === $contentDefinition['resources']) {
            $actions = $this->getActions($contentDefinition['actions']);
            if (0 === count($actions)) {
                return [];
            }

            $this->addClassAcl('BackBee\ClassContent\AbstractClassContent', $securityIdentity, $actions);
        } elseif (is_array($contentDefinition['resources']) && 0 < count($contentDefinition['resources'])) {
            if (is_array($contentDefinition['resources'][0])) {
                $usedClasses = [];
                foreach ($contentDefinition['resources'] as $index => $resourcesDefinition) {
                    if (!isset($contentDefinition['actions'][$index])) {
                        continue;
                    }

                    $actions = $this->getActions($contentDefinition['actions'][$index]);
                    if ('remains' === $resourcesDefinition) {
                        foreach ($allClasses as $className) {
                            if (!in_array($className, $usedClasses)) {
                                $usedClasses[] = $className;
                                if (0 < count($actions)) {
                                    $this->addClassAcl($className, $securityIdentity, $actions);
                                }
                            }
                        }
                    } elseif (is_array($resourcesDefinition)) {
                        foreach ($resourcesDefinition as $content) {
                            $className = '\BackBee\ClassContent\\'.$content;
                            if (substr($className, -1) === '*') {
                                $className = substr($className, 0 - 1);
                                foreach ($allClasses as $fullClass) {
                                    if (0 === strpos($fullClass, $className)) {
                                        $usedClasses[] = $fullClass;
                                        if (0 < count($actions)) {
                                            $this->addClassAcl($fullClass, $securityIdentity, $actions);
                                        }
                                    }
                                }
                            } elseif (class_exists($className)) {
                                $usedClasses[] = $className;
                                if (0 < count($actions)) {
                                    $this->addClassAcl($className, $securityIdentity, $actions);
                                }
                            }
                        }
                    }
                }
            } else {
                $actions = $this->getActions($contentDefinition['actions']);
                if (0 === count($actions)) {
                    return [];
                }

                foreach ($contentDefinition['resources'] as $content) {
                    $className = '\BackBee\ClassContent\\'.$content;
                    if (substr($className, -1) === '*') {
                        $className = substr($className, 0 -1);
                        foreach($allClasses as $fullClass) {
                            if (0 === strpos($fullClass, $className)) {
                                $this->addClassAcl($fullClass, $securityIdentity, $actions);
                            }
                        }
                    } elseif (class_exists($className)) {
                        $this->addClassAcl($className, $securityIdentity, $actions);
                    }
                }
            }
        }
    }

    public function addBundleRights($bundleDefinition, $securityIdentity)
    {
        if (!array_key_exists('resources', $bundleDefinition) || !array_key_exists('actions', $bundleDefinition)) {
            return;
        }

        $actions = $this->getActions($bundleDefinition['actions']);
        if (0 === count($actions)) {
            return [];
        }

        if (is_array($bundleDefinition['resources'])) {
            foreach ($bundleDefinition['resources'] as $bundleName) {
                if (null !== $bundle = $this->app->getBundle($bundleName)) {
                    $this->addObjectAcl($bundle, $securityIdentity, $actions);
                }
            }
        } elseif ('all' === $bundleDefinition['resources']) {
            foreach ($this->app->getBundles() as $bundle) {
                $this->addObjectAcl($bundle, $securityIdentity, $actions);
            }
        }
    }

    public function addUserRights($userDef, $securityIdentity)
    {
        if (!array_key_exists('resources', $userDef) || !array_key_exists('actions', $userDef)) {
            return [];
        }

        $actions = $this->getActions($userDef['actions']);
        if (0 === count($actions)) {
            return [];
        }

        if (is_array($userDef['resources'])) {
            foreach ($userDef['resources'] as $userId) {
                $user = $this->em->getRepository('BackBee\Security\User')->findBy(['_id' => $userId]);
                $this->addObjectAcl($user, $securityIdentity, $actions);
            }
        } elseif ('all' === $userDef['resources']) {
            $this->addClassAcl('BackBee\\Security\\User', $securityIdentity, $actions);
        }
    }


    public function addGroupRights($groupDef, $securityIdentity)
    {
        if (!array_key_exists('resources', $groupDef) || !array_key_exists('actions', $groupDef)) {
            return [];
        }

        $actions = $this->getActions($groupDef['actions']);
        if (0 === count($actions)) {
            return [];
        }

        if (is_array($groupDef['resources'])) {
            foreach ($groupDef['resources'] as $group_id) {
                $group = $this->em->getRepository('BackBee\Security\Group')->findBy(array('_id' => $group_id));
                $this->addObjectAcl($group, $securityIdentity, $actions);
            }
        } elseif ('all' === $groupDef['resources']) {
            $this->addClassAcl('BackBee\\Security\\Group', $securityIdentity, $actions);
        }
    }

    public function getAllContentClasses()
    {
        $allClasses = [];
        foreach ($this->categoryManager->getCategories() as $category) {
            $blocks = array_map(
                function($block) {
                    return AbstractContent::CLASSCONTENT_BASE_NAMESPACE.str_replace('/', NAMESPACE_SEPARATOR, $block->type);
                },
                $category->getBlocks()
            );
            $allClasses = array_merge($allClasses, $blocks);
        }

        return $allClasses;
    }

    public function getActions($definition)
    {
        $actions = [];
        if (is_array($definition)) {
            $actions = array_intersect(['view', 'create', 'edit', 'delete', 'publish'], $definition);
        } elseif ('all' === $definition) {
            $actions = ['view', 'create', 'edit', 'delete', 'publish'];
        }

        return $actions;
    }

    public function addObjectAcl($object, $securityIdentity, $rights)
    {
        $this->addAcl(ObjectIdentity::fromDomainObject($object), $securityIdentity, $rights);
    }

    public function addClassAcl($className, $securityIdentity, $rights)
    {
        $this->addAcl(new ObjectIdentity('all', $className), $securityIdentity, $rights);
    }

    public function addAcl($objectIdentity, $securityIdentity, $rights)
    {
        try {
            $acl = $this->aclProvider->createAcl($objectIdentity);
        } catch (\Exception $e) {
            $acl = $this->aclProvider->findAcl($objectIdentity);
        }

        $builder = new MaskBuilder();
        foreach ($rights as $right) {
            $builder->add($right);
        }

        $mask = $builder->get();
        foreach($acl->getObjectAces() as $i => $ace) {
            if($securityIdentity->equals($ace->getSecurityIdentity())) {
                $acl->updateObjectAce($i, $ace->getMask() & ~$mask);
            }
        }

        $acl->insertClassAce($securityIdentity, $mask);

        $this->aclProvider->updateAcl($acl);
    }
}
