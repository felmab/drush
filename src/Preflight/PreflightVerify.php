<?php
namespace Drush\Preflight;

use Drush\Config\Environment;
use Drush\Utils\StringUtils;

/**
 * Helper methods to verify preflight state.
 */
class PreflightVerify
{
    /**
     * Throw an exception if the environment is not right for running Drush.
     *
     * @param Environment $environment
     */
    public function verify(Environment $environment)
    {
        // Fail fast if the PHP version is not at least 7.4.0.
        // We'll come back and check this again later, in case someone
        // set a higher value in a configuration file.
        $this->confirmPhpVersion('7.4.0');

        // Fail if this is not a CLI php
        $this->confirmUsingCLI($environment);

        // Fail if any mandatory functions have been disabled, or any
        // illegal options have been set in php.ini.
        $this->checkPhpIni();
    }

    /**
     * Fail fast if the php version does not meet the minimum requirements.
     *
     * @param string $minimumPhpVersion
     *   The minimum allowable php version
     */
    public function confirmPhpVersion($minimumPhpVersion)
    {
        if (version_compare(phpversion(), $minimumPhpVersion) < 0 && !getenv('DRUSH_NO_MIN_PHP')) {
            throw new \Exception(StringUtils::interpolate('Your command line PHP installation is too old. Drush requires at least PHP {version}. To suppress this check, set the environment variable DRUSH_NO_MIN_PHP=1', ['version' => $minimumPhpVersion]));
        }
    }

    /**
     * Fail if not being run from the command line.
     *
     * @param Environment $environment
     */
    protected function confirmUsingCLI(Environment $environment)
    {
        if (!$environment->verifyCLI()) {
            throw new \Exception(StringUtils::interpolate('Drush is designed to run via the command line.'));
        }
    }

    /**
     * Evaluate the environment before command bootstrapping
     * begins.  If the php environment is too restrictive, then
     * notify the user that a setting change is needed and abort.
     */
    protected function checkPhpIni()
    {
        $ini_checks = ['safe_mode' => '', 'open_basedir' => ''];

        // Test to insure that certain php ini restrictions have not been enabled
        $prohibited_list = [];
        foreach ($ini_checks as $prohibited_mode => $disallowed_value) {
            $ini_value = ini_get($prohibited_mode);
            if ($this->invalidIniValue($ini_value, $disallowed_value)) {
                $prohibited_list[] = $prohibited_mode;
            }
        }
        if (!empty($prohibited_list)) {
            throw new \Exception(StringUtils::interpolate('The following restricted PHP modes have non-empty values: {prohibited_list}. This configuration is incompatible with drush.  {php_ini_msg}', ['prohibited_list' => implode(' and ', $prohibited_list), 'php_ini_msg' => $this->loadedPhpIniMessage()]));
        }
    }

    /**
     * Determine whether an ini value is valid based on the criteria.
     *
     * @param string $ini_value
     *   The value of the ini setting being tested.
     * @param string|string[] $disallowed_value
     *   The value that the ini seting cannot be, or a list of disallowed
     *   values that cannot appear in the setting.
     * @return bool
     */
    protected function invalidIniValue($ini_value, $disallowed_value)
    {
        if (empty($disallowed_value)) {
            return !empty($ini_value) && (strcasecmp($ini_value, 'off') != 0);
        } else {
            foreach ($disallowed_value as $test_value) {
                if (preg_match('/(^|,)' . $test_value . '(,|$)/', $ini_value)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns a localizable message about php.ini that
     * varies depending on whether the php_ini_loaded_file()
     * is available or not.
     */
    protected function loadedPhpIniMessage()
    {
        if (function_exists('php_ini_loaded_file')) {
            return StringUtils::interpolate('Please check your configuration settings in !phpini.', ['!phpini' => php_ini_loaded_file()]);
        } else {
            return StringUtils::interpolate('Please check your configuration settings in your php.ini file.');
        }
    }
}
