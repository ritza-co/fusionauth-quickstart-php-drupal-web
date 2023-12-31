<?php

declare(strict_types=1);

namespace Drush\Preflight;

use Drush\Commands\DrushCommands;
use Symfony\Component\Filesystem\Path;

/**
 * RedispatchToSiteLocal forces an `exec` to the site-local Drush if it
 * exist.  We must do this super-early, before loading Drupal's autoload
 * file.  If we do not, we will crash unless the site-local Drush and the
 * global Drush are using the exact same versions of all dependencies, which
 * will rarely line up sufficiently to prevent problems.
 */
class RedispatchToSiteLocal
{
    /**
     * Determine if a local redispatch is needed, and do so if it is.
     *
     * @param array $argv The commandline arguments
     * @param string $root The selected site root or false if none
     * @param string $vendor The path to the vendor directory
     * @param PreflightLog $preflightLog A basic logger.
     *
     * @return array{preflightDidRedispatch: bool, exitStatus: int}
     */
    public static function redispatchIfSiteLocalDrush(array $argv, string $root, string $vendor, PreflightLog $preflightLog): array
    {

        // Try to find the site-local Drush. If there is none, we are done.
        $siteLocalDrush = static::findSiteLocalDrush($root);
        if (!$siteLocalDrush) {
            return [false, DrushCommands::EXIT_SUCCESS];
        }

        // If the site-local Drush is us, then we do not need to redispatch.
        if (Path::isBasePath($vendor, $siteLocalDrush)) {
            return [false, DrushCommands::EXIT_SUCCESS];
        }

        // Do another special check to detect symlinked Drush folder similar
        // to what the SUT sets up for Drush functional tests.
        if (dirname($vendor) === dirname($siteLocalDrush)) {
            return [false, DrushCommands::EXIT_SUCCESS];
        }

        // Redispatch!
        $command = escapeshellarg($siteLocalDrush);
        $preflightLog->log(dt('Redispatch to site-local Drush: !cmd.', ['!cmd' => $command]));
        array_shift($argv);
        $args = array_map(
            function ($item) {
                return escapeshellarg($item);
            },
            $argv
        );
        $command .= ' ' . implode(' ', $args);
        passthru($command, $status);

        return [true, $status];
    }

    /**
     * Find a site-local Drush, if there is one in the selected site's
     * vendor directory.
     *
     * @param $root The selected site root
     */
    protected static function findSiteLocalDrush(string $root)
    {
        $candidates = [
            "$root/vendor/drush/drush/drush",
            dirname($root) . '/vendor/drush/drush/drush',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
    }
}
