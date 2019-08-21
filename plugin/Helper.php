<?php

namespace GeminiLabs\SiteReviews;

use GeminiLabs\SiteReviews\Database\Cache;
use GeminiLabs\Vectorface\Whip\Whip;

class Helper
{
    /**
     * @param string $name
     * @param string $path
     * @return string
     */
    public function buildClassName($name, $path = '')
    {
        $className = $this->camelCase($name);
        $path = ltrim(str_replace(__NAMESPACE__, '', $path), '\\');
        return !empty($path)
            ? __NAMESPACE__.'\\'.$path.'\\'.$className
            : $className;
    }

    /**
     * @param string $name
     * @param string $prefix
     * @return string
     */
    public function buildMethodName($name, $prefix = '')
    {
        return lcfirst($prefix.$this->buildClassName($name));
    }

    /**
     * @param string $name
     * @return string
     */
    public function buildPropertyName($name)
    {
        return lcfirst($this->buildClassName($name));
    }

    /**
     * @param string $string
     * @return string
     */
    public function camelCase($string)
    {
        $string = ucwords(str_replace(['-', '_'], ' ', trim($string)));
        return str_replace(' ', '', $string);
    }

    /**
     * @return bool
     */
    public function compareArrays(array $arr1, array $arr2)
    {
        sort($arr1);
        sort($arr2);
        return $arr1 == $arr2;
    }

    /**
     * @param mixed $array
     * @return array
     */
    public function consolidateArray($array)
    {
        return is_array($array) || is_object($array)
            ? (array) $array
            : [];
    }

    /**
     * @return array
     */
    public function convertDotNotationArray(array $array)
    {
        $results = [];
        foreach ($array as $path => $value) {
            $results = $this->dataSet($results, $path, $value);
        }
        return $results;
    }

    /**
     * @param string $name
     * @return string
     */
    public function convertPathToId($path, $prefix = '')
    {
        return str_replace(['[', ']'], ['-', ''], $this->convertPathToName($path, $prefix));
    }

    /**
     * @param string $path
     * @return string
     */
    public function convertPathToName($path, $prefix = '')
    {
        $levels = explode('.', $path);
        return array_reduce($levels, function ($result, $value) {
            return $result.= '['.$value.']';
        }, $prefix);
    }

    /**
     * @param string $string
     * @param mixed $callback
     * @return array
     */
    public function convertStringToArray($string, $callback = null)
    {
        $array = array_map('trim', explode(',', $string));
        return $callback
            ? array_filter($array, $callback)
            : array_filter($array);
    }

    /**
     * @param string $string
     * @return string
     */
    public function dashCase($string)
    {
        return str_replace('_', '-', $this->snakeCase($string));
    }

