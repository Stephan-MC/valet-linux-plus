<?php

namespace Valet;

use Exception;

class Configuration
{
    public Filesystem $files;

    /**
     * Create a new Valet configuration class instance.
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Install the Valet configuration file.
     */
    public function install(): void
    {
        $this->createConfigurationDirectory();
        $this->createDriversDirectory();
        $this->createSitesDirectory();
        $this->createExtensionsDirectory();
        $this->createLogDirectory();
        $this->createCertificatesDirectory();
        $this->writeBaseConfiguration();

        $this->files->chown($this->path(), user());
    }

    /**
     * Uninstall the Valet configuration folder.
     * @throws Exception
     */
    public function uninstall(): void
    {
        if ($this->files->isDir(VALET_HOME_PATH)) {
            $this->files->remove(VALET_HOME_PATH);
        }
    }

    /**
     * Add the given path to the configuration.
     */
    public function addPath(string $path, bool $prepend = false): void
    {
        $this->write(tap($this->read(), function (&$config) use ($path, $prepend) {
            $method = $prepend ? 'prepend' : 'push';

            $config['paths'] = collect($config['paths'])->{$method}($path)->unique()->all();
        }));
    }

    /**
     * Add the given path to the configuration.
     */
    public function removePath(string $path): void
    {
        $this->write(tap($this->read(), function (&$config) use ($path) {
            $config['paths'] = collect($config['paths'])->reject(function ($value) use ($path) {
                return $value === $path;
            })->values()->all();
        }));
    }

    /**
     * Prune all non-existent paths from the configuration.
     */
    public function prune(): void
    {
        if (!$this->files->exists($this->path())) {
            return;
        }

        $this->write(tap($this->read(), function (&$config) {
            $config['paths'] = collect($config['paths'])->filter(function ($path) {
                return $this->files->isDir($path);
            })->values()->all();
        }));
    }

    /**
     * Get a configuration value.
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->files->exists($this->path())) {
            return $default;
        }

        $config = $this->read();

        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    /**
     * Get a configuration value.
     * @param mixed $value
     * @return mixed
     */
    public function set(string $key, $value)
    {
        return $this->updateKey($key, $value);
    }

    public function parseDomain(string $siteName): string
    {
        $domain = $this->get('domain');
        if (str_ends_with($siteName, ".$domain") !== true) {
            return \sprintf('%s.%s', $siteName, $domain);
        }
        return $siteName;
    }

    /**
     * Update a specific key in the configuration file.
     *
     * @param mixed $value
     * @return array
     */
    private function updateKey(string $key, $value)
    {
        return tap($this->read(), function (&$config) use ($key, $value) {
            $config[$key] = $value;
            $this->write($config);
        });
    }

    /**
     * Write the given configuration to disk.
     */
    private function write(array $config): void
    {
        $this->files->putAsUser(
            $this->path(),
            json_encode(
                $config,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ).PHP_EOL
        );
    }

    /**
     * Read the configuration file as JSON.
     */
    private function read(): array
    {
        return json_decode($this->files->get($this->path()), true);
    }

    /**
     * Create the Valet configuration directory.
     */
    private function createConfigurationDirectory(): void
    {
        $this->files->ensureDirExists(VALET_HOME_PATH, user());
    }

    /**
     * Create the Valet drivers directory.
     */
    private function createDriversDirectory(): void
    {
        if ($this->files->isDir($driversDirectory = VALET_HOME_PATH.'/Drivers')) {
            return;
        }

        $this->files->mkdirAsUser($driversDirectory);

        $this->files->putAsUser(
            $driversDirectory.'/SampleValetDriver.php',
            $this->files->get(VALET_ROOT_PATH.'/cli/stubs/SampleValetDriver.php')
        );
    }

    /**
     * Create the Valet sites directory.
     */
    private function createSitesDirectory(): void
    {
        $this->files->ensureDirExists(VALET_HOME_PATH.'/Sites', user());
    }

    /**
     * Create the directory for the Valet extensions.
     */
    private function createExtensionsDirectory(): void
    {
        $this->files->ensureDirExists(VALET_HOME_PATH.'/Extensions', user());
    }

    /**
     * Create the directory for Nginx logs.
     */
    private function createLogDirectory(): void
    {
        $this->files->ensureDirExists(VALET_HOME_PATH.'/Log', user());

        $this->files->touch(VALET_HOME_PATH.'/Log/nginx-error.log');
    }

    /**
     * Create the directory for SSL certificates.
     */
    private function createCertificatesDirectory(): void
    {
        $this->files->ensureDirExists(VALET_HOME_PATH.'/Certificates', user());
    }

    /**
     * Write the base, initial configuration for Valet.
     */
    private function writeBaseConfiguration(): void
    {
        if (!$this->files->exists($this->path())) {
            $this->write([
                'domain'                => 'test',
                'paths'                 => [],
                'port'                  => '80',
            ]);
        }
    }

    /**
     * Get the configuration file path.
     */
    private function path(): string
    {
        return VALET_HOME_PATH.'/config.json';
    }
}
