<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_coursefiles\local;

/**
 * Connecting a desktop app (VisuDesk) to Moodle without copying a key: the teacher signs in to Moodle as usual
 * (also with single sign-on), confirms, and Moodle hands the token for the app's service to the app on the same
 * computer. Works like the launch of the Moodle mobile app: the app sends a random passport and only accepts a token
 * that comes back with it. The token only ever goes to a loopback address (127.0.0.1, localhost, ::1).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connect {
    /** Services an app may connect to (short names). */
    public const SERVICES = ['visudesk'];

    /** Path the app listens on. */
    public const RETURNPATH = '/verbunden';

    /**
     * Checks the address the token goes to: only the app on this computer.
     *
     * @param string $url
     * @return bool
     */
    public static function valid_return(string $url): bool {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'http' || empty($parts['port'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($host, ['127.0.0.1', 'localhost', '[::1]'], true)) {
            return false;
        }
        $port = (int)$parts['port'];
        return $port >= 1024 && $port <= 65535 && ($parts['path'] ?? '') === self::RETURNPATH;
    }

    /**
     * The service record, if apps may connect to it.
     *
     * @param string $shortname
     * @return \stdClass
     */
    public static function service(string $shortname): \stdClass {
        global $DB;
        if (!in_array($shortname, self::SERVICES, true)) {
            throw new \moodle_exception('connectnoservice', 'local_coursefiles', '', $shortname);
        }
        $service = $DB->get_record('external_services', ['shortname' => $shortname, 'enabled' => 1]);
        if (!$service) {
            throw new \moodle_exception('connectnoservice', 'local_coursefiles', '', $shortname);
        }
        return $service;
    }

    /**
     * The token of the current user for the service: the existing one or a new one. Moodle checks, as on the
     * security keys page, that the user may create tokens and may use the service.
     *
     * @param \stdClass $service
     * @return string
     */
    public static function token(\stdClass $service): string {
        try {
            $token = \core_external\util::generate_token_for_current_user($service);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('connectnotallowed', 'local_coursefiles', '', $service->name, $e->getMessage());
        }
        \core_external\util::log_token_request($token);
        return $token->token;
    }

    /**
     * The address the browser goes to afterwards: the app on this computer, with passport, site and token.
     *
     * @param string $return checked with valid_return
     * @param string $passport
     * @param string $token
     * @return string
     */
    public static function callback(string $return, string $passport, string $token): string {
        global $CFG;
        // Moodle sets arg_separator.output to &amp; – the browser needs a plain &.
        return $return . '?' . http_build_query(['passport' => $passport, 'site' => $CFG->wwwroot, 'token' => $token], '', '&');
    }
}
