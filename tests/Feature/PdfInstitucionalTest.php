<?php

namespace Tests\Feature;

use App\Models\EstagioAluno;
use App\Models\EstagioRelatorio;
use App\Services\RelatorioPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PdfInstitucionalTest extends TestCase
{
    private function dados(?string $referencias = null): array
    {
        return [new EstagioAluno(['nome' => 'Estudante de teste', 'escola' => 'Escola', 'curso' => 'Informática', 'concedente' => 'Empresa', 'orientador' => 'Professor']),
            new EstagioRelatorio(['introducao' => str_repeat('Introdução de estágio. ', 40), 'atividades' => str_repeat('Atividade de estágio. ', 60), 'conclusao' => str_repeat('Aprendizado de estágio. ', 27), 'referencias' => $referencias, 'local' => 'Santa Quitéria', 'data_relatorio' => '2026-11-17'])];
    }

    public function test_document_has_six_or_seven_pages_according_to_references(): void
    {
        foreach ([null => 6, 'Fonte consultada.' => 7] as $referencias => $total) {
            [$aluno, $relatorio] = $this->dados($referencias ?: null);
            [$pdf, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio);
            $this->assertSame([], $erros);
            $this->assertSame($total, $pdf->getDomPDF()->getCanvas()->get_page_count());
            $this->assertStringStartsWith('%PDF-', $pdf->output());
        }
    }

    public function test_cover_and_summary_use_actual_section_pages(): void
    {
        [$aluno, $relatorio] = $this->dados('Fonte consultada.');
        $metodo = new \ReflectionMethod(RelatorioPdf::class, 'renderizar');
        [, $mapa] = $metodo->invoke(app(RelatorioPdf::class), $aluno, $relatorio, []);
        $paginas = array_map(fn ($numeros) => min($numeros), $mapa);
        $this->assertSame(['capa' => 1, 'identificacao' => 2, 'sumario' => 3, 'introducao' => 4, 'atividades' => 5, 'conclusao' => 6, 'referencias' => 7], $paginas);
        $html = view('pdf.estagio', compact('aluno', 'relatorio', 'paginas'))->render();
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/pdf/governo-ceara-capa.png'))), $html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/pdf/escola-capa.png'))), $html);
        $this->assertStringContainsString('ESTUDANTE DE TESTE', $html);
        $this->assertStringContainsString('<td class="sumario-numero">7</td>', $html);
    }

    public function test_signature_and_date_follow_last_section_on_same_page(): void
    {
        foreach ([null => 6, 'Fonte consultada.' => 7, " \t" => 6] as $referencias => $paginaEsperada) {
            [$aluno, $relatorio] = $this->dados($referencias ?: null);
            $pdf = Pdf::loadView('pdf.estagio', compact('aluno', 'relatorio'))->setPaper('a4');
            $paginas = [];
            $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$paginas) {
                $node = $frame->get_node();
                if ($node->nodeType !== XML_TEXT_NODE || trim($node->nodeValue) === '') {
                    return;
                }
                $classe = $node->parentNode?->attributes?->getNamedItem('class')?->nodeValue;
                if ($classe === 'assinatura-linha' || $classe === 'fechamento') {
                    $paginas[$classe][] = $canvas->get_page_number();
                }
            }]]);
            $pdf->render();
            $this->assertSame([$paginaEsperada], array_values(array_unique($paginas['assinatura-linha'] ?? [])));
            $this->assertSame([$paginaEsperada], array_values(array_unique($paginas['fechamento'] ?? [])));
        }
    }

    public function test_rendered_overflow_is_rejected_but_can_be_previewed(): void
    {
        [$aluno, $relatorio] = $this->dados();
        $relatorio->conclusao = implode("\n", array_fill(0, 45, 'Linha'));
        [$pdf, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio, false);
        $this->assertArrayHasKey('conclusao', $erros, json_encode($erros));
        $this->assertGreaterThan(6, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->expectException(ValidationException::class);
        app(RelatorioPdf::class)->gerar($aluno, $relatorio);
    }

    public function test_whitespace_only_references_are_omitted(): void
    {
        foreach ([null, '', " \n\t", "\u{00A0}\u{2003}"] as $referencias) {
            [$aluno, $relatorio] = $this->dados($referencias);
            [$pdf, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio);
            $this->assertSame([], $erros);
            $this->assertSame(6, $pdf->getDomPDF()->getCanvas()->get_page_count());
        }
    }

    public function test_overflow_only_identifies_the_responsible_section(): void
    {
        [$aluno, $relatorio] = $this->dados('Fonte consultada.');
        $relatorio->atividades = implode("\n", array_fill(0, 50, 'Atividade realizada.'));
        [$pdf, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio, false);
        $this->assertSame(['atividades'], array_keys($erros));
        $this->assertStringContainsString('quebras de linha e a separação em parágrafos', $erros['atividades']);
        $this->assertGreaterThan(7, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $relatorio->referencias = " \u{00A0}";
        [, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio, false);
        $this->assertSame(['atividades'], array_keys($erros));
    }

    public function test_references_overflow_is_reported_only_when_present(): void
    {
        [$aluno, $relatorio] = $this->dados(implode("\n", array_fill(0, 55, 'Fonte consultada.')));
        [, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio, false);
        $this->assertSame(['referencias'], array_keys($erros));
    }
}
