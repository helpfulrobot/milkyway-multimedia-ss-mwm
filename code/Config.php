<?php
/**
 * Milkyway Multimedia
 * Config.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS;

use Config as Original;

class Config {
    private static $_cache = [];

    /**
     * Get a config value from DataObject/YAML/environment vars using dot notation
     *
     * @param string $key
     * @param array $objects
     * @param mixed|null $default
     * @param Callable $parseEnvVarFn
     * @param array $beforeConfigNamespaceCheckCallbacks
     * @param bool $fromCache
     * @param bool $doCache
     * @return mixed|null
     */
    public static function get($key, $objects = [], $default = null, $parseEnvVarFn = null, $beforeConfigNamespaceCheckCallbacks = [], $fromCache = true, $doCache = true) {
	    foreach($objects as $object) {
		    if($object && ($object instanceof \ViewableData) && $object->$key)
			    return $object->$key;
	    }

	    // Grab mapping from object
	    $mapping = (array)Original::inst()->get('environment', 'mapping');
	    $objects = array_reverse($objects, true);

	    foreach($objects as $object) {
		    if($object && method_exists($object, 'config'))
		        $mapping = array_merge($mapping, (array)$object->config()->db_to_environment_mapping);
	    }

	    if(isset($mapping[$key]))
		    $key = $mapping[$key];

	    // 1. Check cache for valid key and return if found
        if($fromCache && isset(static::$_cache[$key])) {
            return static::$_cache[$key] === null ? $default : static::$_cache[$key];
        }

        $value = $default;

	    // The function to check the $_ENV vars
        $findInEnvironment = function($key) use($parseEnvVarFn) {
            $value = null;

            if(isset($_ENV[$key]))
                $value = $_ENV[$key];

            if(getenv($key))
                $value = getenv($key);

            if(is_callable($parseEnvVarFn))
                $value = call_user_func_array($parseEnvVarFn, [$value, $key]);

            return $value;
        };

	    // If key has dots, check recursively
        if(strpos($key, '.')) {
            $keyParts = explode('.', $key);

	        // First part of key can denote multiple classes separated by a pipe (or)
            $namespaces = explode('|', array_shift($keyParts));

	        // 2. Check \Config class for original value
	        foreach($namespaces as $namespace) {
		        // Do a callback to get a value from a function sent in (this is for checking SiteConfig)
		        if(isset($beforeConfigClassCheckCallbacks[$namespace]) && is_callable($beforeConfigNamespaceCheckCallbacks[$namespace])) {
			        $value = call_user_func_array($beforeConfigNamespaceCheckCallbacks[$namespace], [$keyParts, $key]);

			        if($value !== null)
				        break;
		        }

	            $config = Original::inst()->forClass($namespace);

	            $value = $config->{implode('.', $keyParts)};

		        // 3. If value not found explicitly, recursively get if array
	            if(!$value && count($keyParts) > 1) {
	                $configKey = array_shift($keyParts);
	                $configValue = $config->$configKey;

	                if(is_array($configValue))
	                    $value = array_get($configValue, implode('.', $keyParts));
		            else
			            $value = $configValue;
	            }

		        if($value !== null)
			        break;
	        }

	        // 4. Check environment for key explicitly
            if($value === null)
                $value = $findInEnvironment($key);

	        // 5. Otherwise check for key by namespace in environment
	        if($value === null && count($namespaces) > 1) {
		        foreach($namespaces as $namespace) {
			        $value = $findInEnvironment($namespace . '.' . implode('.', $keyParts));

			        if($value !== null)
				        break;
		        }
	        }

	        // 6. Otherwise check for key recursively in environment
            if($value === null && count($keyParts)) {
	            foreach($namespaces as $namespace) {
		            if(($first = $findInEnvironment($namespace)) && is_array($first)) {
			            $value = array_get($first, implode('.', $keyParts));

			            if($value !== null)
				            break;
		            }
	            }

	            // 7. Otherwise, check for key as is (without namespaces, a global override to assume)
	            if($value === null) {
		            $value = $findInEnvironment(implode('.', $keyParts));
	            }
            }
        }
        else {
	        // Or else check in $_ENV vars
            $value = $findInEnvironment($key);
        }

        if($doCache)
            static::$_cache[$key] = $value;

        return $value === null ? $default : $value;
    }

    /**
     * Set a value manually in the config cache
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value = null) {
        static::$_cache[$key] = $value;
    }

    /**
     * Remove a value from the config cache
     *
     * @param string $key
     */
    public static function remove($key) {
        if(isset(static::$_cache[$key]))
            unset(static::$_cache[$key]);
    }
} 