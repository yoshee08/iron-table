<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Services\ScenarioChecker;
use InvalidArgumentException;
use Tests\TestCase;

class ScenarioCheckerTest extends TestCase
{
    private const ORG = [
        'org.name' => 'Example Corp',
        'org.domain.example_user' => 'analyst@example.com',
        'org.channel.report' => 'security@example.com',
    ];

    private const CONCEPTS = ['reporting_procedure', 'severity_table', 'containment', 'external_reporting'];

    /**
     * The example scenario, with each search text replaced one time.
     *
     * @param  array<string, string>  $changes
     */
    private function source(array $changes = []): string
    {
        $source = (string) file_get_contents(resource_path('scenarios/phish-to-mailbox-rule.yaml'));
        foreach ($changes as $search => $replace) {
            $this->assertSame(1, substr_count($source, $search), $search);
            $source = str_replace($search, $replace, $source);
        }

        return $source;
    }

    /**
     * @param  array<string, string>  $changes
     * @param  array<string, string>  $org
     * @param  list<string>|null  $concepts
     * @return list<string>
     */
    private function errors(array $changes = [], array $org = self::ORG, ?array $concepts = self::CONCEPTS): array
    {
        $checker = new ScenarioChecker;

        return $checker->check($checker->read($this->source($changes)), $org, $concepts)['errors'];
    }

    public function test_the_example_scenario_passes_and_placeholders_are_replaced(): void
    {
        $checker = new ScenarioChecker;
        $result = $checker->check($checker->read($this->source()), self::ORG, self::CONCEPTS);

        $this->assertSame([], $result['errors']);
        $this->assertSame('analyst@example.com', $result['facts']['victim_mailbox']);
        $this->assertSame('10:40', $result['scenario']['injects'][0]['at']);
        $this->assertStringContainsString('Mailbox: analyst@example.com', $result['scenario']['injects'][0]['artifacts'][0]['content']);
        $this->assertSame('Report sent through security@example.com', $result['scenario']['rubric']['inj-1'][0]['item']);
    }

    public function test_a_placeholder_without_a_value_is_an_error(): void
    {
        $org = self::ORG;
        unset($org['org.channel.report']);

        $this->assertSame(
            ['{{org.channel.report}} has no value (used in rubric). Add it to the organization profile.'],
            $this->errors(org: $org),
        );
        $this->assertSame(
            ['{{facts.victim_hostname}} has no value (used in inject inj-1). Add it to facts. A fact cannot use another fact.'],
            $this->errors(['Computer: {{facts.victim_host}}' => 'Computer: {{facts.victim_hostname}}']),
        );
    }

    public function test_an_inject_cannot_be_earlier_than_the_inject_before_it(): void
    {
        $this->assertSame(
            ['Inject inj-2a comes after inject inj-1 (10:40), but its time (10:30) is earlier.'],
            $this->errors(['disable_time: "11:15"' => 'disable_time: "10:30"']),
        );
        // inj-2a and inj-2b are different branches, so their times do not have to agree.
        $this->assertSame([], $this->errors(['spread_time: "11:30"' => 'spread_time: "11:10"']));
        $this->assertSame(
            ['Inject inj-1: the time (at) must be HH:MM, for example 09:05. It is "10.40".'],
            $this->errors(['rule_found_time: "10:40"' => 'rule_found_time: "10.40"']),
        );
    }

    public function test_each_branch_must_go_to_an_inject_that_exists(): void
    {
        $this->assertSame(
            ['Inject inj-1: a branch goes to inj-9, which does not exist.'],
            $this->errors(['goto: inj-2b' => 'goto: inj-9']),
        );
    }

    public function test_each_expected_plan_concept_must_be_in_the_plan(): void
    {
        $errors = $this->errors(concepts: ['reporting_procedure', 'severity_table', 'external_reporting']);
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('No section of the IR plan has the plan concept containment (used in inject inj-2a, inj-2b).', $errors[0]);

        $this->assertSame(['There is no IR plan. Upload the plan, then set the plan concepts on its sections.'], $this->errors(concepts: null));
    }

    public function test_a_fixed_value_that_is_not_a_fact_is_an_error(): void
    {
        $this->assertSame(
            ['Inject inj-1, artifact 1: "FIN-PC08" is a fixed value that is not a fact. Add it to facts and use {{facts.name}}.'],
            $this->errors(['Computer: {{facts.victim_host}}' => 'Computer: FIN-PC08']),
        );
        $this->assertSame(
            ['Inject inj-2a, text: "09:20" is a fixed value that is not a fact. Add it to facts and use {{facts.name}}.'],
            $this->errors(['at {{facts.signin_time}}, seven' => 'at 09:20, seven']),
        );
    }

    public function test_artifacts_need_content_and_the_rubric_needs_real_injects(): void
    {
        $this->assertSame(
            ['Inject inj-1, artifact 1 has no content. Put the artifact text in content: one YAML file holds the whole scenario.'],
            $this->errors(["        content: |\n          Mailbox: {{facts.victim_mailbox}}\n          Computer: {{facts.victim_host}}\n          Rule: forward all messages to {{facts.forward_address}}\n          The user says: \"I did not make this rule.\"\n" => "        file: artifacts/ticket.md\n"]),
        );
        $this->assertSame(
            ['The rubric has items for inject inj-7, which does not exist.'],
            $this->errors(['  inj-3:' => '  inj-7:']),
        );
    }

    public function test_a_file_with_the_wrong_structure_is_refused(): void
    {
        $checker = new ScenarioChecker;
        $cases = [
            'meta: [' => 'The file is not valid YAML',
            'title: only a title' => 'The scenario must start with a meta block',
            "meta:\n  title: No id\n  version: 1\ninjects: []" => 'meta.id is missing',
            $this->source(['id: inj-2b' => 'id: inj-2a']) => 'Two injects have the id inj-2a.',
            $this->source(['goto: inj-2b' => 'target: inj-2b']) => 'Inject inj-1, branches item 2 has no goto.',
            $this->source(['    end: true' => "    end: true\n    timer: 0"]) => 'Inject inj-3: timer must be a whole number of minutes, 1 to 240.',
        ];

        foreach ($cases as $yaml => $message) {
            try {
                $checker->read($yaml);
                $this->fail("No error for: $message");
            } catch (InvalidArgumentException $e) {
                $this->assertStringStartsWith($message, $e->getMessage());
            }
        }
    }
}