    /**
     * Get a value from an array of values using a dot-notation path as reference.
     * @param array $data
     * @param string $path
     * @param mixed $fallback
     * @return mixed
     */
    public function dataGet($data, $path = '', $fallback = '')
    {
        $data = $this->consolidateArray($data);
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return $fallback;
            }
            $data = $data[$key];
        }
        return $data;
    }

    /**
     * Set a value to an array of values using a dot-notation path as reference.
     * @param string $path
     * @param mixed $value
     * @return array
     */
    public function dataSet(array $data, $path, $value)
    {
        $token = strtok($path, '.');
        $ref = &$data;
        while (false !== $token) {
            $ref = $this->consolidateArray($ref);
            $ref = &$ref[$token];
            $token = strtok('.');
        }
        $ref = $value;
        return $data;
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @return bool
     */
    public function endsWith($needle, $haystack)
    {
        $length = strlen($needle);
        return 0 != $length
            ? substr($haystack, -$length) === $needle
            : true;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function filterInput($key, array $request = [])
    {
        if (isset($request[$key])) {
            return $request[$key];
        }
        $variable = filter_input(INPUT_POST, $key);
        if (is_null($variable) && isset($_POST[$key])) {
            $variable = $_POST[$key];
        }
        return $variable;
    }

    /**
     * @param string $key
     * @return array
     */
    public function filterInputArray($key)
    {
        $variable = filter_input(INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        if (empty($variable) && !empty($_POST[$key]) && is_array($_POST[$key])) {
            $variable = $_POST[$key];
        }
        return (array) $variable;
    }

    /**
     * @param bool $flattenValue
     * @param string $prefix
     * @return array
     */
    public function flattenArray(array $array, $flattenValue = false, $prefix = '')
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = ltrim($prefix.'.'.$key, '.');
            if ($this->isIndexedFlatArray($value)) {
                if ($flattenValue) {
                    $value = '['.implode(', ', $value).']';
                }
            } elseif (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $flattenValue, $newKey));
                continue;
            }
            $result[$newKey] = $value;
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getIpAddress()
    {
        $cloudflareIps = glsr(Cache::class)->getCloudflareIps();
        $ipv6 = defined('AF_INET6')
            ? $cloudflareIps['v6']
            : [];
        $whitelist = apply_filters('site-reviews/whip/whitelist', [
            Whip::CLOUDFLARE_HEADERS => [
                Whip::IPV4 => $cloudflareIps['v4'],
                Whip::IPV6 => $ipv6,
            ],
            Whip::CUSTOM_HEADERS => [
                Whip::IPV4 => ['127.0.0.1'],
                Whip::IPV6 => ['::1'],
            ],
        ]);
        $methods = Whip::CUSTOM_HEADERS | Whip::CLOUDFLARE_HEADERS | Whip::REMOTE_ADDR;
        $methods = apply_filters('site-reviews/whip/methods', $methods);
        $whip = new Whip($methods, $whitelist);
        do_action_ref_array('site-reviews/whip', [&$whip]);
        return (string) $whip->getValidIpAddress();
    }

    /**
     * @param string $key
     * @param string $position
     * @return array
     */
    public function insertInArray(array $array, array $insert, $key, $position = 'before')
    {
        $keyPosition = intval(array_search($key, array_keys($array)));
        if ('after' == $position) {
            ++$keyPosition;
        }
        if (false !== $keyPosition) {
            $result = array_slice($array, 0, $keyPosition);
            $result = array_merge($result, $insert);
            return array_merge($result, array_slice($array, $keyPosition));
        }
        return array_merge($array, $insert);
    }

    /**
     * @param mixed $array
     * @return bool
     */
    public function isIndexedFlatArray($array)
    {
        if (!is_array($array) || array_filter($array, 'is_array')) {
            return false;
        }
        return wp_is_numeric_array($array);
    }

    /**
     * @param string $prefix
     * @param string $trim
     * @return array
     */
    public function prefixArrayKeys(array $values, $prefix = '', $trim = '')
    {
        $prefixed = [];
        foreach ($values as $key => $value) {
            $key = $prefix.ltrim($key, $trim);
            $prefixed[$key] = $value;
        }
        return $prefixed;
    }

    /**
     * @param string $string
     * @param string $prefix
     * @return string
     */
    public function prefixString($string, $prefix = '')
    {
        return $prefix.str_replace($prefix, '', trim($string));
    }

    /**
     * @return array
     */
    public function removeEmptyArrayValues(array $array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (!$value) {
                continue;
            }
            $result[$key] = is_array($value)
                ? $this->removeEmptyArrayValues($value)
                : $value;
        }
        return $result;
    }

    /**
     * @param string $prefix
     * @param string $text
     * @return string
     */
    public function removePrefix($prefix, $text)
    {
        return 0 === strpos($text, $prefix)
            ? substr($text, strlen($prefix))
            : $text;
    }

    /**
     * @param string $string
     * @return string
     */
    public function snakeCase($string)
    {
        if (!ctype_lower($string)) {
            $string = preg_replace('/\s+/u', '', $string);
            $string = preg_replace('/(.)(?=[A-Z])/u', '$1_', $string);
            $string = function_exists('mb_strtolower')
                ? mb_strtolower($string, 'UTF-8')
                : strtolower($string);
        }
        return str_replace('-', '_', $string);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @return bool
     */
    public function startsWith($needle, $haystack)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}
