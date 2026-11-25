<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a scenario YAML file (spec section 6) and does the consistency check
 * (spec section 6.3).
 *
 * read() refuses a file with the wrong structure. check() returns a list of
 * errors that the moderator must correct before a session can start, and the
 * scenario with all placeholders replaced.
 */
class ScenarioChecker
{
    /** The file name of a picture artifact: no folders, only these image types. */
    public const IMAGE_NAME = '/^[A-Za-z0-9_-]{1,80}\.(png|jpg|jpeg|webp)$/';

    private const PLACEHOLDER = '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/';

    private const TIME = '/^([01]\d|2[0-3]):([0-5]\d)$/';

    /** Values that must come from the facts: IPv4 addresses, host names such as PC1 or SRV-02, and times such as 09:12. */
    private const FIXED_VALUES = '/\b(?:\d{1,3}\.){3}\d{1,3}\b|\b[A-Z]{2,}(?:-[A-Z]+)*-?\d+\b|(?<![\d:])(?:[01]\d|2[0-3]):[0-5]\d(?![\d:])/';

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, array<string, true>> placeholder => places where it has no value */
    private array $missing = [];

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException with a message for the moderator
     */
    public function read(string $yaml): array
    {
        try {
            $data = Yaml::parse($yaml);
        } catch (ParseException $e) {
            $this->fail('The file is not valid YAML: '.$e->getMessage());
        }

        if (! is_array($data) || ! is_array($data['meta'] ?? null)) {
            $this->fail('The scenario must start with a meta block (spec section 6.1).');
        }

        foreach (['id' => 100, 'title' => 255, 'version' => 20] as $key => $length) {
            $value = $data['meta'][$key] ?? null;
            if (! is_scalar($value) || trim((string) $value) === '' || $length < mb_strlen((string) $value)) {
                $this->fail("meta.$key is missing or longer than $length characters.");
            }
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $data['meta']['id'])) {
            $this->fail('meta.id must use only lowercase letters, digits and hyphens, for example phish-to-mailbox-rule.');
        }
        if (! preg_match('/^[0-9A-Za-z.+-]+$/', (string) $data['meta']['version'])) {
            $this->fail('meta.version must use only letters, digits and the characters . + -, for example 1.0.0.');
        }

        $this->optionalText($data, 'briefing', 'The scenario');
        $this->optionalTextList($data, 'objectives', 'The scenario');

        if (isset($data['facts'])) {
            if (! is_array($data['facts']) || array_is_list($data['facts'])
                || array_filter($data['facts'], fn ($value) => $value !== null && ! is_scalar($value)) !== []) {
                $this->fail('facts must be a list of names and values, for example "victim_host: PC1".');
            }
        }

        if (! is_array($data['injects'] ?? null) || ! array_is_list($data['injects']) || $data['injects'] === []) {
            $this->fail('The scenario must have injects: a list of events.');
        }

        $ids = [];
        foreach ($data['injects'] as $n => $inject) {
            $where = 'Inject '.($n + 1);
            if (! is_array($inject)) {
                $this->fail("$where must be a block with id, title and at.");
            }
            foreach (['id', 'title', 'at'] as $key) {
                if (! is_scalar($inject[$key] ?? null) || trim((string) $inject[$key]) === '') {
                    $this->fail("$where has no $key.");
                }
            }

            $id = (string) $inject['id'];
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                $this->fail("$where: the id \"$id\" must use only letters, digits, hyphens and underscores.");
            }
            if (isset($ids[$id])) {
                $this->fail("Two injects have the id $id.");
            }
            $ids[$id] = true;
            $data['injects'][$n]['id'] = $id;

