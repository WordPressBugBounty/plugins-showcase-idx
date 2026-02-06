<?php

namespace OpcacheGui;

/**
 * OPcache Info
 *
 * @author Andrew Collington, andy@amnuts.com, modified by Alan Pinstein
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, http://acollington.mit-license.org/
 */

class OpCacheService
{
    protected $data;
    protected $options;
    protected $defaults = array(
        'allow_filelist'   => true,
        'allow_invalidate' => true,
        'allow_reset'      => true,
        'allow_realtime'   => true,
        'refresh_time'     => 5,
        'size_precision'   => 2,
        'size_space'       => false,
        'charts'           => true,
        'debounce_rate'    => 250,
        'cookie_name'      => 'opcachegui',
        'cookie_ttl'       => 365
    );

    private function __construct($options = array())
    {
        $this->options = array_merge($this->defaults, $options);
        $this->data = $this->compileState();
    }

    public static function init($options = array())
    {
        $self = new self($options);
        return $self;
    }

    public function getOption($name = null)
    {
        if ($name === null) {
            return $this->options;
        }
        return (isset($this->options[$name])
            ? $this->options[$name]
            : null
        );
    }

    public function getData($section = null, $property = null)
    {
        if ($section === null) {
            return $this->data;
        }
        $section = strtolower($section);
        if (isset($this->data[$section])) {
            if ($property === null || !isset($this->data[$section][$property])) {
                return $this->data[$section];
            }
            return $this->data[$section][$property];
        }
        return null;
    }

    protected function size($size)
    {
        $i = 0;
        $val = array('b', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        while (($size / 1024) > 1) {
            $size /= 1024;
            ++$i;
        }
        return sprintf('%.'.$this->getOption('size_precision').'f%s%s',
            $size, ($this->getOption('size_space') ? ' ' : ''), $val[$i]
        );
    }

    protected function compileState()
    {
        $enabled = false;
        $version = array();
        $overview = array();
        $directives = array();

        if (!extension_loaded('Zend OPcache')) {
            $version['opcache_product_name'] = 'The Zend OPcache extension does not appear to be installed.';
        } else {
            $config = opcache_get_configuration();

            // Check if opcache_get_configuration() returned valid data
            if ($config === false || !is_array($config) || !isset($config['version'])) {
                $version['opcache_product_name'] = 'OPcache installed, but configuration unavailable (restricted by opcache.restrict_api).';
                return array(
                    'enabled'  => $enabled,
                    'version'    => $version,
                    'overview'   => $overview,
                    'directives' => $directives
                );
            }

            $version = $config['version'];

            $ocEnabled = ini_get('opcache.enable');
            if ($ocEnabled == 1) {
                $enabled = true;
                $status = opcache_get_status(false);

                // Check if opcache_get_status() returned valid data
                if ($status === false || !is_array($status) || !isset($status['memory_usage']) || !isset($status['opcache_statistics'])) {
                    $version['opcache_product_name'] .= ' enabled, but status unavailable (restricted by opcache.restrict_api).';
                } else {
                    $memoryConsumption = isset($config['directives']['opcache.memory_consumption']) ? $config['directives']['opcache.memory_consumption'] : 0;
                    $usedMemoryPercentage = 0;
                    if ($memoryConsumption > 0 && isset($status['memory_usage']['used_memory']) && isset($status['memory_usage']['wasted_memory'])) {
                        $usedMemoryPercentage = round(100 * (
                            ($status['memory_usage']['used_memory'] + $status['memory_usage']['wasted_memory'])
                            / $memoryConsumption));
                    }

                    $opcacheHitRate = isset($status['opcache_statistics']['opcache_hit_rate']) ? $status['opcache_statistics']['opcache_hit_rate'] : 0;
                    $wastedPercentage = isset($status['memory_usage']['current_wasted_percentage']) ? $status['memory_usage']['current_wasted_percentage'] : 0;
                    $startTime = isset($status['opcache_statistics']['start_time']) ? $status['opcache_statistics']['start_time'] : 0;
                    $lastRestartTime = isset($status['opcache_statistics']['last_restart_time']) ? $status['opcache_statistics']['last_restart_time'] : 0;

                    $overview = array_merge(
                        isset($status['memory_usage']) ? $status['memory_usage'] : array(),
                        isset($status['opcache_statistics']) ? $status['opcache_statistics'] : array(),
                        array(
                        'used_memory_percentage'  => $usedMemoryPercentage,
                        'hit_rate_percentage'     => round($opcacheHitRate),
                        'wasted_percentage'       => round($wastedPercentage, 2),
                        'readable' => array(
                            'total_memory'       => $this->size($memoryConsumption),
                            'used_memory'        => $this->size(isset($status['memory_usage']['used_memory']) ? $status['memory_usage']['used_memory'] : 0),
                            'free_memory'        => $this->size(isset($status['memory_usage']['free_memory']) ? $status['memory_usage']['free_memory'] : 0),
                            'wasted_memory'      => $this->size(isset($status['memory_usage']['wasted_memory']) ? $status['memory_usage']['wasted_memory'] : 0),
                            'num_cached_scripts' => number_format(isset($status['opcache_statistics']['num_cached_scripts']) ? $status['opcache_statistics']['num_cached_scripts'] : 0),
                            'hits'               => number_format(isset($status['opcache_statistics']['hits']) ? $status['opcache_statistics']['hits'] : 0),
                            'misses'             => number_format(isset($status['opcache_statistics']['misses']) ? $status['opcache_statistics']['misses'] : 0),
                            'blacklist_miss'     => number_format(isset($status['opcache_statistics']['blacklist_misses']) ? $status['opcache_statistics']['blacklist_misses'] : 0),
                            'num_cached_keys'    => number_format(isset($status['opcache_statistics']['num_cached_keys']) ? $status['opcache_statistics']['num_cached_keys'] : 0),
                            'max_cached_keys'    => number_format(isset($status['opcache_statistics']['max_cached_keys']) ? $status['opcache_statistics']['max_cached_keys'] : 0),
                            'interned'           => null,
                            'start_time'         => $startTime > 0 ? date('Y-m-d H:i:s', $startTime) : 'N/A',
                            'last_restart_time'  => ($lastRestartTime == 0 ? 'never' : date('Y-m-d H:i:s', $lastRestartTime))
                        )
                    )
                );
                }
            } else {
                $version['opcache_product_name'] .= ' installed, but not enabled.';
            }

            $directives = array();
            if (isset($config['directives']) && is_array($config['directives'])) {
                ksort($config['directives']);
                foreach ($config['directives'] as $k => $v) {
                    $directives[$k] = $v;
                }
            }
        }

        return array(
            'enabled'  => $enabled,
            'version'    => $version,
            'overview'   => $overview,
            'directives' => $directives
        );
    }
}
