<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * Writes a scenario draft in small steps (spec section 9.2), so that a small
 * local model can do it: an outline, the facts, then one inject text, one
 * artifact or one rubric at a time. The moderator checks each step.
 *
 * The AI never sees the IR plan or the organization profile values: it gets
 * only the names of the plan concepts and of the {{org.*}} placeholders.
 */
class ScenarioDrafter
{
    private const INSTRUCTIONS = <<<'TEXT'
        You write one part of an incident response tabletop exercise for IRon Table.
        Rules:
        - Write short, clear sentences in the present tense.
        - Use only fictional people, systems and addresses. Never use a real company name.
        - Do only the task in the request. Answer only with JSON.
        TEXT;

    private const ARTIFACT_TYPES = ['email', 'log', 'ticket', 'chat', 'report'];

    public function __construct(private Provider $ai) {}

    /**
     * Steps 1 and 2: the outline and the facts. Returns a new scenario.
     *
     * @return array<string, mixed>
     */
    public function start(string $incident, string $difficulty, int $injects): array
    {
        $outline = $this->ai->json(self::INSTRUCTIONS, <<<TEXT
            Task: write the outline of a tabletop exercise.
            Incident type: $incident
            Difficulty: $difficulty
            Give a title, a briefing of 2 or 3 sentences (the normal situation before the incident starts),
            2 or 3 objectives (what the exercise tests), and exactly $injects inject titles in time order.
            TEXT, [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'briefing' => ['type' => 'string'],
                'objectives' => ['type' => 'array', 'items' => ['type' => 'string']],
                'injects' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'briefing', 'objectives', 'injects'],
        ]);

        $titles = array_slice($outline['injects'], 0, $injects);
        $list = implode("\n", array_map(fn ($n, $title) => ($n + 1).'. '.$title, array_keys($titles), $titles));

