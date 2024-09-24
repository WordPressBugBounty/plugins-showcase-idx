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
    protected $defaults = [
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
    ];

    private function __construct($options = [])
    {
        $this->options = array_merge($this->defaults, $options);
        $this->data = $this->compileState();
    }

    public static function init($options = [])
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
        $version = [];
        $overview = [];
        $directives = [];

        if (!extension_loaded('Zend OPcache')) {
            $version['opcache_product_name'] = 'The Zend OPcache extension does not appear to be installed.';
        } else {
            $config = opcache_get_configuration();
            $version = $config['version'];

            $ocEnabled = ini_get('opcache.enable');
            if ($ocEnabled == 1) {
                $enabled = true;
                $status = opcache_get_status();

                $overview = array_merge(
                    $status['memory_usage'], $status['opcache_statistics'], [
                        'used_memory_percentage'  => round(100 * (
                                ($status['memory_usage']['used_memory'] + $status['memory_usage']['wasted_memory'])
                                / $config['directives']['opcache.memory_consumption'])),
                        'hit_rate_percentage'     => round($status['opcache_statistics']['opcache_hit_rate']),
                        'wasted_percentage'       => round($status['memory_usage']['current_wasted_percentage'], 2),
                        'readable' => [
                            'total_memory'       => $this->size($config['directives']['opcache.memory_consumption']),
                            'used_memory'        => $this->size($status['memory_usage']['used_memory']),
                            'free_memory'        => $this->size($status['memory_usage']['free_memory']),
                            'wasted_memory'      => $this->size($status['memory_usage']['wasted_memory']),
                            'num_cached_scripts' => number_format($status['opcache_statistics']['num_cached_scripts']),
                            'hits'               => number_format($status['opcache_statistics']['hits']),
                            'misses'             => number_format($status['opcache_statistics']['misses']),
                            'blacklist_miss'     => number_format($status['opcache_statistics']['blacklist_misses']),
                            'num_cached_keys'    => number_format($status['opcache_statistics']['num_cached_keys']),
                            'max_cached_keys'    => number_format($status['opcache_statistics']['max_cached_keys']),
                            'interned'           => null,
                            'start_time'         => date('Y-m-d H:i:s', $status['opcache_statistics']['start_time']),
                            'last_restart_time'  => ($status['opcache_statistics']['last_restart_time'] == 0
                                    ? 'never'
                                    : date('Y-m-d H:i:s', $status['opcache_statistics']['last_restart_time'])
                                )
                        ]
                    ]
                );
            } else {
                $version['opcache_product_name'] .= ' installed, but not enabled.';
            }

            $directives = [];
            ksort($config['directives']);
            foreach ($config['directives'] as $k => $v) {
                $directives[$k] = $v;
            }
        }

        return [
            'enabled'  => $enabled,
            'version'    => $version,
            'overview'   => $overview,
            'directives' => $directives,
        ];
    }
}
