<?php

/*
 * This file is part of Gush package.
 *
 * (c) 2013-2014 Luis Cordova <cordoval@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Gush\Helper;

use Symfony\Component\Console\Helper\Helper;

class GitConfigHelper extends Helper
{
    /** @var \Gush\Helper\ProcessHelper */
    private $processHelper;

    public function __construct(ProcessHelper $processHelper)
    {
        $this->processHelper = $processHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'git_config';
    }

    /**
     * @param string $config
     * @param string $section
     * @param null   $expectedValue
     *
     * @return bool
     */
    public function hasGitConfig($config, $section = 'local', $expectedValue = null)
    {
        $value = trim(
                $this->processHelper->runCommand(
                sprintf(
                    'git config --%s --get %s',
                    $section,
                    $config
                ),
                true
            )
        );

        if ('' === $value || (null !== $expectedValue && $value !== $expectedValue)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $config
     * @param string $value
     * @param bool   $overwrite
     * @param string $section
     */
    public function setGitConfig($config, $value, $overwrite = false, $section = 'local')
    {
        if ($this->hasGitConfig($config, $section, $value) && $overwrite) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to set git config "%s" at %s, because the value is already set.',
                    $config,
                    $section
                )
            );
        }

        $this->processHelper->runCommand(
            sprintf(
                'git config "%s" "%s" --%s',
                $config,
                $value,
                $section
            )
        );
    }

    /**
     * @param string $config
     * @param string $section
     *
     * @return string
     */
    public function getGitConfig($config, $section = 'local')
    {
        return trim(
            $this->processHelper->runCommand(
                sprintf(
                    'git config --%s --get %s',
                    $section,
                    $config
                ),
                true
            )
        );
    }

    public function remoteExists($name, $expectedUrl = null)
    {
        if (!$this->hasGitConfig(sprintf('remote.%s.url', $name))) {
            return false;
        }

        if (null === $expectedUrl) {
            return true;
        }

        return $expectedUrl === $this->getGitConfig(sprintf('remote.%s.url', $name));
    }

    /**
     * @param string $name
     * @param string $url
     * @param string $pushUrl Optional push-url
     */
    public function setRemote($name, $url, $pushUrl = null)
    {
        if (!$this->hasGitConfig('remote.'.$name.'.url')) {
            $this->processHelper->runCommand(['git', 'remote', 'add', $name, $url]);
        } else {
            $this->setGitConfig('remote.'.$name.'.url', $url, true);
        }

        if ($pushUrl) {
            $this->setGitConfig('remote.'.$name.'.pushurl', $pushUrl, true);
        }
    }

    /**
     * @param string $name
     *
     * @return array [host, vendor, repo]
     */
    public function getRemoteInfo($name)
    {
        $info = [
            'host' => '',
            'vendor' => '',
            'repo' => '',
        ];

        $output = $this->getGitConfig('remote.'.$name.'.url');

        if (0 === stripos($output, 'http://') || 0 === stripos($output, 'https://')) {
            $url = parse_url($output);

            $info['host'] = $url['host'];
            $info['path'] = ltrim($url['path'], '/');
        } elseif (preg_match('%^(?:(?:git|ssh)://)?[^@]+@(?P<host>[^:]+):(?P<path>[^$]+)$%', $output, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        }

        if (isset($info['path'])) {
            $dirs = array_slice(explode('/', $info['path']), -2, 2);

            $info['vendor'] = $dirs[0];
            $info['repo'] = substr($dirs[1], -4, 4) === '.git' ? substr($dirs[1], 0, -4) : $dirs[1];

            unset($info['path']);
        }

        return $info;
    }
}
