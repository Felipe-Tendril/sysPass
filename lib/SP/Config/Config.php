<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Config;

use DI\Container;
use ReflectionObject;
use SP\Core\Exceptions\ConfigException;
use SP\Core\Session\Session;
use SP\Services\Config\ConfigBackupService;
use SP\Storage\XmlFileStorageInterface;
use SP\Storage\XmlHandler;

defined('APP_ROOT') || die();

/**
 * Esta clase es responsable de leer y escribir la configuración del archivo config.php
 */
class Config
{
    /**
     * @var bool
     */
    private static $configLoaded = false;
    /**
     * @var ConfigData
     */
    private $configData;
    /**
     * @var XmlHandler
     */
    private $fileStorage;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var Container
     */
    private $dic;

    /**
     * Config constructor.
     *
     * @param XmlFileStorageInterface $fileStorage
     * @param Session                 $session
     * @param Container               $dic
     * @throws ConfigException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(XmlFileStorageInterface $fileStorage, Session $session, Container $dic)
    {
        $this->session = $session;
        $this->fileStorage = $fileStorage;

        if (!self::$configLoaded) {
            $this->configData = new ConfigData();

            $this->loadConfigFile();

            self::$configLoaded = true;
        }
        $this->dic = $dic;
    }

    /**
     * Cargar el archivo de configuración
     *
     * @return ConfigData
     * @throws \SP\Core\Exceptions\ConfigException
     */
    public function loadConfigFile()
    {
        ConfigUtil::checkConfigDir();

        try {
            // Mapear el array de elementos de configuración con las propiedades de la clase configData
            $items = $this->fileStorage->load('config')->getItems();
            $reflectionObject = new ReflectionObject($this->configData);

            foreach ($reflectionObject->getProperties() as $property) {
                $property->setAccessible(true);

                if (isset($items[$property->getName()])) {
                    $property->setValue($this->configData, $items[$property->getName()]);
                }

                $property->setAccessible(false);
            }
        } catch (\Exception $e) {
            debugLog($e->getMessage());

            throw new ConfigException(ConfigException::CRITICAL, $e->getMessage(), '', $e->getCode(), $e);
        }

        return $this->configData;
    }

    /**
     * Cargar la configuración desde el archivo
     *
     * @param bool $reload
     * @return ConfigData
     */
    public function loadConfig($reload = false)
    {
        $configData = $this->session->getConfig();

        if ($reload === true
            || $configData === null
            || time() >= ($this->session->getConfigTime() + $configData->getSessionTimeout() / 2)
        ) {
            $this->saveConfigInSession();
        }

        return $this->configData;
    }

    /**
     * Guardar la configuración en la sesión
     */
    private function saveConfigInSession()
    {
        $this->session->setConfig($this->configData);
        $this->session->setConfigTime(time());
    }

    /**
     * Guardar la configuración
     *
     * @param ConfigData $configData
     * @param bool       $backup
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function saveConfig(ConfigData $configData, $backup = true)
    {
        if ($backup) {
            $this->dic->get(ConfigBackupService::class)->backup();
        }

        $configData->setConfigDate(time());
        $configData->setConfigSaver($this->session->getUserData()->getLogin());
        $configData->setConfigHash();

        $this->fileStorage->setItems($configData);
        $this->fileStorage->save('config');
    }

    /**
     * @return ConfigData
     */
    public function getConfigData()
    {
        return clone $this->configData;
    }
}