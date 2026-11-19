<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Services\Ai\AiException;
use App\Services\Ai\ScenarioDrafter;
use App\Services\ScenarioChecker;
use Symfony\Component\Yaml\Yaml;
use Tests\FakeAi;
use Tests\TestCase;

class ScenarioDrafterTest extends TestCase
{
    public const OUTLINE = [
        'title' => 'Ransomware on the file server',
        'briefing' => 'It is Monday morning. Staff start work.',
        'objectives' => ['Report the incident.', 'Contain the server.'],
        'injects' => ['Files cannot be opened', 'A ransom note appears', 'Backups are old'],
    ];

    public const FACTS = [
        'facts' => [
            ['name' => 'File Server', 'value' => 'FS-01'],
            ['name' => 'attacker_ip', 'value' => '198.51.100.7'],
            ['name' => 'bad name!', 'value' => ''],
        ],
        'times' => ['08:10', '08:45', '09:30'],
    ];

    /**
     * @return array<string, mixed>
     */
    private function started(): array
    {
        return (new ScenarioDrafter(new FakeAi([self::OUTLINE, self::FACTS])))->start('ransomware', 'beginner', 3);
    }

    public function test_the_first_steps_write_the_outline_and_the_facts(): void
    {
        $scenario = $this->started();

        $this->assertSame('ransomware-on-the-file-server', $scenario['meta']['id']);
        $this->assertSame('0.1.0', $scenario['meta']['version']);
        $this->assertSame(['file_server' => 'FS-01', 'attacker_ip' => '198.51.100.7', 'inject_1_time' => '08:10', 'inject_2_time' => '08:45', 'inject_3_time' => '09:30'], $scenario['facts']);
        $this->assertSame(['id' => 'inj-2', 'at' => '{{facts.inject_2_time}}', 'title' => 'A ransom note appears'], $scenario['injects'][1]);

        // The draft is a valid scenario and passes the consistency check.
        $checker = new ScenarioChecker;
        $this->assertSame([], $checker->check($checker->read(Yaml::dump($scenario, 10, 2)), [], [])['errors']);
    }

    public function test_an_inject_uses_placeholders_and_only_the_plan_concepts(): void
    {
        $ai = new FakeAi([[
            'body' => 'Staff cannot open files on FS-01. The help desk calls {{org.role.ciso.name}}.',
            'questions' => ['Who do you call?', 'What severity is this?'],
            'expected_plan' => ['reporting_procedure'],
        ]]);
        $scenario = (new ScenarioDrafter($ai))->inject($this->started(), 0, ['reporting_procedure', 'severity_table'], ['org.name', 'org.role.ciso.name']);

        // The fact value FS-01 goes back to its placeholder.
        $this->assertSame('Staff cannot open files on {{facts.file_server}}. The help desk calls {{org.role.ciso.name}}.', $scenario['injects'][0]['body']);
        $this->assertSame(['reporting_procedure'], $scenario['injects'][0]['expected_plan']);
        // The AI gets placeholder names, never profile values.
        $this->assertStringContainsString('{{org.role.ciso.name}}', $ai->requests[0]);
        $this->assertStringContainsString('{{facts.file_server}} = FS-01', $ai->requests[0]);
    }

    public function test_an_artifact_and_a_rubric_are_added(): void
    {
        $scenario = (new ScenarioDrafter(new FakeAi([
            ['type' => 'log', 'title' => 'Firewall log', 'content' => "08:09 allow 198.51.100.7 -> FS-01 port 445\n"],
            ['items' => [['item' => 'The team reports to security', 'points' => 5], ['item' => 'The team isolates the server', 'points' => 0]]],
        ])));
        $result = $scenario->rubric($scenario->artifact($this->started(), 0), 0);

        $this->assertSame("08:09 allow {{facts.attacker_ip}} -> {{facts.file_server}} port 445\n", $result['injects'][0]['artifacts'][0]['content']);
        $this->assertSame([['item' => 'The team reports to security', 'points' => 3], ['item' => 'The team isolates the server', 'points' => 1]], $result['rubric']['inj-1']);
    }

    public function test_an_answer_in_the_wrong_format_is_refused(): void
    {
        $this->expectException(AiException::class);

        (new ScenarioDrafter(new FakeAi([['title' => 'Only a title']])))->start('phishing', 'beginner', 3);
    }
}