        $answer = $this->ai->json(self::INSTRUCTIONS, <<<TEXT
            Task: write the facts of this exercise. Facts are the single source of truth: host names, IP addresses,
            account names, people (by job title) and the root cause.
            Title: {$outline['title']}
            Injects:
            $list
            Give 5 to 8 facts. Each name uses lowercase letters and underscores, for example victim_host.
            Also give one time (HH:MM, 24-hour clock) for each inject, in the same order. Each time is later than the time before it.
            TEXT, [
            'type' => 'object',
            'properties' => [
                'facts' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string'], 'value' => ['type' => 'string']],
                    'required' => ['name', 'value'],
                ]],
                'times' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['facts', 'times'],
        ]);

        $facts = [];
        foreach ($answer['facts'] as $fact) {
            $name = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($fact['name'])), '_');
            if (preg_match('/^[a-z]/', $name) && ! isset($facts[$name]) && trim($fact['value']) !== '') {
                $facts[$name] = trim($fact['value']);
            }
        }

        $scenario = [
            'meta' => [
                'id' => Str::limit(Str::slug($outline['title']), 80, '') ?: 'ai-draft',
                'title' => Str::limit($outline['title'], 200, ''),
                'version' => '0.1.0',
                'author' => 'AI draft - check every part',
                'duration_minutes' => 60,
                'difficulty' => $difficulty,
            ],
            'objectives' => $outline['objectives'],
            'facts' => $facts,
            'briefing' => $outline['briefing'],
            'injects' => [],
        ];
        foreach ($titles as $n => $title) {
            $time = 'inject_'.($n + 1).'_time';
            $scenario['facts'][$time] = $answer['times'][$n] ?? '';
            $scenario['injects'][] = ['id' => 'inj-'.($n + 1), 'at' => '{{facts.'.$time.'}}', 'title' => $title];
        }

        return $scenario;
    }

    /**
     * Step 3: the text, the questions and the expected plan concepts of one inject.
     *
     * @param  array<string, mixed>  $scenario
     * @param  list<string>  $concepts  the plan concepts of the current IR plan
     * @param  list<string>  $placeholders  the names of the organization placeholders, for example org.role.ciso.name
     * @return array<string, mixed>
     */
    public function inject(array $scenario, int $n, array $concepts, array $placeholders): array
    {
        $inject = $scenario['injects'][$n];
        $before = implode("\n", array_map(
            fn (array $earlier) => '- '.$earlier['title'].(isset($earlier['body']) ? ': '.$earlier['body'] : ''),
            array_slice($scenario['injects'], 0, $n),
        )) ?: '(none - this is the first inject)';
        $org = $placeholders === [] ? '(none)' : implode(', ', array_map(fn ($name) => '{{'.$name.'}}', $placeholders));
        $conceptList = $concepts === [] ? 'reporting_procedure, severity_table, containment, external_reporting' : implode(', ', $concepts);

        $answer = $this->ai->json(self::INSTRUCTIONS, <<<TEXT
            Task: write the text that participants see for one inject, and 2 questions for them.
            Scenario: {$scenario['meta']['title']}
            Briefing: {$scenario['briefing']}
            Facts:
            {$this->factList($scenario)}
            Earlier injects:
            $before
            This inject: {$inject['title']}
            Write 2 to 4 sentences. To use a fact, write its placeholder, for example {{facts.victim_host}}. Do not write the value.
            To name the organization or a person in it, use one of these placeholders: $org
            Also choose 1 or 2 plan concepts that the participants must use, from: $conceptList
            TEXT, [
            'type' => 'object',
            'properties' => [
                'body' => ['type' => 'string'],
                'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'expected_plan' => ['type' => 'array', 'items' => $concepts === []
                    ? ['type' => 'string']
                    : ['type' => 'string', 'enum' => $concepts]],
            ],
            'required' => ['body', 'questions', 'expected_plan'],
        ]);

        $scenario['injects'][$n]['body'] = $this->useFacts($scenario, $answer['body']);
        $scenario['injects'][$n]['questions'] = array_slice($answer['questions'], 0, 3);
        $scenario['injects'][$n]['expected_plan'] = array_values(array_unique(array_slice($answer['expected_plan'], 0, 3)));

        return $scenario;
    }

    /**
     * Step 4: one artifact (email, log, ticket, chat or report) for one inject.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function artifact(array $scenario, int $n): array
    {
        $inject = $scenario['injects'][$n];
        $body = $inject['body'] ?? '(no text yet)';

        $answer = $this->ai->json(self::INSTRUCTIONS, <<<TEXT
            Task: write one artifact that participants receive with this inject: an email, a log extract, a help desk ticket,
            a chat message or a short report. Keep it under 15 lines.
            Facts:
            {$this->factList($scenario)}
            Inject: {$inject['title']}
            Inject text: $body
            To use a fact, write its placeholder, for example {{facts.victim_host}}. Do not write the value.
            TEXT, [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => self::ARTIFACT_TYPES],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
            ],
            'required' => ['type', 'title', 'content'],
        ]);

        $scenario['injects'][$n]['artifacts'][] = [
            'type' => $answer['type'],
            'title' => $answer['title'],
            'content' => $this->useFacts($scenario, $answer['content']),
        ];

        return $scenario;
    }

    /**
     * A picture artifact for one inject: the AI writes the title and the image prompt.
     * The image provider makes the picture later.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function picture(array $scenario, int $n): array
    {
        $inject = $scenario['injects'][$n];
        $body = $inject['body'] ?? '(no text yet)';

        $answer = $this->ai->json(self::INSTRUCTIONS, <<<TEXT
            Task: describe one photo that participants see with this inject, for example a door that is open,
            a USB stick on the ground or a laptop screen from a distance.
            Facts:
            {$this->factList($scenario)}
            Inject: {$inject['title']}
            Inject text: $body
            Give a short title and an image prompt of 1 to 3 sentences: the scene, the place, the light and the camera view.
            The photo must not contain readable text, logos, real brands or recognizable people.
            TEXT, [
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string'], 'prompt' => ['type' => 'string']],
            'required' => ['title', 'prompt'],
        ]);

        $scenario['injects'][$n]['artifacts'][] = ['type' => 'image', 'title' => $answer['title'], 'prompt' => $answer['prompt']];

        return $scenario;
    }

    /**
     * Step 5: the rubric items of one inject.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function rubric(array $scenario, int $n): array
    {
        $inject = $scenario['injects'][$n];
        $body = $inject['body'] ?? '(no text yet)';
        $concepts = implode(', ', $inject['expected_plan'] ?? []) ?: '(none)';

        $answer = $this->ai->json(self::INSTRUCTIONS, <<<TEXT
            Task: write 2 or 3 scoring items for this inject. Each item is one action that a moderator can see the team do.
            Give each item 1, 2 or 3 points.
            Inject: {$inject['title']}
            Inject text: $body
            Plan concepts that the team must use: $concepts
            TEXT, [
            'type' => 'object',
            'properties' => [
                'items' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['item' => ['type' => 'string'], 'points' => ['type' => 'integer']],
                    'required' => ['item', 'points'],
                ]],
            ],
            'required' => ['items'],
        ]);

        $scenario['rubric'][$inject['id']] = array_map(
            fn (array $item) => ['item' => $item['item'], 'points' => max(1, min(3, $item['points']))],
            array_slice($answer['items'], 0, 3),
        );

        return $scenario;
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function factList(array $scenario): string
    {
        $lines = [];
        foreach ($scenario['facts'] ?? [] as $name => $value) {
            $lines[] = "- {{facts.$name}} = $value";
        }

        return implode("\n", $lines) ?: '(none)';
    }

    /**
     * A small model sometimes writes a fact value instead of its placeholder.
     * Put the placeholder back, so that the facts stay the single source of truth.
     *
     * @param  array<string, mixed>  $scenario
     */
    private function useFacts(array $scenario, string $text): string
    {
        $facts = array_filter(array_map('strval', $scenario['facts'] ?? []), fn (string $value) => mb_strlen($value) >= 3);
        uasort($facts, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($facts as $name => $value) {
            $text = (string) preg_replace('/(?<![\w.:{])'.preg_quote($value, '/').'(?![\w:}]|\.\d)/', '{{facts.'.$name.'}}', $text);
        }

        return $text;
    }
}