            $where = "Inject $id";
            $this->optionalText($inject, 'body', $where);
            $this->optionalText($inject, 'next', $where);
            $this->optionalText($inject, 'phase', $where);
            $this->optionalTextList($inject, 'audience', $where);
            $this->optionalTextList($inject, 'questions', $where);
            $this->optionalTextList($inject, 'expected_plan', $where);
            $this->optionalBlocks($inject, 'artifacts', $where, ['type', 'title', 'content', 'prompt', 'image'], []);
            foreach ($inject['artifacts'] ?? [] as $a => $artifact) {
                if (isset($artifact['image']) && ! preg_match(self::IMAGE_NAME, (string) $artifact['image'])) {
                    $this->fail("$where, artifacts item ".($a + 1).': image must be a file name such as door.png (letters, digits, - and _; png, jpg or webp).');
                }
            }
            $this->optionalBlocks($inject, 'branches', $where, ['when', 'goto'], ['when', 'goto']);
            if (isset($inject['timer']) && (! is_int($inject['timer']) || $inject['timer'] < 1 || $inject['timer'] > 240)) {
                $this->fail("$where: timer must be a whole number of minutes, 1 to 240.");
            }
            if (isset($inject['day']) && (! is_int($inject['day']) || $inject['day'] < 1)) {
                $this->fail("$where: day must be a whole number, 1 or more.");
            }
        }

        if (isset($data['rubric'])) {
            if (! is_array($data['rubric']) || array_is_list($data['rubric'])) {
                $this->fail('rubric must have one list of items for each inject id, for example "inj-1:".');
            }
            foreach ($data['rubric'] as $injectId => $items) {
                if (! is_array($items) || ! array_is_list($items)) {
                    $this->fail("rubric $injectId must be a list of items.");
                }
                foreach ($items as $n => $item) {
                    if (! is_array($item) || ! is_scalar($item['item'] ?? null) || ! is_int($item['points'] ?? null) || $item['points'] < 0) {
                        $this->fail("rubric $injectId, item ".($n + 1).' must have item (text) and points (a whole number, 0 or more).');
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data  a scenario from read()
     * @param  array<string, string>  $org  organization profile values, for example ['org.name' => 'Example Corp']
     * @param  list<string>|null  $concepts  the plan concepts of the IR plan; null if there is no IR plan
     * @return array{errors: list<string>, facts: array<string, string>, scenario: array<string, mixed>}
     */
    public function check(array $data, array $org, ?array $concepts): array
    {
        $this->errors = [];
        $this->missing = [];

        // Facts can use organization values only.
        $facts = [];
        foreach ($data['facts'] ?? [] as $name => $value) {
            $value = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            $facts['facts.'.$name] = $this->fill($value, $org, "fact $name");
        }
        $values = $org + $facts;

        $scenario = $data;
        foreach (['briefing', 'objectives', 'rubric'] as $part) {
            if (isset($scenario[$part])) {
                $scenario[$part] = $this->fillAll($scenario[$part], $values, $part);
            }
        }
        foreach ($scenario['injects'] as $n => $inject) {
            $scenario['injects'][$n] = $this->fillAll($inject, $values, 'inject '.$inject['id']);
        }

        foreach ($this->missing as $name => $places) {
            $hint = match (true) {
                str_starts_with($name, 'org.') => 'Add it to the organization profile.',
                str_starts_with($name, 'facts.') => 'Add it to facts. A fact cannot use another fact.',
                default => 'A placeholder starts with org. or facts.',
            };
            $this->errors[] = '{{'.$name.'}} has no value (used in '.implode(', ', array_keys($places)).'). '.$hint;
        }

        $this->checkOrderAndTimes($scenario['injects']);
        $this->checkConcepts($scenario['injects'], $concepts);
        $this->checkFixedValues($scenario['injects'], array_merge(array_values($facts), array_values($org)));

        $ids = array_column($scenario['injects'], 'id');
        foreach (array_keys($scenario['rubric'] ?? []) as $injectId) {
            if (! in_array((string) $injectId, $ids, true)) {
                $this->errors[] = "The rubric has items for inject $injectId, which does not exist.";
            }
        }

        $factValues = [];
        foreach ($facts as $key => $value) {
            $factValues[substr($key, 6)] = $value;
        }

        return ['errors' => $this->errors, 'facts' => $factValues, 'scenario' => $scenario];
    }

    /**
     * The scenario as YAML again, after the application changed it (an AI step or a picture).
     * Comments in the old YAML are lost.
     *
     * @param  array<string, mixed>  $data
     */
    public static function dump(array $data): string
    {
        return Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * The picture file names that the artifacts of a scenario use.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function images(array $data): array
    {
        $names = [];
        foreach ($data['injects'] ?? [] as $inject) {
            foreach ($inject['artifacts'] ?? [] as $artifact) {
                if (isset($artifact['image'])) {
                    $names[] = (string) $artifact['image'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The injects that can come after inject $n: its branches, its "next", or the next inject in the list.
     *
     * @param  list<array<string, mixed>>  $injects
     * @return list<array{0: string, 1: string}> inject id and how it is reached
     */
    public static function following(array $injects, int $n): array
    {
        $inject = $injects[$n];
        if (! empty($inject['branches'])) {
            return array_map(fn (array $branch) => [(string) $branch['goto'], 'a branch'], $inject['branches']);
        }
        if (isset($inject['next'])) {
            return [[(string) $inject['next'], 'next']];
        }
        if (! empty($inject['end']) || ! isset($injects[$n + 1])) {
            return [];
        }

        return [[$injects[$n + 1]['id'], 'the order of the list']];
    }

    /**
     * @param  list<array<string, mixed>>  $injects
     */
    private function checkOrderAndTimes(array $injects): void
    {
        $ids = array_column($injects, 'id');
        $minutes = [];
        foreach ($injects as $inject) {
            if (preg_match(self::TIME, (string) $inject['at'], $m)) {
                $minutes[$inject['id']] = (($inject['day'] ?? 1) * 24 + (int) $m[1]) * 60 + (int) $m[2];
            } else {
                $this->errors[] = "Inject {$inject['id']}: the time (at) must be HH:MM, for example 09:05. It is \"{$inject['at']}\".";
            }
        }
        $at = fn (array $inject) => (isset($inject['day']) ? 'day '.$inject['day'].' ' : '').$inject['at'];

        foreach ($injects as $n => $inject) {
            foreach (self::following($injects, $n) as [$target, $how]) {
                $index = array_search($target, $ids, true);
                if ($index === false) {
                    $this->errors[] = "Inject {$inject['id']}: $how goes to $target, which does not exist.";
                } elseif (isset($minutes[$inject['id']], $minutes[$target]) && $minutes[$target] < $minutes[$inject['id']]) {
                    $this->errors[] = "Inject $target comes after inject {$inject['id']} ({$at($inject)}), but its time ({$at($injects[$index])}) is earlier.";
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $injects
     * @param  list<string>|null  $concepts
     */
    private function checkConcepts(array $injects, ?array $concepts): void
    {
        $expected = [];
        foreach ($injects as $inject) {
            foreach ($inject['expected_plan'] ?? [] as $concept) {
                $expected[(string) $concept][] = $inject['id'];
            }
        }

        if ($expected !== [] && $concepts === null) {
            $this->errors[] = 'There is no IR plan. Upload the plan, then set the plan concepts on its sections.';

            return;
        }
        foreach ($expected as $concept => $injectIds) {
            if (! in_array($concept, $concepts ?? [], true)) {
                $this->errors[] = "No section of the IR plan has the plan concept $concept (used in inject ".implode(', ', array_unique($injectIds)).'). Open the IR plan, select "Correct this section" on the right section and type '.$concept.' in "Plan concept".';
            }
        }
    }

    /**
     * Participants see the inject text and the artifacts. A host name, an IP address
     * or a time in them must be a fact, so that two injects cannot disagree.
     *
     * @param  list<array<string, mixed>>  $injects
     * @param  list<string>  $known  fact and organization values
     */
    private function checkFixedValues(array $injects, array $known): void
    {
        $known = implode("\n", $known);
        foreach ($injects as $inject) {
            $texts = ['text' => $inject['body'] ?? ''];
            foreach ($inject['artifacts'] ?? [] as $n => $artifact) {
                $label = 'artifact '.($n + 1);
                if (($artifact['type'] ?? null) === 'image') {
                    if (! isset($artifact['image'])) {
                        $this->errors[] = "Inject {$inject['id']}, $label is a picture without an image. Generate the picture or upload one.";
                    }

                    continue;
                }
                if (! is_string($artifact['content'] ?? null) || trim($artifact['content']) === '') {
                    $this->errors[] = "Inject {$inject['id']}, $label has no content. Put the artifact text in content: one YAML file holds the whole scenario.";
                }
                $texts[$label] = $artifact['content'] ?? '';
            }

            foreach ($texts as $label => $text) {
                preg_match_all(self::FIXED_VALUES, (string) $text, $matches);
                foreach (array_unique($matches[0]) as $value) {
                    if (! preg_match('/(?<![\w.:])'.preg_quote($value, '/').'(?![\w:]|\.\d)/', $known)) {
                        $this->errors[] = "Inject {$inject['id']}, $label: \"$value\" is a fixed value that is not a fact. Add it to facts and use {{facts.name}}.";
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fillAll(mixed $value, array $values, string $where): mixed
    {
        if (is_string($value)) {
            return $this->fill($value, $values, $where);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->fillAll($item, $values, $where);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fill(string $text, array $values, string $where): string
    {
        return (string) preg_replace_callback(self::PLACEHOLDER, function (array $m) use ($values, $where): string {
            if (array_key_exists($m[1], $values)) {
                return $values[$m[1]];
            }
            $this->missing[$m[1]][$where] = true;

            return $m[0];
        }, $text);
    }

    /**
     * @param  array<mixed>  $data
     */
    private function optionalText(array $data, string $key, string $where): void
    {
        if (isset($data[$key]) && ! is_scalar($data[$key])) {
            $this->fail("$where: $key must be text.");
        }
    }

    /**
     * @param  array<mixed>  $data
     */
    private function optionalTextList(array $data, string $key, string $where): void
    {
        if (! isset($data[$key])) {
            return;
        }
        if (! is_array($data[$key]) || ! array_is_list($data[$key])
            || array_filter($data[$key], fn ($value) => ! is_scalar($value)) !== []) {
            $this->fail("$where: $key must be a list of text items.");
        }
    }

    /**
     * A list of blocks, for example the artifacts of an inject.
     *
     * @param  array<mixed>  $data
     * @param  list<string>  $fields  the text fields of a block
     * @param  list<string>  $required  the fields that each block must have
     */
    private function optionalBlocks(array $data, string $key, string $where, array $fields, array $required): void
    {
        if (! isset($data[$key])) {
            return;
        }
        if (! is_array($data[$key]) || ! array_is_list($data[$key])) {
            $this->fail("$where: $key must be a list.");
        }
        foreach ($data[$key] as $n => $block) {
            $label = "$where, $key item ".($n + 1);
            if (! is_array($block)) {
                $this->fail("$label must be a block with ".implode(', ', $fields).'.');
            }
            foreach ($fields as $field) {
                if (isset($block[$field]) && ! is_scalar($block[$field])) {
                    $this->fail("$label: $field must be text.");
                }
            }
            foreach ($required as $field) {
                if (! isset($block[$field]) || trim((string) $block[$field]) === '') {
                    $this->fail("$label has no $field.");
                }
            }
        }
    }

    private function fail(string $message): never
    {
        throw new InvalidArgumentException($message);
    }
}
