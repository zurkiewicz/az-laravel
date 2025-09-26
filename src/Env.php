<?php

namespace AZ\Project;

use Dotenv\Dotenv;

class Env
{

    /**
     *
     * @var boolean
     */
    static private $loaded = false;

    /**
     * 
     */
    public function __construct()
    {

        if (!self::$loaded) {

            self::$loaded = true;

            $basePath = getcwd();

            if (!file_exists("{$basePath}/.env")) {

                $basePath = realpath("{$basePath}/../");
            }

            if (file_exists("{$basePath}/.env")) {
                $dotenv = Dotenv::createImmutable($basePath);
                $dotenv->safeLoad();
            }
        }
    }

    /**
     * Return env value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get(string $key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}
