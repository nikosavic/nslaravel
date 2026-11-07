<?php

namespace Tests\Unit;

use App\Support\CaseStudy;
use Tests\TestCase;

class CaseStudyTest extends TestCase
{
    public function test_leading_key_value_lines_become_facts(): void
    {
        $r = CaseStudy::parse("Stack: Laravel, Tailwind CSS, MySQL\nRole: Full-stack\n\nRunSphere connects runners with organizers.");

        $this->assertSame([['Stack', 'Laravel, Tailwind CSS, MySQL'], ['Role', 'Full-stack']], $r['facts']);
        $this->assertSame('<p>RunSphere connects runners with organizers.</p>', $r['html']);
    }

    public function test_headings_lists_and_paragraphs(): void
    {
        $text = "Overview\nA platform for races.\nIt has roles.\n\nFeatures:\n- Search events\n- Pay with Iyzico\n\n## Challenges\n1. Roles\n2. Payments";
        $html = CaseStudy::parse($text)['html'];

        $this->assertStringContainsString('<h2>Overview</h2>', $html);
        $this->assertStringContainsString('<p>A platform for races. It has roles.</p>', $html);
        $this->assertStringContainsString('<h2>Features</h2><ul><li>Search events</li><li>Pay with Iyzico</li></ul>', $html);
        $this->assertStringContainsString('<h2>Challenges</h2><ol><li>Roles</li><li>Payments</li></ol>', $html);
    }

    public function test_html_is_escaped_and_inline_formatting_works(): void
    {
        $html = CaseStudy::parse('Use **bold**, `code` and https://example.com/a?b=1 <script>alert(1)</script>.')['html'];

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
        $this->assertStringContainsString('<a href="https://example.com/a?b=1"', $html);
    }

    public function test_a_normal_sentence_is_not_a_heading(): void
    {
        $html = CaseStudy::parse("First paragraph.\n\nThis one is short.\n\nAnother.")['html'];

        $this->assertStringNotContainsString('<h2>', $html);
    }

    public function test_empty_input(): void
    {
        $this->assertSame(['facts' => [], 'html' => ''], CaseStudy::parse(null));
    }
}
