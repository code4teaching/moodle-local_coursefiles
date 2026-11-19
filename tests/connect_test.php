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

namespace local_coursefiles;

use local_coursefiles\local\connect;

/**
 * Tests for connecting VisuDesk without copying a key.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(connect::class)]
final class connect_test extends \advanced_testcase {
    /**
     * Creates the web service VisuDesk.
     *
     * @param int $restricted
     * @return \stdClass
     */
    private function create_service(int $restricted = 0): \stdClass {
        global $DB;
        $id = $DB->insert_record('external_services', (object)[
            'name' => 'VisuDesk', 'shortname' => 'visudesk', 'enabled' => 1, 'restrictedusers' => $restricted,
            'requiredcapability' => '', 'component' => null, 'timecreated' => time(), 'timemodified' => time(),
            'downloadfiles' => 1, 'uploadfiles' => 1,
        ]);
        return $DB->get_record('external_services', ['id' => $id]);
    }

    /**
     * The token only goes to the app on this computer.
     */
    public function test_valid_return(): void {
        $this->assertTrue(connect::valid_return('http://127.0.0.1:8765/verbunden'));
        $this->assertTrue(connect::valid_return('http://localhost:8765/verbunden'));
        $invalid = [
            'https://127.0.0.1:8765/verbunden',
            'http://127.0.0.1/verbunden',
            'http://127.0.0.1:80/verbunden',
            'http://evil.example:8765/verbunden',
            'http://127.0.0.1.evil.example:8765/verbunden',
            'http://user@127.0.0.1:8765/verbunden',
            'http://127.0.0.1:8765/verbunden?x=1',
            'http://127.0.0.1:8765/other',
            'javascript:alert(1)',
            '',
        ];
        foreach ($invalid as $url) {
            $this->assertFalse(connect::valid_return($url), $url);
        }
    }

    /**
     * Only the listed and enabled services.
     */
    public function test_service(): void {
        $this->resetAfterTest();
        $this->create_service();
        $this->assertSame('visudesk', connect::service('visudesk')->shortname);
        $this->expectException(\moodle_exception::class);
        connect::service('moodle_mobile_app');
    }

    /**
     * A user who may create tokens gets one, and the same one again next time.
     */
    public function test_token(): void {
        $this->resetAfterTest();
        $service = $this->create_service();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/webservice:createtoken', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance());
        $this->setUser($user);

        $token = connect::token($service);
        $this->assertSame(32, strlen($token));
        $this->assertSame($token, connect::token($service));
        $url = connect::callback('http://127.0.0.1:8765/verbunden', 'abc', $token);
        $this->assertStringStartsWith('http://127.0.0.1:8765/verbunden?passport=abc&site=', $url);
        $this->assertStringEndsWith('&token=' . $token, $url);
    }

    /**
     * Without the capability to create tokens there is no token.
     */
    public function test_token_not_allowed(): void {
        $this->resetAfterTest();
        $service = $this->create_service();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        connect::token($service);
    }

    /**
     * A restricted service needs the user in its list of authorised users.
     */
    public function test_token_restricted(): void {
        $this->resetAfterTest();
        $service = $this->create_service(1);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/webservice:createtoken', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance());
        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        connect::token($service);
    }
}
