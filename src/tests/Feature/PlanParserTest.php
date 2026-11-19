<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Services\PlanParser;
use InvalidArgumentException;
use Tests\TestCase;

class PlanParserTest extends TestCase
{
    private const EXPECTED = ['1', '2', '2.1', '3', '4', '4.1'];

    /**
     * @return list<?string>
     */
    private function numbers(string $file, string $extension): array
    {
        $sections = (new PlanParser)->parse(base_path('tests/Fixtures/plans/'.$file), $extension);

        return array_values(array_filter(array_column($sections, 'number')));
    }

    public function test_it_reads_a_markdown_plan(): void
    {
        $this->assertSame(self::EXPECTED, $this->numbers('example-plan.md', 'md'));
    }

    public function test_it_reads_a_docx_plan(): void
    {
        $this->assertSame(self::EXPECTED, $this->numbers('example-plan.docx', 'docx'));
    }

    public function test_it_reads_a_pdf_plan(): void
    {
        $this->assertSame(self::EXPECTED, $this->numbers('example-plan.pdf', 'pdf'));
    }

    public function test_titles_and_text_go_to_the_right_section(): void
    {
        $sections = (new PlanParser)->parse(base_path('tests/Fixtures/plans/example-plan.md'), 'md');
        $reporting = collect($sections)->firstWhere('number', '3');

        $this->assertSame('Reporting a Suspected Incident', $reporting['title']);
        // The numbered list inside section 3 stays in its text: it is not a heading.
        $this->assertStringContainsString('1. Name of the person who reports the incident', $reporting['body']);
    }

    public function test_lists_tables_and_the_table_of_contents_are_not_headings(): void
    {
        $text = implode("\n", [
            'Example Corp, 3062 Main Street',
            '1. Purpose .......... 2',
            '2. Scope ............ 3',
            '1. Purpose',
            'This plan describes the process.',
            '2. Scope',
            'Send this information:',
            '1. Name of the reporter',
            '2. Contact information',
            '3. Description of the event',
            '2.1 Locations',
            'Low: 90 Business Days',
            '3. Roles',
            'The team.',
        ]);

        $sections = (new PlanParser)->split($text);

        $this->assertSame(['1', '2', '2.1', '3'], array_values(array_filter(array_column($sections, 'number'))));
    }

    public function test_an_unknown_file_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PlanParser)->text(base_path('tests/Fixtures/plans/example-plan.md'), 'exe');
    }
}
