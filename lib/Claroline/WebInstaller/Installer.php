<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\WebInstaller;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Installation\Settings\FirstAdminSettings;
use Claroline\CoreBundle\Library\Security\PlatformRoles;
use Symfony\Component\Filesystem\Filesystem;

class Installer
{
    private $adminSettings;
    private $writer;
    private $kernelFile;
    private $kernelClass;

    public function __construct(
        FirstAdminSettings $adminSettings,
        Writer $writer,
        $kernelFile,
        $kernelClass
    )
    {
        $this->adminSettings = $adminSettings;
        $this->writer = $writer;
        $this->kernelFile = $kernelFile;
        $this->kernelClass = $kernelClass;
    }

    public function install()
    {
        // preventive clear in case the installer is launched twice
        $this->clearCache();

        require_once $this->kernelFile;

        $kernel = new $this->kernelClass('prod', false);
        $kernel->boot();

        $refresher = $kernel->getContainer()->get('claroline.installation.refresher');
        $refresher->installAssets();

        $installer = $kernel->getContainer()->get('claroline.installation.platform_installer');
        $installer->installFromOperationFile();

        $userManager = $kernel->getContainer()->get('claroline.manager.user_manager');
        $user = new User();
        $user->setFirstName($this->adminSettings->getFirstName());
        $user->setLastName($this->adminSettings->getLastName());
        $user->setUsername($this->adminSettings->getUsername());
        $user->setPlainPassword($this->adminSettings->getPassword());
        $user->setMail($this->adminSettings->getEmail());
        $userManager->createUserWithRole($user, PlatformRoles::ADMIN);

        $refresher->dumpAssets('prod');

        $this->writer->writeInstallFlag();
    }

    private function clearCache()
    {
        if (is_dir($directory = __DIR__ . '/../../../../../app/cache/prod')) {
            $fileSystem = new Filesystem();
            $cacheIterator = new \DirectoryIterator($directory);

            foreach ($cacheIterator as $item) {
                if (!$item->isDot()) {
                    $fileSystem->remove($item->getPathname());
                }
            }
        }
    }
}
